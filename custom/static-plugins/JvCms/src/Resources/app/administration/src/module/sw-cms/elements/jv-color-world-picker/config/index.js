/** Administration config for CMS element `jv-color-world-picker`. */
import template from './sw-cms-el-config-jv-color-world-picker.html.twig';
import './sw-cms-el-config-jv-color-world-picker.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [Mixin.getByName('cms-element')],

    inject: ['repositoryFactory'],

    data() {
        return {
            activeColorMediaIndex: null,
            mediaModalIsOpen: false,
        };
    },

    computed: {
        colors() {
            return this.ensureColors();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    created() {
        this.initElementConfig('jv-color-world-picker');
        this.normalizeColors();
    },

    methods: {
        onUpdate() {
            this.syncColorPositions();
            this.$emit('element-update', this.element);
        },

        ensureColors() {
            if (!Array.isArray(this.element.config.colors.value)) {
                this.element.config.colors.value = [];
            }

            return this.element.config.colors.value;
        },

        normalizeColors() {
            this.ensureColors().forEach((color) => {
                if (typeof color.id !== 'string') {
                    color.id = '';
                }
                if (typeof color.name !== 'string') {
                    color.name = '';
                }
                if (typeof color.url !== 'string') {
                    color.url = '';
                }
                if (typeof color.hex !== 'string') {
                    color.hex = '';
                }
                if (!Object.prototype.hasOwnProperty.call(color, 'imageMedia')) {
                    color.imageMedia = null;
                }
            });
        },

        syncColorPositions() {
            this.colors.forEach((color, index) => {
                color.position = index;
            });
        },

        addColor() {
            this.colors.push({
                id: Utils.createId(),
                name: '',
                url: '',
                hex: '',
                imageMedia: null,
                position: this.colors.length,
            });
            this.onUpdate();
        },

        removeColor(index) {
            this.colors.splice(index, 1);
            this.onUpdate();
        },

        moveColor(index, offset) {
            const targetIndex = index + offset;
            if (targetIndex < 0 || targetIndex >= this.colors.length) {
                return;
            }

            const [color] = this.colors.splice(index, 1);
            this.colors.splice(targetIndex, 0, color);
            this.onUpdate();
        },

        colorImageUploadTag(index) {
            return `cms-element-jv-color-world-picker-color-${this.element.id}-${index}`;
        },

        colorImagePreviewSource(color) {
            return color.imageMedia;
        },

        onOpenColorMediaModal(index) {
            this.activeColorMediaIndex = index;
            this.mediaModalIsOpen = true;
        },

        onCloseColorMediaModal() {
            this.mediaModalIsOpen = false;
            this.activeColorMediaIndex = null;
        },

        async onColorImageUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            this.colors[index].imageMedia = mediaEntity.id;
            this.onUpdate();
        },

        onColorImageRemove(index) {
            this.colors[index].imageMedia = null;
            this.onUpdate();
        },

        onColorImageSelectionChanges(mediaEntities) {
            if (this.activeColorMediaIndex === null) {
                return;
            }

            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            this.colors[this.activeColorMediaIndex].imageMedia = media.id;
            this.onUpdate();
            this.onCloseColorMediaModal();
        },
    },
};
