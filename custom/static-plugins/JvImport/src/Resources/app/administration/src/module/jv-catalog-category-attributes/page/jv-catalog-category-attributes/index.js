import template from './jv-catalog-category-attributes.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('jv-catalog-category-attributes', {
    template,

    inject: ['acl', 'repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            mappings: [],
            sourceCategories: [],
            isMappingLoading: false,
            savingMappingId: null,
            editingMapping: null,
            editableMapping: null,
        };
    },

    computed: {
        categoryId() {
            return this.$route.params.id;
        },

        mappingRepository() {
            return this.repositoryFactory.create('jv_catalog_category_attribute');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        mappingColumns() {
            return [
                { property: 'attributeName', label: this.$tc('jv-catalog-category-attributes.columns.attribute'), primary: true },
                { property: 'attributeType', label: this.$tc('jv-catalog-category-attributes.columns.type') },
                { property: 'featureRelevance', label: this.$tc('jv-catalog-category-attributes.columns.usage') },
                { property: 'active', label: this.$tc('jv-catalog-category-attributes.columns.available') },
                { property: 'enabled', label: this.$tc('jv-catalog-category-attributes.columns.import') },
                { property: 'storage', label: this.$tc('jv-catalog-category-attributes.columns.destination') },
                { property: 'target', label: this.$tc('jv-catalog-category-attributes.columns.target') },
                { property: 'actions', label: '' },
            ];
        },

        sourceCategoryColumns() {
            return [
                { property: 'name', label: this.$tc('jv-catalog-category-attributes.columns.category'), primary: true },
            ];
        },

        storageOptions() {
            return [
                { value: 'property', label: this.$tc('jv-catalog-category-attributes.storage.property') },
                { value: 'custom_field', label: this.$tc('jv-catalog-category-attributes.storage.customField') },
                { value: 'ignore', label: this.$tc('jv-catalog-category-attributes.storage.ignore') },
            ];
        },

        isSourceCategoryGroup() {
            return this.mappings.length > 0;
        },

        canEditMappings() {
            return this.acl.can('category.editor');
        },
    },

    watch: {
        categoryId: {
            immediate: true,
            handler() {
                this.load();
            },
        },
    },

    methods: {
        async load() {
            if (!this.categoryId) {
                return;
            }

            this.isMappingLoading = true;
            const mappingCriteria = new Criteria(1, 500);
            mappingCriteria.addFilter(Criteria.equals('categoryId', this.categoryId));
            mappingCriteria.addAssociation('propertyGroup');
            mappingCriteria.addSorting(Criteria.sort('attributeName', 'ASC'));

            const categoryCriteria = new Criteria(1, 500);
            categoryCriteria.addFilter(Criteria.equals('parentId', this.categoryId));
            categoryCriteria.addSorting(Criteria.sort('name', 'ASC'));

            try {
                const [mappings, sourceCategories] = await Promise.all([
                    this.mappingRepository.search(mappingCriteria, Shopware.Context.api),
                    this.categoryRepository.search(categoryCriteria, Shopware.Context.api),
                ]);
                this.mappings = [...mappings];
                this.sourceCategories = [...sourceCategories];
            } finally {
                this.isMappingLoading = false;
            }
        },

        async saveMapping(mapping) {
            if (!this.canEditMappings) {
                return;
            }

            this.savingMappingId = mapping.id;
            try {
                await this.mappingRepository.save(mapping, Shopware.Context.api);
                this.createNotificationSuccess({ message: this.$tc('jv-catalog-category-attributes.saved') });
                return true;
            } catch (error) {
                this.createNotificationError({ message: this.$tc('jv-catalog-category-attributes.saveError') });
                return false;
            } finally {
                this.savingMappingId = null;
            }
        },

        openMappingEditor(mapping) {
            this.editingMapping = mapping;
            this.editableMapping = {
                id: mapping.id,
                attributeName: mapping.attributeName,
                customFieldName: mapping.customFieldName,
                enabled: mapping.enabled,
                propertyGroupId: mapping.propertyGroupId,
                storage: mapping.storage,
            };
        },

        closeMappingEditor() {
            if (this.savingMappingId) {
                return;
            }

            this.editingMapping = null;
            this.editableMapping = null;
        },

        onEditorStorageChange(storage) {
            this.editableMapping.storage = storage;
            if (storage !== 'property') {
                this.editableMapping.propertyGroupId = null;
            }
            if (storage !== 'custom_field') {
                this.editableMapping.customFieldName = null;
            }
        },

        async saveEditedMapping() {
            if (!this.editingMapping || !this.editableMapping) {
                return;
            }

            Object.assign(this.editingMapping, {
                customFieldName: this.editableMapping.customFieldName || null,
                enabled: this.editableMapping.enabled,
                propertyGroupId: this.editableMapping.propertyGroupId || null,
                storage: this.editableMapping.storage,
            });

            const saved = await this.saveMapping(this.editingMapping);
            if (saved) {
                this.closeMappingEditor();
            }
        },

        storageLabel(storage) {
            return this.storageOptions.find((option) => option.value === storage)?.label ?? storage;
        },

        targetLabel(mapping) {
            if (mapping.storage === 'property') {
                return mapping.propertyGroup?.name ?? this.$tc('jv-catalog-category-attributes.target.noProperty');
            }
            if (mapping.storage === 'custom_field') {
                return mapping.customFieldName ?? this.$tc('jv-catalog-category-attributes.target.catalogJson');
            }

            return this.$tc('jv-catalog-category-attributes.target.notImported');
        },
    },
});
