/** Administration config for CMS element `jv-article-hero`. */
import template from './sw-cms-el-config-jv-article-hero.html.twig';
import './sw-cms-el-config-jv-article-hero.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [Mixin.getByName('cms-element')],

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
            return `cms-element-jv-article-hero-image-${this.element.id}`;
        },

        imagePreviewSource() {
            if (this.element?.data?.imageMedia?.id) {
                return this.element.data.imageMedia;
            }

            return this.element.config.imageMedia.value;
        },
    },

    created() {
        this.initElementConfig('jv-article-hero');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
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
            this.onCloseImageMediaModal();
        },

        updateImageElementData(media = null) {
            if (!this.element.data) {
                this.element.data = { imageMedia: media };
                return;
            }

            this.element.data.imageMedia = media;
        },
    },
};
