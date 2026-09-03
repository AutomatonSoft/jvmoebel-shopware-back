/**
 * Canvas preview for CMS element `jv-product-grid`.
 */
import template from './sw-cms-el-jv-product-grid.html.twig';
import './sw-cms-el-jv-product-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        title() {
            return (this.element?.config?.title?.value || '').trim();
        },

        eyebrow() {
            return (this.element?.config?.eyebrow?.value || '').trim();
        },

        products() {
            const products = this.element?.config?.products?.value;

            return Array.isArray(products) ? products : [];
        },

        viewAllLabel() {
            return (this.element?.config?.viewAll?.value?.label || '').trim();
        },
    },

    created() {
        this.initElementConfig('jv-product-grid');
    },
};
