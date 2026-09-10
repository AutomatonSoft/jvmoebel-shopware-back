/** Administration config for CMS element `jv-faq`. */
import template from './sw-cms-el-config-jv-faq.html.twig';
import './sw-cms-el-config-jv-faq.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('jv-cms-validation'),
    ],

    computed: {
        items() {
            return this.ensureItems();
        },
    },

    created() {
        this.initElementConfig('jv-faq');
        this.normalizeConfig();
    },

    methods: {
        onUpdate() {
            this.syncItemPositions();
            this.$emit('element-update', this.element);
        },

        normalizeConfig() {
            ['title', 'eyebrow', 'description'].forEach((field) => {
                if (typeof this.element.config[field].value !== 'string') {
                    this.element.config[field].value = '';
                }
            });

            const items = this.ensureItems();
            items.forEach((item, index) => {
                this.ensureItemShape(item, index);
            });
            items.sort((first, second) => first.position - second.position);

            this.syncItemPositions();
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
            if (typeof item.question !== 'string') {
                item.question = '';
            }
            if (typeof item.answer !== 'string') {
                item.answer = '';
            }
            if (typeof item.position !== 'number' || !Number.isFinite(item.position)) {
                item.position = index;
            }
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
                question: '',
                answer: '',
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
    },
};
