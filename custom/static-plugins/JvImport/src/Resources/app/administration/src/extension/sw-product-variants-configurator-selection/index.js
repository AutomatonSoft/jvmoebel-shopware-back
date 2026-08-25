import template from './sw-product-variants-configurator-selection.html.twig';
import './sw-product-variants-configurator-selection.scss';

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-product-variants-configurator-selection', {
    template,

    data() {
        return {
            jvImportAllowedGroupIds: null,
            jvImportRecommendedGroupIds: new Set(),
            jvImportUsedGroupIds: new Set(),
        };
    },

    computed: {
        jvImportCatalogCategoryAttributeRepository() {
            return this.repositoryFactory.create('jv_catalog_category_attribute');
        },

        jvImportProductRepository() {
            return this.repositoryFactory.create('product');
        },

        jvImportSections() {
            const groups = this.groups.filter((group) => this.jvImportAllowedGroupIds?.includes(group.id));
            const used = groups.filter((group) => this.jvImportUsedGroupIds.has(group.id));
            const recommended = groups.filter((group) => !this.jvImportUsedGroupIds.has(group.id)
                && this.jvImportRecommendedGroupIds.has(group.id));
            const other = groups.filter((group) => !this.jvImportUsedGroupIds.has(group.id)
                && !this.jvImportRecommendedGroupIds.has(group.id));

            return [
                { key: 'used', label: this.$t('jv-import.variantGroups.used'), groups: used },
                { key: 'recommended', label: this.$t('jv-import.variantGroups.recommended'), groups: recommended },
                { key: 'other', label: this.$t('jv-import.variantGroups.other'), groups: other },
            ];
        },

        propertyGroupCriteria() {
            const criteria = this.$super('propertyGroupCriteria');
            criteria.addAssociation('options');
            criteria.setLimit(500);
            this.jvImportApplyGroupFilter(criteria, 'id');

            return criteria;
        },

        propertyGroupOptionCriteria() {
            const criteria = this.$super('propertyGroupOptionCriteria');
            this.jvImportApplyGroupFilter(criteria, 'groupId');

            return criteria;
        },
    },

    methods: {
        async createdComponent() {
            await this.jvImportLoadGroupSections();

            if (this.collapsible) {
                document.addEventListener('click', this.closeOnClickOutside);
                document.addEventListener('keyup', this.closeOnClickOutside);

                return;
            }

            this.showTree();
        },

        async jvImportLoadGroupSections() {
            const productCriteria = new Criteria(1, 1);
            productCriteria.addAssociation('categories');

            const product = await this.jvImportProductRepository.get(this.product.id, Shopware.Context.api, productCriteria);
            const categoryGroupIds = new Set();
            product.categories.forEach((category) => {
                categoryGroupIds.add(category.parentId ?? category.id);
            });

            const usedGroupIds = new Set();
            this.options.forEach((setting) => {
                if (setting.option?.groupId) {
                    usedGroupIds.add(setting.option.groupId);
                }
            });

            if (categoryGroupIds.size === 0) {
                this.jvImportAllowedGroupIds = [...usedGroupIds];
                this.jvImportUsedGroupIds = usedGroupIds;

                return;
            }

            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('sourceCode', 'okb'));
            criteria.addFilter(Criteria.equalsAny('categoryId', [...categoryGroupIds]));
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addFilter(Criteria.equals('enabled', true));
            criteria.addFilter(Criteria.equals('storage', 'property'));

            const mappings = await this.jvImportCatalogCategoryAttributeRepository.search(criteria, Shopware.Context.api);
            const mappedGroupIds = new Set();
            const recommendedGroupIds = new Set();
            mappings.forEach((mapping) => {
                if (!mapping.propertyGroupId) {
                    return;
                }

                mappedGroupIds.add(mapping.propertyGroupId);
                if (mapping.featureRelevance?.includes('VARIATION_THEME')) {
                    recommendedGroupIds.add(mapping.propertyGroupId);
                }
            });

            const groups = await this.jvImportLoadNonEmptyGroups([...mappedGroupIds]);
            this.jvImportAllowedGroupIds = [...new Set([...usedGroupIds, ...groups.map((group) => group.id)])];
            this.jvImportRecommendedGroupIds = recommendedGroupIds;
            this.jvImportUsedGroupIds = usedGroupIds;
        },

        async jvImportLoadNonEmptyGroups(groupIds) {
            if (groupIds.length === 0) {
                return [];
            }

            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equalsAny('id', groupIds));
            criteria.addAssociation('options');

            const groups = await this.propertyGroupRepository.search(criteria, Shopware.Context.api);

            return groups.filter((group) => group.options?.length > 0);
        },

        jvImportApplyGroupFilter(criteria, field) {
            if (this.jvImportAllowedGroupIds === null) {
                return;
            }

            if (this.jvImportAllowedGroupIds.length === 0) {
                criteria.addFilter(Criteria.equals(field, '00000000000000000000000000000000'));

                return;
            }

            criteria.addFilter(Criteria.equalsAny(field, this.jvImportAllowedGroupIds));
        },

        selectGroup(group) {
            if (!group) {
                this.groupOptions = [];

                return;
            }

            this.currentGroup = group;
            this.optionPage = 1;
            this.loadOptions();
        },

        async loadGroups() {
            await this.$super('loadGroups');
            this.groups = this.groups.filter((group) => group.options?.length > 0);
        },
    },
});
