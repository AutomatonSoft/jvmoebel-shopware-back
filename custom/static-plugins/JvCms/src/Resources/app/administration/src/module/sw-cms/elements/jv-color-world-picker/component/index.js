import template from './sw-cms-el-jv-color-world-picker.html.twig';
import './sw-cms-el-jv-color-world-picker.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        title() {
            return this.text(this.element?.config?.title?.value);
        },

        colors() {
            return this.collection(this.element?.config?.colors?.value);
        },
    },

    created() {
        this.initElementConfig('jv-color-world-picker');
    },

    methods: {
        collection(value) {
            if (Array.isArray(value)) {
                return value;
            }
            if (value && typeof value === 'object') {
                return Object.values(value);
            }

            return [];
        },

        text(value) {
            return typeof value === 'string' ? value.trim() : '';
        },
    },
};
