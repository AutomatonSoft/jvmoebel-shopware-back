import template from './sw-cms-el-config-jv-expert-quote.html.twig';
import './sw-cms-el-config-jv-expert-quote.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-expert-quote');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },
    },
};
