/**
 * Canvas preview for CMS element `jv-side-navigation`.
 *
 * Shopping Experiences only. Public drawer = Next.js.
 * Layout: logo → category typeahead → L1…L4 drill-down → footer.
 */
import template from './sw-cms-el-jv-side-navigation.html.twig';
import './sw-cms-el-jv-side-navigation.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const TREE_DEPTH = 4;

export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            /** Drill-down columns. Index 0 is L1; later indexes open on › click. */
            columns: [],
            logoUrl: null,
            logoLoadToken: 0,
            categoryLoadToken: 0,
            /** L1 category nodes; nested `children` are L2–L4. */
            categoryTree: [],
            categoryLoading: false,
            searchQuery: '',
            searchOpen: false,
            highlightedId: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        footerItems() {
            const items = this.element?.config?.footer?.value?.items;

            return Array.isArray(items) ? items : [];
        },

        logoMediaId() {
            return this.element?.config?.logoMedia?.value || null;
        },

        logoLink() {
            return this.element?.config?.logoLink?.value || '';
        },

        rootCategoryId() {
            return this.element?.config?.rootCategoryId?.value || null;
        },

        showIcons() {
            return this.element?.config?.showIcons?.value !== false;
        },

        searchPlaceholder() {
            const value = (this.element?.config?.searchPlaceholder?.value || '').trim();

            return value || this.$t('cms.elements.jv-side-navigation.config.searchPlaceholder.placeholder');
        },

        canGoBack() {
            return this.columns.length > 1;
        },

        showSearchClear() {
            return this.searchQuery.trim() !== '';
        },

        /** Prefix match on label, all 4 levels. Categories only — no product search. */
        searchSuggestions() {
            const query = this.searchQuery.trim();
            if (!query) {
                return [];
            }

            return this.flattenCategoryNodes(this.categoryTree)
                .filter((entry) => this.startsWithPrefix(entry.label, query));
        },
    },

    watch: {
        logoMediaId: {
            immediate: true,
            handler() {
                this.loadLogo();
            },
        },

        'element.data.logo': {
            deep: true,
            handler() {
                this.loadLogo();
            },
        },

        rootCategoryId: {
            immediate: true,
            handler() {
                this.loadCategoryTree();
            },
        },

        showIcons() {
            this.loadCategoryTree();
        },
    },

    created() {
        this.initElementConfig('jv-side-navigation');
        this.resetColumnsToRoot();
    },

    methods: {
        async loadLogo() {
            const token = this.logoLoadToken + 1;
            this.logoLoadToken = token;

            const fromData = this.element?.data?.logo;
            if (fromData?.url) {
                this.logoUrl = fromData.url;

                return;
            }

            const mediaId = this.logoMediaId;
            if (!mediaId) {
                this.logoUrl = null;

                return;
            }

            if (typeof mediaId === 'object' && mediaId.url) {
                this.logoUrl = mediaId.url;

                return;
            }

            try {
                const media = await this.mediaRepository.get(mediaId, Shopware.Context.api);
                if (token !== this.logoLoadToken) {
                    return;
                }

                this.logoUrl = media?.url || null;
            } catch {
                if (token === this.logoLoadToken) {
                    this.logoUrl = null;
                }
            }
        },

        categoryAssociationPath(depth) {
            const levels = Math.min(Math.max(Number(depth) || 0, 0), TREE_DEPTH);
            if (levels < 1) {
                return [];
            }

            const parts = [];
            let path = 'children';

            for (let i = 0; i < levels; i += 1) {
                parts.push(path);
                parts.push(`${path}.media`);
                path = `${path}.children`;
            }

            return parts;
        },

        toEntityArray(collection) {
            if (!collection) {
                return [];
            }

            if (Array.isArray(collection)) {
                return collection;
            }

            if (typeof collection.forEach === 'function') {
                const items = [];
                collection.forEach((item) => {
                    items.push(item);
                });

                return items;
            }

            return [];
        },

        mapCategoryNode(category, showIcons, remainingDepth) {
            const nested = remainingDepth > 1
                ? this.toEntityArray(category.children)
                    .map((child) => this.mapCategoryNode(child, showIcons, remainingDepth - 1))
                : [];

            return {
                id: category.id,
                label: category.translated?.name || category.name || category.id,
                iconUrl: showIcons ? (category.media?.url || null) : null,
                active: category.active !== false,
                children: nested,
            };
        },

        /**
         * Load L1–L4 for the current root. Stale responses from a previous root
         * must not overwrite categoryTree / categoryLoading / columns.
         */
        async loadCategoryTree() {
            const token = this.categoryLoadToken + 1;
            this.categoryLoadToken = token;
            const rootId = this.rootCategoryId;

            if (!rootId) {
                this.categoryTree = [];
                this.categoryLoading = false;
                this.resetColumnsToRoot();

                return;
            }

            this.categoryLoading = true;

            try {
                // Children of root = L1; nested associations hydrate L2–L4 in one search.
                const criteria = new Criteria(1, 500);
                criteria.addFilter(Criteria.equals('parentId', rootId));
                criteria.addAssociation('media');
                this.categoryAssociationPath(TREE_DEPTH - 1).forEach((association) => {
                    criteria.addAssociation(association);
                });

                const result = await this.categoryRepository.search(criteria, Shopware.Context.api);
                if (token !== this.categoryLoadToken) {
                    return;
                }

                this.categoryTree = this.toEntityArray(result)
                    .map((category) => this.mapCategoryNode(category, this.showIcons, TREE_DEPTH));
            } catch {
                if (token !== this.categoryLoadToken) {
                    return;
                }

                this.categoryTree = [];
            } finally {
                if (token !== this.categoryLoadToken) {
                    return;
                }

                this.categoryLoading = false;
                if (this.columns.length <= 1) {
                    this.resetColumnsToRoot();
                }
            }
        },

        resetColumnsToRoot() {
            this.columns = [this.buildRootColumn()];
        },

        buildRootColumn() {
            const title = this.$t('cms.elements.jv-side-navigation.label');
            const rows = [];

            if (!this.rootCategoryId) {
                rows.push({
                    kind: 'empty',
                    id: 'no-root',
                    label: this.$t('cms.elements.jv-side-navigation.component.noRoot'),
                });
            } else if (this.categoryLoading && !this.categoryTree.length) {
                rows.push({
                    kind: 'empty',
                    id: 'loading',
                    label: this.$t('cms.elements.jv-side-navigation.component.loadingCategories'),
                });
            } else if (!this.categoryTree.length) {
                rows.push({
                    kind: 'empty',
                    id: 'empty-tree',
                    label: this.$t('cms.elements.jv-side-navigation.component.emptyCategoryTree'),
                });
            } else {
                this.categoryTree.forEach((node) => {
                    rows.push(this.categoryNodeToRow(node));
                });
            }

            return {
                id: 'root',
                title,
                rows,
            };
        },

        categoryNodeToRow(node) {
            const children = Array.isArray(node.children) ? node.children : [];

            return {
                kind: 'link',
                id: node.id,
                label: node.label,
                iconUrl: node.iconUrl || null,
                active: node.active !== false,
                children: children.map((child) => this.categoryNodeToRow(child)),
                hasChildren: children.length > 0,
            };
        },

        buildChildColumnRows(row) {
            const childRows = Array.isArray(row.children) && row.children.length
                ? row.children
                : [{
                    kind: 'empty',
                    id: `${row.id}-empty`,
                    label: this.$t('cms.elements.jv-side-navigation.component.noChildLinks'),
                }];

            return [
                {
                    kind: 'all-link',
                    id: `${row.id}-all`,
                    label: this.$t('cms.elements.jv-side-navigation.component.allLinkForCategory', {
                        name: row.label,
                    }),
                },
                ...childRows,
            ];
        },

        onRowClick(row, columnIndex) {
            if (!row || row.kind !== 'link' || !row.hasChildren) {
                return;
            }

            const index = Number.isInteger(columnIndex) ? columnIndex : 0;
            const existing = this.columns[index + 1];

            // Second click on the same row closes the branch; another row replaces columns to the right.
            if (existing && existing.sourceRowId === row.id) {
                this.columns = this.columns.slice(0, index + 1);

                return;
            }

            const nextColumn = {
                id: `col-${row.id}`,
                sourceRowId: row.id,
                title: row.label,
                rows: this.buildChildColumnRows(row),
            };

            this.columns = [...this.columns.slice(0, index + 1), nextColumn];
            this.$nextTick(() => {
                this.scrollColumnsToEnd();
            });
        },

        scrollColumnsToEnd() {
            const el = this.$refs.columnsScroller;
            if (!el) {
                return;
            }

            el.scrollLeft = el.scrollWidth;
        },

        goBack() {
            if (!this.canGoBack) {
                return;
            }

            this.columns = this.columns.slice(0, -1);
        },

        footerVisibilityLabel(visibility) {
            const key = `cms.elements.jv-side-navigation.component.visibility.${visibility}`;
            const translated = this.$t(key);

            return translated === key ? (visibility || 'always') : translated;
        },

        isRowActive(columnIndex, row) {
            const next = this.columns[columnIndex + 1];

            return Boolean(
                (next && row?.kind === 'link' && next.sourceRowId === row.id)
                || (row?.id && row.id === this.highlightedId),
            );
        },

        startsWithPrefix(label, query) {
            const needle = query.trim().toLocaleLowerCase();
            if (!needle) {
                return false;
            }

            return String(label || '').toLocaleLowerCase().startsWith(needle);
        },

        flattenCategoryNodes(nodes, ancestors = []) {
            const list = [];

            (Array.isArray(nodes) ? nodes : []).forEach((node) => {
                const path = [...ancestors, node];
                list.push({
                    id: node.id,
                    label: node.label,
                    iconUrl: node.iconUrl || null,
                    path,
                });
                list.push(...this.flattenCategoryNodes(node.children, path));
            });

            return list;
        },

        onSearchFocus() {
            this.searchOpen = true;
        },

        onSearchInput() {
            this.searchOpen = true;
        },

        onSearchKeydown(event) {
            if (event.key === 'Escape') {
                this.searchOpen = false;
            }
        },

        clearSearch() {
            this.searchQuery = '';
            this.searchOpen = true;
        },

        /**
         * Admin preview: open the branch to this category. Storefront navigation is Next.js.
         */
        onSuggestionClick(suggestion) {
            this.searchOpen = false;
            this.searchQuery = '';
            this.highlightedId = suggestion.id;
            this.openPath(suggestion.path);
        },

        openPath(pathNodes) {
            const columns = [this.buildRootColumn()];

            (Array.isArray(pathNodes) ? pathNodes : []).forEach((node) => {
                const row = this.categoryNodeToRow(node);
                if (!row.hasChildren) {
                    return;
                }

                columns.push({
                    id: `col-${row.id}`,
                    sourceRowId: row.id,
                    title: row.label,
                    rows: this.buildChildColumnRows(row),
                });
            });

            this.columns = columns;
            this.$nextTick(() => {
                this.scrollColumnsToEnd();
            });
        },
    },
};
