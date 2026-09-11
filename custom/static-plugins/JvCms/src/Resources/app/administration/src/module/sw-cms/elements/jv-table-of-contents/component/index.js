import template from './sw-cms-el-jv-table-of-contents.html.twig';
import './sw-cms-el-jv-table-of-contents.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        title() {
            return this.text(this.element?.config?.title?.value);
        },

        items() {
            return this.collection(this.element?.config?.items?.value);
        },
    },

    created() {
        this.initElementConfig('jv-table-of-contents');
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
