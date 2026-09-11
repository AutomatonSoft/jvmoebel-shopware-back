import template from './sw-cms-el-jv-look-scene.html.twig';
import './sw-cms-el-jv-look-scene.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        title() {
            return this.text(this.element?.config?.title?.value);
        },

        description() {
            return this.text(this.element?.config?.description?.value);
        },

        products() {
            return this.collection(this.element?.config?.products?.value);
        },
    },

    created() {
        this.initElementConfig('jv-look-scene');
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
