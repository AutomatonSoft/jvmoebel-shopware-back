/**
 * Config panel for CMS element `jv-promo-banner`.
 */
import template from './sw-cms-el-config-jv-promo-banner.html.twig';
import './sw-cms-el-config-jv-promo-banner.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            mediaModalOpen: false,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        imagePreviewSource() {
            return this.element.config.image?.value || this.element.config.imageMedia.value;
        },

        contentPositionOptions() {
            return [
                { value: 'left', label: this.$t('cms.elements.jv-promo-banner.config.contentPosition.left') },
                { value: 'right', label: this.$t('cms.elements.jv-promo-banner.config.contentPosition.right') },
            ];
        },

        sizeOptions() {
            return [
                { value: 'small', label: this.$t('cms.elements.jv-promo-banner.config.link.size.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-promo-banner.config.link.size.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-promo-banner.config.link.size.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-promo-banner');
        this.ensureLinkShape();
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        ensureLinkShape() {
            if (!this.element.config.link.value || typeof this.element.config.link.value !== 'object') {
                this.element.config.link.value = { label: '', url: '', size: 'medium' };
            }

            const link = this.element.config.link.value;
            if (typeof link.label !== 'string') {
                link.label = '';
            }
            if (typeof link.url !== 'string') {
                link.url = '';
            }
            if (typeof link.size !== 'string' || !['small', 'medium', 'large'].includes(link.size)) {
                link.size = 'medium';
            }
        },

        uploadTag() {
            return `cms-element-jv-promo-banner-${this.element.id}`;
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            this.element.config.imageMedia.value = mediaEntity.id;
            this.element.config.image = { source: 'static', value: mediaEntity };
            this.onUpdate();
        },

        onImageRemove() {
            this.element.config.imageMedia.value = null;
            this.element.config.image = { source: 'static', value: null };
            this.onUpdate();
        },

        onOpenMediaModal() {
            this.mediaModalOpen = true;
        },

        onCloseMediaModal() {
            this.mediaModalOpen = false;
        },

        onMediaSelectionChanges(mediaEntities) {
            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            this.element.config.imageMedia.value = media.id;
            this.element.config.image = { source: 'static', value: media };
            this.onUpdate();
            this.onCloseMediaModal();
        },
    },
};
