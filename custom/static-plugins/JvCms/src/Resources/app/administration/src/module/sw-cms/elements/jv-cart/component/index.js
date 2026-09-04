import template from './sw-cms-el-jv-cart.html.twig';
import './sw-cms-el-jv-cart.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        headerLabel() {
            return this.element.config.headerTrigger.value.label || this.$t('cms.elements.jv-cart.component.headerLabelPlaceholder');
        },

        pageTitle() {
            return this.element.config.titleSingular.value || this.$t('cms.elements.jv-cart.component.pageTitlePlaceholder');
        },
    },

    created() {
        this.initElementConfig('jv-cart');
    },
};
