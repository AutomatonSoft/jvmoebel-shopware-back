import template from './sw-cms-el-config-jv-hero.html.twig';
import './sw-cms-el-config-jv-hero.scss';

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
            mediaModalIsOpen: false,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        imageUploadTag() {
            return `cms-element-jv-hero-image-${this.element.id}`;
        },

        imagePreviewSource() {
            if (this.element?.data?.imageMedia?.id) {
                return this.element.data.imageMedia;
            }

            return this.element.config.imageMedia.value;
        },

        sizeOptions() {
            return [
                { value: 'small', label: this.$t('cms.elements.jv-hero.config.link.size.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-hero.config.link.size.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-hero.config.link.size.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-hero');
        this.ensureLinkObjects();
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        ensureLinkObjects() {
            this.ensureLink('primaryLink');
            this.ensureLink('secondaryLink');
        },

        ensureLink(field) {
            const config = this.element?.config?.[field];
            if (!config) {
                return;
            }

            if (!config.value || typeof config.value !== 'object' || Array.isArray(config.value)) {
                config.value = {
                    label: '',
                    url: '',
                    size: 'medium',
                };
                return;
            }

            if (typeof config.value.label !== 'string') {
                config.value.label = '';
            }
            if (typeof config.value.url !== 'string') {
                config.value.url = '';
            }
            if (!['small', 'medium', 'large'].includes(config.value.size)) {
                config.value.size = 'medium';
            }
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);

            this.element.config.imageMedia.value = mediaEntity.id;
            this.element.config.imageMedia.source = 'static';
            this.updateImageElementData(mediaEntity);
            this.onUpdate();
        },

        onImageRemove() {
            this.element.config.imageMedia.value = null;
            this.updateImageElementData();
            this.onUpdate();
        },

        onOpenImageMediaModal() {
            this.mediaModalIsOpen = true;
        },

        onCloseImageMediaModal() {
            this.mediaModalIsOpen = false;
        },

        onImageSelectionChanges(mediaEntities) {
            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            this.element.config.imageMedia.value = media.id;
            this.element.config.imageMedia.source = 'static';
            this.updateImageElementData(media);
            this.onUpdate();
        },

        /** Keep element.data in sync so the canvas can show the image before page reload. */
        updateImageElementData(media = null) {
            const mediaId = media === null ? null : media.id;

            if (!this.element.data) {
                this.element.data = { imageMediaId: mediaId, imageMedia: media };
                return;
            }

            this.element.data.imageMediaId = mediaId;
            this.element.data.imageMedia = media;
        },
    },
};
