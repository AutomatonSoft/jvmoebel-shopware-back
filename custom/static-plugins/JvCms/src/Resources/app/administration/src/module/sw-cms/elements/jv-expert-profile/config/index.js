import template from './sw-cms-el-config-jv-expert-profile.html.twig';
import './sw-cms-el-config-jv-expert-profile.scss';

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
            return `cms-element-jv-expert-profile-image-${this.element.id}`;
        },

        imagePreviewSource() {
            if (this.element?.data?.imageMedia?.id) {
                return this.element.data.imageMedia;
            }

            return this.element.config.imageMedia.value;
        },
    },

    created() {
        this.initElementConfig('jv-expert-profile');
        this.ensureLinkShape();
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        ensureLinkShape() {
            if (!this.element.config.link.value || typeof this.element.config.link.value !== 'object') {
                this.element.config.link.value = { label: '', url: '' };
            }

            const link = this.element.config.link.value;
            if (typeof link.label !== 'string') {
                link.label = '';
            }
            if (typeof link.url !== 'string') {
                link.url = '';
            }
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            this.element.config.imageMedia.value = mediaEntity.id;
            this.onUpdate();
        },

        onImageRemove() {
            this.element.config.imageMedia.value = null;
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
            this.onUpdate();
            this.onCloseImageMediaModal();
        },
    },
};
