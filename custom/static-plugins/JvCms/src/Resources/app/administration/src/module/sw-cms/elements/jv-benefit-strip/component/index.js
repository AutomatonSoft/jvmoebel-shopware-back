import template from './sw-cms-el-jv-benefit-strip.html.twig';
import './sw-cms-el-jv-benefit-strip.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        items() {
            const items = this.element?.config?.items?.value;

            return Array.isArray(items) ? items : [];
        },
    },

    created() {
        this.initElementConfig('jv-benefit-strip');
    },
};
