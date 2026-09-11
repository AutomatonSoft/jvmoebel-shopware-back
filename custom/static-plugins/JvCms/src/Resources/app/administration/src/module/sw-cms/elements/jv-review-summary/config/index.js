import template from './sw-cms-el-config-jv-review-summary.html.twig';
import './sw-cms-el-config-jv-review-summary.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-review-summary');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },
    },
};
