import template from './jv-catalog-attribute-mapping-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('jv-catalog-attribute-mapping-list', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Mixin.getByName('listing')],

    data() {
        return { mappings: null, total: 0, isLoading: false };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('jv_catalog_category_attribute');
        },
        columns() {
            return [
                { property: 'sourceCode', label: 'jv-catalog-attribute-mapping.columns.source', primary: true },
                { property: 'categoryGroupId', label: 'jv-catalog-attribute-mapping.columns.group' },
                { property: 'attributeName', label: 'jv-catalog-attribute-mapping.columns.attribute' },
                { property: 'attributeType', label: 'jv-catalog-attribute-mapping.columns.type' },
                { property: 'featureRelevance', label: 'jv-catalog-attribute-mapping.columns.relevance' },
                { property: 'active', label: 'jv-catalog-attribute-mapping.columns.present' },
                { property: 'enabled', label: 'jv-catalog-attribute-mapping.columns.enabled', inlineEdit: 'boolean' },
                {
                    property: 'storage',
                    label: 'jv-catalog-attribute-mapping.columns.storage',
                    inlineEdit: 'single-select',
                    inlineEditConfig: {
                        options: [
                            { value: 'property', label: 'Property / option' },
                            { value: 'custom_field', label: 'Catalog JSON field' },
                            { value: 'ignore', label: 'Ignore' },
                        ],
                    },
                },
                { property: 'propertyGroupId', label: 'jv-catalog-attribute-mapping.columns.propertyGroup', inlineEdit: 'string' },
            ];
        },
    },

    created() {
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('sourceCode', 'ASC'));
            criteria.addSorting(Criteria.sort('categoryGroupId', 'ASC'));
            criteria.addSorting(Criteria.sort('attributeName', 'ASC'));

            return this.repository.search(criteria, Shopware.Context.api).then((result) => {
                this.total = result.total;
                this.mappings = result;
                this.isLoading = false;
            });
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            return this.repository.save(item, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.createNotificationSuccess({ message: this.$tc('jv-catalog-attribute-mapping.saved') });
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({ message: this.$tc('jv-catalog-attribute-mapping.saveError') });
            });
        },
    },
});
