/**
 * Canvas preview for CMS element `jv-side-navigation`.
 *
 * Shown only in Shopping Experiences (Administration layout editor).
 * Reads `element.config`, loads categories/media via Admin API, and drives
 * an interactive multi-column preview (tabs + drill-down + footer).
 *
 * The public storefront drawer is rendered by Next.js — not by this component.
 */
import template from './sw-cms-el-jv-side-navigation.html.twig';
import './sw-cms-el-jv-side-navigation.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    // Admin API repositories for Media (logo/icons) and Category (trees).
    inject: ['repositoryFactory'],

    mixins: [
        // Provides `this.element`, `initElementConfig()`, CMS page context, etc.
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            // Currently selected preview tab (often mirrors defaultTabId).
            activeTabId: null,

            /**
             * Drill-down column stack.
             * Index 0 = root column for the active tab; later indexes open on click.
             * @type {Array<{ id: string, sourceRowId?: string, title: string, rows: Array }>}
             */
            columns: [],

            // Resolved logo URL for the chrome <img>.
            logoUrl: null,

            // Drops stale async logo responses when media id changes quickly.
            logoLoadToken: 0,

            // Cache: sectionId → { rootLabel, children[] } for category-tree sections.
            categoryTrees: {},

            // sectionId → true while a category search is in flight.
            categoryLoading: {},

            // Cache: mediaId → url (manual-link icons).
            mediaUrlById: {},
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        // Raw tabs from CMS slot config.
        tabs() {
            const value = this.element?.config?.tabs?.value;

            return Array.isArray(value) ? value : [];
        },

        defaultTabId() {
            return this.element?.config?.defaultTabId?.value || '';
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

        activeTab() {
            return this.tabs.find((tab) => tab.id === this.activeTabId) || this.tabs[0] || null;
        },

        // True once the user drilled into at least one child column.
        canGoBack() {
            return this.columns.length > 1;
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

        // Sidebar config edits should refresh the canvas immediately.
        tabs: {
            deep: true,
            handler() {
                this.ensureActiveTab();
                this.loadCategoryTreesForActiveTab();
                this.resetColumnsToRoot();
            },
        },

        defaultTabId() {
            this.ensureActiveTab();
            this.resetColumnsToRoot();
        },

        activeTabId() {
            this.loadCategoryTreesForActiveTab();
            this.resetColumnsToRoot();
        },
    },

    created() {
        this.initElementConfig('jv-side-navigation');
        this.ensureActiveTab();
        this.loadCategoryTreesForActiveTab();
        this.resetColumnsToRoot();
    },

    methods: {
        /** Prefer defaultTabId when valid; otherwise first tab. */
        ensureActiveTab() {
            if (this.activeTabId && this.tabs.some((tab) => tab.id === this.activeTabId)) {
                return;
            }

            if (this.defaultTabId && this.tabs.some((tab) => tab.id === this.defaultTabId)) {
                this.activeTabId = this.defaultTabId;

                return;
            }

            this.activeTabId = this.tabs[0]?.id || null;
        },

        selectTab(tabId) {
            if (!tabId || tabId === this.activeTabId) {
                return;
            }

            this.activeTabId = tabId;
        },

        tabLabel(tab) {
            return tab?.label || tab?.id || this.$t('cms.elements.jv-side-navigation.component.untitledTab');
        },

        /**
         * Resolve logo URL for the chrome <img>.
         * Prefer element.data.logo (set by config upload), else fetch by media UUID.
         */
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

        /** Lazy-load and cache a media URL (manual-link icons). */
        async resolveMediaUrl(mediaId) {
            if (!mediaId) {
                return null;
            }

            if (this.mediaUrlById[mediaId]) {
                return this.mediaUrlById[mediaId];
            }

            try {
                const media = await this.mediaRepository.get(mediaId, Shopware.Context.api);
                const url = media?.url || null;
                this.mediaUrlById = {
                    ...this.mediaUrlById,
                    [mediaId]: url,
                };

                return url;
            } catch {
                return null;
            }
        },

        /**
         * Nested association paths: children, children.media, children.children, …
         * Lets one search hydrate several drill-down levels.
         */
        categoryAssociationPath(depth) {
            const levels = Math.min(Math.max(Number(depth) || 0, 0), 5);
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

        /**
         * Repositories often return EntityCollection, not a plain Array.
         * Array.isArray(collection) is false — iterate via forEach instead.
         */
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

        /** Map a Shopware category entity into a plain preview tree node. */
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
         * Load direct children of section.rootCategoryId (plus nested associations).
         * Admin preview keeps inactive categories visible (dimmed in CSS).
         * Store API / sales-channel tree still filters by active + channel.
         */
        async loadCategoryTree(section) {
            const sectionId = section?.id;
            const rootId = section?.rootCategoryId;

            if (!sectionId || !rootId) {
                if (sectionId) {
                    this.categoryTrees = {
                        ...this.categoryTrees,
                        [sectionId]: {
                            rootLabel: '',
                            children: [],
                        },
                    };
                }

                return;
            }

            this.categoryLoading = {
                ...this.categoryLoading,
                [sectionId]: true,
            };

            try {
                const maxDepth = Math.min(Math.max(Number(section.maxDepth) || 3, 1), 5);
                const showIcons = section.showIcons !== false;

                let rootLabel = '';
                try {
                    const root = await this.categoryRepository.get(rootId, Shopware.Context.api);
                    rootLabel = root?.translated?.name || root?.name || '';
                } catch {
                    rootLabel = '';
                }

                const criteria = new Criteria(1, 500);
                criteria.addFilter(Criteria.equals('parentId', rootId));
                criteria.addAssociation('media');
                this.categoryAssociationPath(Math.max(maxDepth - 1, 0)).forEach((association) => {
                    criteria.addAssociation(association);
                });

                const result = await this.categoryRepository.search(criteria, Shopware.Context.api);
                const children = this.toEntityArray(result)
                    .map((category) => this.mapCategoryNode(category, showIcons, maxDepth));

                this.categoryTrees = {
                    ...this.categoryTrees,
                    [sectionId]: {
                        rootLabel,
                        children,
                    },
                };
            } catch {
                this.categoryTrees = {
                    ...this.categoryTrees,
                    [sectionId]: {
                        rootLabel: '',
                        children: [],
                    },
                };
            } finally {
                this.categoryLoading = {
                    ...this.categoryLoading,
                    [sectionId]: false,
                };
                // Refresh root column only if the user has not drilled down yet.
                if (this.columns.length <= 1) {
                    this.resetColumnsToRoot();
                }
            }
        },

        loadCategoryTreesForActiveTab() {
            const sections = Array.isArray(this.activeTab?.sections) ? this.activeTab.sections : [];

            sections
                .filter((section) => section?.type === 'category-tree' && section.rootCategoryId)
                .forEach((section) => {
                    this.loadCategoryTree(section);
                });
        },

        /** Reset drill-down to a single root column for the active tab. */
        resetColumnsToRoot() {
            this.columns = [this.buildRootColumn()];
            this.hydrateVisibleIcons(this.columns[0]?.rows || []);
        },

        /** Build the first column: flatten all sections of the active tab into rows. */
        buildRootColumn() {
            const tab = this.activeTab;
            const title = tab ? this.tabLabel(tab) : this.$t('cms.elements.jv-side-navigation.label');
            const rows = [];

            if (!tab) {
                return {
                    id: 'root',
                    title,
                    rows: [{
                        kind: 'empty',
                        id: 'no-tab',
                        label: this.$t('cms.elements.jv-side-navigation.component.noTabs'),
                    }],
                };
            }

            const sections = Array.isArray(tab.sections) ? tab.sections : [];

            if (!sections.length) {
                rows.push({
                    kind: 'empty',
                    id: 'no-sections',
                    label: this.$t('cms.elements.jv-side-navigation.component.noSections'),
                });
            }

            sections.forEach((section) => {
                rows.push(...this.rowsFromSection(section));
            });

            return {
                id: `tab-${tab.id || 'root'}`,
                title,
                rows,
            };
        },

        /** Convert one config section into preview rows (by section.type). */
        rowsFromSection(section) {
            if (!section || typeof section !== 'object') {
                return [];
            }

            switch (section.type) {
                case 'divider':
                    return [{
                        kind: 'divider',
                        id: section.id || 'divider',
                    }];

                case 'promo':
                    // Config fields: title / url / alt / mediaId (platform SPEC).
                    return [{
                        kind: 'promo',
                        id: section.id || 'promo',
                        title: section.title || this.$t('cms.elements.jv-side-navigation.component.promoEmpty'),
                        url: section.url || '',
                        alt: section.alt || '',
                    }];

                case 'manual-links': {
                    const items = Array.isArray(section.items) ? section.items : [];
                    if (!items.length) {
                        return [{
                            kind: 'empty',
                            id: `${section.id}-empty`,
                            label: this.$t('cms.elements.jv-side-navigation.component.noManualLinks'),
                        }];
                    }

                    return items.map((item) => this.manualItemToRow(item));
                }

                case 'category-tree':
                    return this.categorySectionRows(section);

                default:
                    return [];
            }
        },

        /** Recursive manual-link node → preview link row (supports children). */
        manualItemToRow(item) {
            const children = Array.isArray(item?.children) ? item.children : [];

            return {
                kind: 'link',
                id: item?.id || item?.label || 'item',
                label: item?.label || item?.id || '—',
                url: item?.url || '',
                iconMediaId: item?.iconMediaId || null,
                iconUrl: item?.iconMediaId ? this.mediaUrlById[item.iconMediaId] || null : null,
                children: children.map((child) => this.manualItemToRow(child)),
                hasChildren: children.length > 0,
            };
        },

        /** Rows for a category-tree section (loading / empty / all-link / children). */
        categorySectionRows(section) {
            const sectionId = section.id;
            const tree = this.categoryTrees[sectionId];
            const loading = this.categoryLoading[sectionId];
            const rows = [];

            if (!section.rootCategoryId) {
                rows.push({
                    kind: 'empty',
                    id: `${sectionId}-no-root`,
                    label: this.$t('cms.elements.jv-side-navigation.component.noRoot'),
                });

                return rows;
            }

            if (loading && !tree) {
                rows.push({
                    kind: 'empty',
                    id: `${sectionId}-loading`,
                    label: this.$t('cms.elements.jv-side-navigation.component.loadingCategories'),
                });

                return rows;
            }

            if (tree?.rootLabel) {
                rows.push({
                    kind: 'tree-root',
                    id: `${sectionId}-root-label`,
                    label: tree.rootLabel,
                });
            }

            // Config flag: root-level "Alle Möbel"-style link only.
            if (section.includeRootAsAllLink) {
                rows.push({
                    kind: 'all-link',
                    id: `${sectionId}-all`,
                    label: section.allLinkLabel
                        || tree?.rootLabel
                        || this.$t('cms.elements.jv-side-navigation.component.allLinkFallback'),
                });
            }

            const children = Array.isArray(tree?.children) ? tree.children : [];
            if (!children.length) {
                rows.push({
                    kind: 'empty',
                    id: `${sectionId}-empty-tree`,
                    label: this.$t('cms.elements.jv-side-navigation.component.emptyCategoryTree'),
                });

                return rows;
            }

            children.forEach((node) => {
                rows.push(this.categoryNodeToRow(node));
            });

            return rows;
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

        /**
         * Rows for a newly opened drill-down column.
         * Prepends "All {name}" (home24 pattern) above the child links.
         */
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

        /** Fill missing iconUrl for manual links currently visible in columns. */
        hydrateVisibleIcons(rows) {
            rows.forEach((row) => {
                if (row?.kind === 'link' && row.iconMediaId && !row.iconUrl) {
                    this.resolveMediaUrl(row.iconMediaId).then((url) => {
                        if (!url) {
                            return;
                        }

                        row.iconUrl = url;
                        this.columns = this.columns.map((column) => ({
                            ...column,
                            rows: column.rows.map((entry) => (
                                entry.id === row.id
                                    ? { ...entry, iconUrl: url }
                                    : entry
                            )),
                        }));
                    });
                }
            });
        },

        /**
         * Open / replace / toggle a child column.
         * - Second click on the same row closes the branch.
         * - Click on another row replaces everything to the right of this column.
         */
        onRowClick(row, columnIndex) {
            if (!row || row.kind !== 'link' || !row.hasChildren) {
                return;
            }

            const index = Number.isInteger(columnIndex) ? columnIndex : 0;
            const existing = this.columns[index + 1];

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
            this.hydrateVisibleIcons(nextColumn.rows);
            this.$nextTick(() => {
                this.scrollColumnsToEnd();
            });
        },

        /** Keep deep levels (4–5) visible via horizontal scroll. */
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

        /** Highlight the row that owns the next open column. */
        isRowActive(columnIndex, row) {
            const next = this.columns[columnIndex + 1];

            return Boolean(next && row?.kind === 'link' && next.sourceRowId === row.id);
        },
    },
};
