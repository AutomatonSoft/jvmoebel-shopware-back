/** Administration config for CMS element `jv-social-block`. */
import template from './sw-cms-el-config-jv-social-block.html.twig';
import './sw-cms-el-config-jv-social-block.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('jv-cms-validation'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            mediaModalIndex: null,
        };
    },

    computed: {
        items() {
            return this.ensureItems();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    created() {
        this.initElementConfig('jv-social-block');
        this.normalizeConfig();
    },

    methods: {
        onUpdate() {
            this.syncItemPositions();
            this.ensureUniqueItemIds();
            this.$emit('element-update', this.element);
        },

        normalizeConfig() {
            if (typeof this.element.config.title.value !== 'string') {
                this.element.config.title.value = '';
            }

            const items = this.ensureItems();
            items.forEach((item, index) => {
                this.ensureItemShape(item, index);
            });
            items.sort((first, second) => first.position - second.position);

            this.syncItemPositions();
            this.ensureUniqueItemIds();
        },

        ensureItems() {
            const value = this.element.config.items.value;
            if (!Array.isArray(value)) {
                this.element.config.items.value = this.objectValues(value);
            }

            this.element.config.items.value.forEach((item, index) => {
                if (!item || typeof item !== 'object' || Array.isArray(item)) {
                    this.element.config.items.value[index] = this.emptyItem(index);
                }
            });

            return this.element.config.items.value;
        },

        ensureItemShape(item, index) {
            if (typeof item.id !== 'string') {
                item.id = '';
            }
            if (typeof item.name !== 'string') {
                item.name = '';
            }
            if (typeof item.url !== 'string') {
                item.url = '';
            }
            if (!Object.prototype.hasOwnProperty.call(item, 'imageMedia')) {
                item.imageMedia = null;
            }
            if (typeof item.position !== 'number' || !Number.isFinite(item.position)) {
                item.position = index;
            }
        },

        ensureUniqueItemIds() {
            const seenIds = new Set();
            this.items.forEach((item) => {
                let id = typeof item.id === 'string' ? item.id.trim() : '';
                if (!id || seenIds.has(id)) {
                    id = Utils.createId();
                    item.id = id;
                }

                seenIds.add(id);
            });
        },

        objectValues(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return [];
            }

            return Object.values(value);
        },

        emptyItem(position) {
            return {
                id: Utils.createId(),
                position,
                imageMedia: null,
                name: '',
                url: '',
            };
        },

        syncItemPositions() {
            this.items.forEach((item, index) => {
                item.position = index;
            });
        },

        addItem() {
            this.items.push(this.emptyItem(this.items.length));
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

        itemUploadTag(index) {
            return `cms-element-jv-social-block-item-${this.element.id}-${index}`;
        },

        itemPreviewSource(item) {
            if (item?.image?.id) {
                return item.image;
            }

            return item.imageMedia;
        },

        async onItemUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const item = this.items[index];
            if (!item) {
                return;
            }

            item.imageMedia = mediaEntity.id;
            item.image = mediaEntity;
            this.onUpdate();
        },

        onItemImageRemove(index) {
            const item = this.items[index];
            if (!item) {
                return;
            }

            item.imageMedia = null;
            item.image = null;
            this.onUpdate();
        },

        onOpenItemMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseItemMediaModal() {
            this.mediaModalIndex = null;
        },

        onItemSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const item = this.items[index];
            if (!media || !item) {
                return;
            }

            item.imageMedia = media.id;
            item.image = media;
            this.onUpdate();
            this.onCloseItemMediaModal();
        },
    },
};
