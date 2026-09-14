import template from './sw-cms-el-config-jv-app-download-promo.html.twig';
import './sw-cms-el-config-jv-app-download-promo.scss';

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

        qrImagePreviewSource() {
            return this.element.config.qrImage?.value || this.element.config.qrImageMedia.value;
        },
    },

    created() {
        this.initElementConfig('jv-app-download-promo');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        uploadTag() {
            return `cms-element-jv-app-download-promo-${this.element.id}`;
        },

        async onQrImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            this.element.config.qrImageMedia.value = mediaEntity.id;
            this.element.config.qrImage.value = mediaEntity;
            this.onUpdate();
        },

        onQrImageRemove() {
            this.element.config.qrImageMedia.value = null;
            this.element.config.qrImage.value = null;
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

            this.element.config.qrImageMedia.value = media.id;
            this.element.config.qrImage.value = media;
            this.onUpdate();
            this.onCloseMediaModal();
        },
    },
};
