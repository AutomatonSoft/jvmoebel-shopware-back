import template from './jv-legacy-catalog-index.html.twig';
import './jv-legacy-catalog-index.scss';

const { Mixin } = Shopware;

export default {
    template,

    inject: ['acl', 'jvLegacyCatalogApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            channels: [],
            salesChannelId: null,
            sources: [],
            sourceId: null,
            rows: [],
            detail: null,
            searchTerm: '',
            searchResults: [],
            activeTab: 'tree',
            hasSearched: false,
            isLoading: false,
            isSearching: false,
            isLoadingDetail: false,
            expandedIds: [],
        };
    },

    computed: {
        channelOptions() {
            return this.channels.map((channel) => ({
                value: channel.id,
                label: channel.name + ' — ' + channel.domains.join(', '),
            }));
        },

        selectedSource() {
            return this.sources.find((source) => source.id === this.sourceId) ?? null;
        },

        sourceOptions() {
            return this.sources.map((source) => ({
                value: source.id,
                label: source.sourceProject + ' (' + source.categoryCount + ')',
            }));
        },

        visibleRows() {
            const categoriesBySourceId = new Map(this.rows.map((item) => [item.sourceCategoryId, item]));

            return this.rows.filter((item) => {
                let parentId = item.sourceParentId;
                while (parentId !== 0) {
                    const parent = categoriesBySourceId.get(parentId);
                    if (!parent || !this.expandedIds.includes(parent.id)) return false;
                    parentId = parent.sourceParentId;
                }

                return true;
            });
        },
    },

    created() {
        this.loadSalesChannels();
    },

    methods: {
        async loadSalesChannels() {
            if (!this.acl.can('jv_legacy_catalog:read')) return;
            this.isLoading = true;
            try {
                const response = await this.jvLegacyCatalogApiService.salesChannels();
                this.channels = response.data.data ?? [];
                if (this.channels.length > 0) {
                    this.salesChannelId = this.channels[0].id;
                    await this.onSalesChannelChange(this.salesChannelId);
                }
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async onSalesChannelChange(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.sources = [];
            this.sourceId = null;
            this.rows = [];
            this.detail = null;
            this.searchResults = [];
            this.hasSearched = false;
            this.activeTab = 'tree';
            if (!salesChannelId) return;

            this.isLoading = true;
            try {
                const response = await this.jvLegacyCatalogApiService.sources(salesChannelId);
                this.sources = response.data.data ?? [];
                if (this.sources.length > 0) {
                    this.sourceId = this.sources[0].id;
                    await this.onSourceChange(this.sourceId);
                }
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async onSourceChange(sourceId) {
            this.sourceId = sourceId;
            this.detail = null;
            this.searchResults = [];
            this.hasSearched = false;
            this.activeTab = 'tree';
            this.expandedIds = [];
            this.rows = [];
            if (!sourceId) return;

            this.isLoading = true;
            try {
                this.rows = this.buildTree(await this.loadAllCategories(sourceId));
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async loadAllCategories(sourceId) {
            const categories = [];
            const limit = 500;
            let offset = 0;
            let total = 0;
            do {
                const response = await this.jvLegacyCatalogApiService.allCategories(sourceId, { limit, offset });
                const page = response.data.data ?? [];
                total = response.data.total ?? page.length;
                categories.push(...page);
                offset += page.length;
            } while (offset < total);

            return categories;
        },

        buildTree(categories) {
            const childrenByParentId = new Map();
            for (const category of categories) {
                const siblings = childrenByParentId.get(category.sourceParentId) ?? [];
                siblings.push(category);
                childrenByParentId.set(category.sourceParentId, siblings);
            }

            const stack = (childrenByParentId.get(0) ?? []).slice().reverse().map((category) => ({ category, depth: 0 }));
            const rows = [];
            while (stack.length > 0) {
                const { category, depth } = stack.pop();
                const children = childrenByParentId.get(category.sourceCategoryId) ?? [];
                rows.push({ ...category, depth, hasChildren: children.length > 0 });
                for (let index = children.length - 1; index >= 0; index -= 1) {
                    stack.push({ category: children[index], depth: depth + 1 });
                }
            }

            return rows;
        },

        toggle(item) {
            this.expandedIds = this.expandedIds.includes(item.id)
                ? this.expandedIds.filter((id) => id !== item.id)
                : [...this.expandedIds, item.id];
        },

        async search() {
            const term = this.searchTerm.trim();
            if (!this.sourceId || term.length === 0) return;

            this.activeTab = 'search';
            this.hasSearched = true;
            this.searchResults = [];
            this.isSearching = true;
            this.detail = null;
            try {
                const response = await this.jvLegacyCatalogApiService.search(this.sourceId, term);
                this.searchResults = response.data.data ?? [];
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                this.isSearching = false;
            }
        },

        onTabKeydown(event, currentTab) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            event.preventDefault();
            const nextTab = event.key === 'Home'
                ? 'tree'
                : event.key === 'End'
                    ? 'search'
                    : currentTab === 'tree'
                        ? 'search'
                        : 'tree';

            this.activeTab = nextTab;
            this.$nextTick(() => this.$el.querySelector(`[aria-controls="jv-legacy-catalog-${nextTab}-panel"]`)?.focus());
        },

        async selectSearchResult(result) {
            this.expandedIds = (result.ancestors ?? []).map((item) => item.id);
            await this.selectCategory(result);
        },

        categoryDetailUrl(category) {
            return this.$router.resolve({
                name: 'jv.legacy.catalog.detail',
                params: { sourceId: this.sourceId, categoryId: category.id },
            }).href;
        },

        async selectCategory(category) {
            this.detail = null;
            this.isLoadingDetail = true;
            try {
                const response = await this.jvLegacyCatalogApiService.category(this.sourceId, category.id);
                this.detail = response.data.data;
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error) });
            } finally {
                this.isLoadingDetail = false;
            }
        },


        errorMessage(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? error?.message ?? this.$t('jv-legacy-catalog.errors.request');
        },
    },
};
