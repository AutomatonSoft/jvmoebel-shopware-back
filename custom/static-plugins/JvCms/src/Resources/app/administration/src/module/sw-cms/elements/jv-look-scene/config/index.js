/** Administration config for CMS element `jv-look-scene`. */
import template from './sw-cms-el-config-jv-look-scene.html.twig';
import './sw-cms-el-config-jv-look-scene.scss';

const { Mixin, Utils } = Shopware;

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
        products() {
            return this.ensureProducts();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        imageUploadTag() {
            return `cms-element-jv-look-scene-image-${this.element.id}`;
        },

        imagePreviewSource() {
            if (this.element?.data?.imageMedia?.id) {
                return this.element.data.imageMedia;
            }

            return this.element.config.imageMedia.value;
        },
    },

    created() {
        this.initElementConfig('jv-look-scene');
        this.normalizeProducts();
        this.ensureViewAll();
    },

    methods: {
        onUpdate() {
            this.syncProductPositions();
            this.$emit('element-update', this.element);
        },

        ensureProducts() {
            if (!Array.isArray(this.element.config.products.value)) {
                this.element.config.products.value = [];
            }

            return this.element.config.products.value;
        },

        normalizeProducts() {
            this.ensureProducts().forEach((product) => {
                if (typeof product.id !== 'string') {
                    product.id = '';
                }
                if (!Object.prototype.hasOwnProperty.call(product, 'productId')) {
                    product.productId = null;
                }
                if (typeof product.name !== 'string') {
                    product.name = '';
                }
                if (typeof product.url !== 'string') {
                    product.url = '';
                }
            });
        },

        ensureViewAll() {
            const viewAll = this.element.config.viewAll.value;
            if (!viewAll || typeof viewAll !== 'object' || Array.isArray(viewAll)) {
                this.element.config.viewAll.value = { label: '', url: '' };
                return;
            }

            if (typeof viewAll.label !== 'string') {
                viewAll.label = '';
            }
            if (typeof viewAll.url !== 'string') {
                viewAll.url = '';
            }
        },

        syncProductPositions() {
            this.products.forEach((product, index) => {
                product.position = index;
            });
        },

        addProduct() {
            this.products.push({
                id: Utils.createId(),
                productId: null,
                name: '',
                url: '',
                position: this.products.length,
            });
            this.onUpdate();
        },

        removeProduct(index) {
            this.products.splice(index, 1);
            this.onUpdate();
        },

        moveProduct(index, offset) {
            const targetIndex = index + offset;
            if (targetIndex < 0 || targetIndex >= this.products.length) {
                return;
            }

            const [product] = this.products.splice(index, 1);
            this.products.splice(targetIndex, 0, product);
            this.onUpdate();
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
