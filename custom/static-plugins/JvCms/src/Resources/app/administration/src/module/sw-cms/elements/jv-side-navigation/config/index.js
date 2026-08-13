/**
 * Sidebar config UI for CMS element `jv-side-navigation`.
 *
 * Edits `element.config` (logo, tabs, sections, footer) and emits `element-update`
 * so Shopping Experiences persists changes. Canvas preview reads the same config.
 *
 * Intentionally no legacy-seed cleanup: editors recreate old test navigations manually.
 */
import template from './sw-cms-el-config-jv-side-navigation.html.twig';
import './sw-cms-el-config-jv-side-navigation.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        // Provides `this.element`, `initElementConfig()`, CMS page context, etc.
        Mixin.getByName('cms-element'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            // Type chosen in the "Add section" select before clicking add.
            pendingSectionType: 'category-tree',
            mediaModalIsOpen: false,
        };
    },

    computed: {
        tabs() {
            return this.ensureTabs();
        },

        footerItems() {
            return this.ensureFooterItems();
        },

        defaultTabOptions() {
            return this.tabs.map((tab) => ({
                value: tab.id,
                label: tab.label || tab.id,
            }));
        },

        sectionTypeOptions() {
            return [
                {
                    value: 'category-tree',
                    label: this.$t('cms.elements.jv-side-navigation.config.sections.types.categoryTree'),
                },
                {
                    value: 'manual-links',
                    label: this.$t('cms.elements.jv-side-navigation.config.sections.types.manualLinks'),
                },
                {
                    value: 'divider',
                    label: this.$t('cms.elements.jv-side-navigation.config.sections.types.divider'),
                },
                {
                    value: 'promo',
                    label: this.$t('cms.elements.jv-side-navigation.config.sections.types.promo'),
                },
            ];
        },

        maxDepthOptions() {
            return [1, 2, 3, 4, 5].map((value) => ({
                value,
                label: String(value),
            }));
        },

        manualStyleOptions() {
            return [
                {
                    value: 'default',
                    label: this.$t('cms.elements.jv-side-navigation.config.sections.manualLinks.style.default'),
                },
                {
                    value: 'uppercase',
                    label: this.$t('cms.elements.jv-side-navigation.config.sections.manualLinks.style.uppercase'),
                },
            ];
        },

        /**
         * Named icon keys for the Next.js icon set (not media uploads).
         * Empty value = no icon. Manual-link images use iconMediaId instead.
         */
        footerIconOptions() {
            return [
                { value: '', label: this.$t('cms.elements.jv-side-navigation.config.footer.icon.none') },
                { value: 'login', label: 'login' },
                { value: 'user', label: 'user' },
                { value: 'orders', label: 'orders' },
                { value: 'returns', label: 'returns' },
                { value: 'help', label: 'help' },
                { value: 'grid', label: 'grid' },
                { value: 'recent', label: 'recent' },
            ];
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        logoUploadTag() {
            return `cms-element-jv-side-navigation-logo-${this.element.id}`;
        },

        logoPreviewSource() {
            if (this.element?.data?.logo?.id) {
                return this.element.data.logo;
            }

            return this.element.config.logoMedia.value;
        },
    },

    created() {
        this.initElementConfig('jv-side-navigation');
        this.ensureTabs();
        this.ensureFooterItems();
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        async onLogoUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);

            this.element.config.logoMedia.value = mediaEntity.id;
            this.element.config.logoMedia.source = 'static';
            this.updateLogoElementData(mediaEntity);
            this.onUpdate();
        },

        onLogoRemove() {
            this.element.config.logoMedia.value = null;
            this.updateLogoElementData();
            this.onUpdate();
        },

        onOpenLogoMediaModal() {
            this.mediaModalIsOpen = true;
        },

        onCloseLogoMediaModal() {
            this.mediaModalIsOpen = false;
        },

        onLogoSelectionChanges(mediaEntities) {
            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            this.element.config.logoMedia.value = media.id;
            this.element.config.logoMedia.source = 'static';
            this.updateLogoElementData(media);
            this.onUpdate();
        },

        /** Keep element.data.logo in sync so the canvas preview can show the image immediately. */
        updateLogoElementData(media = null) {
            const mediaId = media === null ? null : media.id;

            if (!this.element.data) {
                this.element.data = { logoMediaId: mediaId, logo: media };
                return;
            }

            this.element.data.logoMediaId = mediaId;
            this.element.data.logo = media;
        },

        /** Guarantee tabs.value is an array and each tab has sections[]. */
        ensureTabs() {
            if (!Array.isArray(this.element.config.tabs.value)) {
                this.element.config.tabs.value = [];
            }

            this.element.config.tabs.value.forEach((tab) => {
                this.ensureSections(tab);
            });

            return this.element.config.tabs.value;
        },

        /** Guarantee footer.value = { items: [] }. */
        ensureFooterItems() {
            if (!this.element.config.footer.value || typeof this.element.config.footer.value !== 'object') {
                this.element.config.footer.value = { items: [] };
            }

            if (!Array.isArray(this.element.config.footer.value.items)) {
                this.element.config.footer.value.items = [];
            }

            return this.element.config.footer.value.items;
        },

        ensureSections(tab) {
            if (!Array.isArray(tab.sections)) {
                tab.sections = [];
            }

            return tab.sections;
        },

        /** Factory for a new section object matching the platform SPEC shape. */
        createSection(type) {
            const id = Utils.createId();

            switch (type) {
                case 'category-tree':
                    return {
                        id,
                        type: 'category-tree',
                        rootCategoryId: null,
                        maxDepth: 3,
                        showIcons: true,
                        includeRootAsAllLink: false,
                        allLinkLabel: '',
                    };
                case 'manual-links':
                    return {
                        id,
                        type: 'manual-links',
                        style: 'default',
                        items: [],
                    };
                case 'divider':
                    return {
                        id,
                        type: 'divider',
                    };
                case 'promo':
                    return {
                        id,
                        type: 'promo',
                        mediaId: null,
                        title: '',
                        url: '',
                        alt: '',
                    };
                default:
                    return null;
            }
        },

        addTab() {
            this.tabs.push({
                id: Utils.createId(),
                label: '',
                sections: [],
            });
            this.onUpdate();
        },

        removeTab(index) {
            const removed = this.tabs.splice(index, 1)[0];
            // Keep defaultTabId pointing at a remaining tab (or clear when none left).
            if (removed && this.element.config.defaultTabId.value === removed.id && this.tabs.length > 0) {
                this.element.config.defaultTabId.value = this.tabs[0].id;
            }
            if (this.tabs.length === 0) {
                this.element.config.defaultTabId.value = '';
            }
            this.onUpdate();
        },

        addSection(tabIndex) {
            const tab = this.tabs[tabIndex];
            const section = this.createSection(this.pendingSectionType);
            if (!section) {
                return;
            }

            this.ensureSections(tab).push(section);
            this.onUpdate();
        },

        removeSection(tabIndex, sectionIndex) {
            this.tabs[tabIndex].sections.splice(sectionIndex, 1);
            this.onUpdate();
        },

        addManualLinkItem(section) {
            if (!Array.isArray(section.items)) {
                section.items = [];
            }

            section.items.push({
                id: Utils.createId(),
                label: '',
                url: '',
                openInNewTab: false,
                iconMediaId: null,
                children: [],
            });
            this.onUpdate();
        },

        removeManualLinkItem(section, itemIndex) {
            section.items.splice(itemIndex, 1);
            this.onUpdate();
        },

        addFooterItem() {
            this.footerItems.push({
                id: Utils.createId(),
                label: '',
                href: '',
                // Empty = "No icon"; named keys map to the Next.js icon set.
                icon: '',
                visibility: 'always',
            });
            this.onUpdate();
        },

        removeFooterItem(index) {
            this.footerItems.splice(index, 1);
            this.onUpdate();
        },
    },
};
