import template from './sw-product-variants-configurator-selection.html.twig';
import './sw-product-variants-configurator-selection.scss';

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-product-variants-configurator-selection', {
    template,

    data() {
        return {
            jvImportGroups: [],
            jvImportRecommendedGroupIds: new Set(),
            jvImportSectionPages: {
                used: 1,
                recommended: 1,
                other: 1,
            },
            jvImportProductPropertyGroupIds: new Set(),
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
            const groups = this.jvImportGroups;
            const usedGroupIds = this.jvImportUsedGroupIds;
            const used = groups.filter((group) => usedGroupIds.has(group.id));
            const recommended = groups.filter((group) => !usedGroupIds.has(group.id)
                && this.jvImportRecommendedGroupIds.has(group.id));
            const other = groups.filter((group) => !usedGroupIds.has(group.id)
                && !this.jvImportRecommendedGroupIds.has(group.id));

            return [
                this.jvImportSection('used', this.$t('jv-import.variantGroups.used'), used),
                this.jvImportSection('recommended', this.$t('jv-import.variantGroups.recommended'), recommended),
                this.jvImportSection('other', this.$t('jv-import.variantGroups.other'), other),
            ];
        },

        jvImportUsedGroupIds() {
            const ids = new Set(this.jvImportProductPropertyGroupIds);

            this.options.forEach((setting) => {
                if (!setting.isDeleted && setting.option?.groupId) {
                    ids.add(setting.option.groupId);
                }
            });

            return ids;
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
            this.jvImportGroups = await this.jvImportLoadNonEmptyGroups();

            const productCriteria = new Criteria(1, 1);
            productCriteria.addAssociation('categories');
            productCriteria.addAssociation('properties');

            let product;
            try {
                product = await this.jvImportProductRepository.get(this.product.id, Shopware.Context.api, productCriteria);
            } catch (error) {
                this.jvImportNotifyClassificationFallback();

                return;
            }

            const categoryGroupIds = new Set();
            (product.categories ?? []).forEach((category) => {
                categoryGroupIds.add(category.id);
                if (category.parentId) {
                    categoryGroupIds.add(category.parentId);
                }
            });
            const productPropertyGroupIds = new Set();
            (product.properties ?? []).forEach((property) => {
                if (property.groupId) {
                    productPropertyGroupIds.add(property.groupId);
                }
            });
            this.jvImportProductPropertyGroupIds = productPropertyGroupIds;

            if (categoryGroupIds.size === 0) {
                return;
            }

            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('sourceCode', 'okb'));
            criteria.addFilter(Criteria.equalsAny('categoryId', [...categoryGroupIds]));
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addFilter(Criteria.equals('enabled', true));
            criteria.addFilter(Criteria.equals('storage', 'property'));

            try {
                const mappings = await this.jvImportSearchAll(this.jvImportCatalogCategoryAttributeRepository, criteria);
                const recommendedGroupIds = new Set();
                mappings.forEach((mapping) => {
                    if (mapping.propertyGroupId && mapping.featureRelevance?.includes('VARIATION_THEME')) {
                        recommendedGroupIds.add(mapping.propertyGroupId);
                    }
                });
                this.jvImportRecommendedGroupIds = recommendedGroupIds;
            } catch (error) {
                this.jvImportNotifyClassificationFallback();
            }
        },

        async jvImportLoadNonEmptyGroups() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('name', 'ASC', false));
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('options.id', null)]));
            criteria.addAssociation('options');
            const groups = await this.jvImportSearchAll(this.propertyGroupRepository, criteria);

            return groups.filter((group) => group.options?.length > 0);
        },

        async jvImportSearchAll(repository, criteria) {
            const firstPage = await repository.search(criteria, Shopware.Context.api);
            const limit = criteria.limit ?? firstPage.length ?? 25;
            const totalPages = Math.ceil((firstPage.total ?? firstPage.length) / limit);
            const pages = [firstPage];

            for (let page = 2; page <= totalPages; page++) {
                const nextCriteria = Criteria.fromCriteria(criteria).setPage(page).setLimit(limit);
                pages.push(await repository.search(nextCriteria, Shopware.Context.api));
            }

            return pages.flatMap((page) => page);
        },

        jvImportNotifyClassificationFallback() {
            this.createNotificationWarning({
                message: this.$t('jv-import.variantGroups.classificationUnavailable'),
            });
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

        jvImportSection(key, label, groups) {
            const page = this.jvImportSectionPages[key] ?? 1;
            const limit = 10;
            const start = (page - 1) * limit;

            return {
                key,
                label,
                groups,
                page,
                visibleGroups: groups.slice(start, start + limit),
            };
        },

        jvImportChangeSectionPage(key, pagination) {
            this.jvImportSectionPages = {
                ...this.jvImportSectionPages,
                [key]: pagination.page,
            };
        },

        showTree() {
            this.displaySearch = false;
            this.displayTree = true;
            this.groupPage = 1;
            this.optionPage = 1;
            this.groupOptions = [];
            this.groups = this.jvImportGroups;
            this.addOptionCount();
        },
    },
});
