import template from './sw-cms-el-config-jv-expert-tip.html.twig';
import './sw-cms-el-config-jv-expert-tip.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-expert-tip');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },
    },
};
