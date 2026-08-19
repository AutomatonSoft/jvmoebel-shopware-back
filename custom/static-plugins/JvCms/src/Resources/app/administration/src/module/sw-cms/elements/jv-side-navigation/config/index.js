/**
 * Sidebar config for CMS element `jv-side-navigation`.
 * Logo, search placeholder, root category, showIcons, footer. No tabs.
 */
import template from './sw-cms-el-config-jv-side-navigation.html.twig';
import './sw-cms-el-config-jv-side-navigation.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            mediaModalIsOpen: false,
        };
    },

    computed: {
        footerItems() {
            return this.ensureFooterItems();
        },

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
        this.dropLegacyTabFields();
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

        /** Keep element.data.logo in sync so the canvas can show the image before page reload. */
        updateLogoElementData(media = null) {
            const mediaId = media === null ? null : media.id;

            if (!this.element.data) {
                this.element.data = { logoMediaId: mediaId, logo: media };
                return;
            }

            this.element.data.logoMediaId = mediaId;
            this.element.data.logo = media;
        },

        /**
         * Strip tabbed-contract fields from layouts saved before this change.
         * Resolver already ignores them; this keeps persisted JSON clean after the next save.
         */
        dropLegacyTabFields() {
            if (!this.element?.config) {
                return;
            }

            let changed = false;

            if (this.element.config.tabs) {
                delete this.element.config.tabs;
                changed = true;
            }

            if (this.element.config.defaultTabId) {
                delete this.element.config.defaultTabId;
                changed = true;
            }

            if (changed) {
                this.onUpdate();
            }
        },

        ensureFooterItems() {
            if (!this.element.config.footer.value || typeof this.element.config.footer.value !== 'object') {
                this.element.config.footer.value = { items: [] };
            }

            if (!Array.isArray(this.element.config.footer.value.items)) {
                this.element.config.footer.value.items = [];
            }

            return this.element.config.footer.value.items;
        },

        addFooterItem() {
            this.footerItems.push({
                id: Utils.createId(),
                label: '',
                href: '',
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
