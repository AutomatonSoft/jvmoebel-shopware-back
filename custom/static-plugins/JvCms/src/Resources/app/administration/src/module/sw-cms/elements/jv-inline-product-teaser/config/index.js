import template from './sw-cms-el-config-jv-inline-product-teaser.html.twig';
import './sw-cms-el-config-jv-inline-product-teaser.scss';

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
    },

    created() {
        this.initElementConfig('jv-inline-product-teaser');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        uploadTag() {
            return `cms-element-jv-inline-product-teaser-${this.element.id}`;
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            this.element.config.imageMedia.value = mediaEntity.id;
            this.element.config.image.value = mediaEntity;
            this.onUpdate();
        },

        onImageRemove() {
            this.element.config.imageMedia.value = null;
            this.element.config.image.value = null;
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
            this.element.config.image.value = media;
            this.onUpdate();
            this.onCloseMediaModal();
        },
    },
};
