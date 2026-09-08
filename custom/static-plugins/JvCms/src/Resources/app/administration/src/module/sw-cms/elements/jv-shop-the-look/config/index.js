/** Administration config for CMS element `jv-shop-the-look`. */
import template from './sw-cms-el-config-jv-shop-the-look.html.twig';
import './sw-cms-el-config-jv-shop-the-look.scss';

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
        items() {
            return this.ensureItems();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        imageUploadTag() {
            return `cms-element-jv-shop-the-look-image-${this.element.id}`;
        },

        imagePreviewSource() {
            if (this.element?.data?.image?.id) {
                return this.element.data.image;
            }

            return this.element.config.imageMedia.value;
        },
    },

    created() {
        this.initElementConfig('jv-shop-the-look');
        this.normalizeItems();
        this.ensureViewAll();
    },

    methods: {
        onUpdate() {
            this.syncItemPositions();
            this.$emit('element-update', this.element);
        },

        ensureItems() {
            if (!Array.isArray(this.element.config.items.value)) {
                this.element.config.items.value = [];
            }

            return this.element.config.items.value;
        },

        normalizeItems() {
            this.ensureItems().forEach((item) => {
                if (typeof item.id !== 'string') {
                    item.id = '';
                }
                if (!Object.prototype.hasOwnProperty.call(item, 'productId')) {
                    item.productId = null;
                }
                if (typeof item.name !== 'string') {
                    item.name = '';
                }
                if (typeof item.description !== 'string') {
                    item.description = '';
                }
                if (typeof item.url !== 'string') {
                    item.url = '';
                }
                if (!item.hotspot || typeof item.hotspot !== 'object' || Array.isArray(item.hotspot)) {
                    item.hotspot = { x: 50, y: 50 };
                }
                if (typeof item.hotspot.x !== 'number') {
                    item.hotspot.x = 50;
                }
                if (typeof item.hotspot.y !== 'number') {
                    item.hotspot.y = 50;
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

        syncItemPositions() {
            this.items.forEach((item, index) => {
                item.position = index;
            });
        },

        addItem() {
            this.items.push({
                id: Utils.createId(),
                productId: null,
                name: '',
                description: '',
                url: '',
                hotspot: { x: 50, y: 50 },
                position: this.items.length,
            });
            this.onUpdate();
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.onUpdate();
        },

        moveItem(index, offset) {
            const targetIndex = index + offset;
            if (targetIndex < 0 || targetIndex >= this.items.length) {
                return;
            }

            const [item] = this.items.splice(index, 1);
            this.items.splice(targetIndex, 0, item);
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
                this.element.data = { image: media };
                return;
            }

            this.element.data.image = media;
        },
    },
};
