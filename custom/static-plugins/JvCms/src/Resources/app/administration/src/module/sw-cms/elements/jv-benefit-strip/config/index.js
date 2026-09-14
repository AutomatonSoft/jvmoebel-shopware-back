/**
 * Config panel for CMS element `jv-benefit-strip`.
 */
import template from './sw-cms-el-config-jv-benefit-strip.html.twig';
import './sw-cms-el-config-jv-benefit-strip.scss';

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
        this.initElementConfig('jv-benefit-strip');
        this.ensureItems();
    },

    methods: {
        onUpdate() {
            this.syncItemPositions();
            this.$emit('element-update', this.element);
        },

        syncItemPositions() {
            this.items.forEach((item, index) => {
                item.position = index;
            });
        },

        ensureItems() {
            if (!Array.isArray(this.element.config.items.value)) {
                this.element.config.items.value = [];
            }

            return this.element.config.items.value;
        },

        addItem() {
            this.items.push({
                id: '',
                title: '',
                description: '',
                position: this.items.length,
                iconMedia: null,
            });
            this.onUpdate();
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.onUpdate();
        },

        itemUploadTag(index) {
            return `cms-element-jv-benefit-strip-item-${this.element.id}-${index}`;
        },

        itemPreviewSource(item) {
            if (item?.icon?.id) {
                return item.icon;
            }

            return item.iconMedia;
        },

        async onItemUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const item = this.items[index];
            if (!item) {
                return;
            }

            item.iconMedia = mediaEntity.id;
            item.icon = mediaEntity;
            this.onUpdate();
        },

        onItemRemove(index) {
            const item = this.items[index];
            if (!item) {
                return;
            }

            item.iconMedia = null;
            item.icon = null;
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

            item.iconMedia = media.id;
            item.icon = media;
            this.onUpdate();
            this.onCloseItemMediaModal();
        },
    },
};
