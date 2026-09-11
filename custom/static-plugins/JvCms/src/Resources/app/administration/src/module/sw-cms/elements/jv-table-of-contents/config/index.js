/** Administration config for CMS element `jv-table-of-contents`. */
import template from './sw-cms-el-config-jv-table-of-contents.html.twig';
import './sw-cms-el-config-jv-table-of-contents.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        items() {
            return this.ensureItems();
        },
    },

    created() {
        this.initElementConfig('jv-table-of-contents');
        this.normalizeItems();
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
                if (typeof item.label !== 'string') {
                    item.label = '';
                }
                if (typeof item.anchorId !== 'string') {
                    item.anchorId = '';
                }
            });
        },

        syncItemPositions() {
            this.items.forEach((item, index) => {
                item.position = index;
            });
        },

        addItem() {
            this.items.push({
                id: Utils.createId(),
                label: '',
                anchorId: '',
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
    },
};
