import template from './jv-catalog-category-attributes-overview.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('jv-catalog-category-attributes-overview', {
    template,

    computed: {
        categoryCriteria() {
            return new Criteria(1, 25);
        },
    },

    data() {
        return {
            categoryId: null,
        };
    },
});
