import template from './jv-catalog-category-attributes.html.twig';
import {
    connectionForProductDetails,
    connectionForProperty,
    disconnectedConnection,
    isConnected as isCatalogAttributeConnected,
} from './connection-state.mjs';

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
                { property: 'connection', label: this.$tc('jv-catalog-category-attributes.columns.connection') },
            ];
        },

        sourceCategoryColumns() {
            return [
                { property: 'name', label: this.$tc('jv-catalog-category-attributes.columns.category'), primary: true },
            ];
        },

        storageOptions() {
            return [
                { value: 'property', label: this.$tc('jv-catalog-category-attributes.connection.property') },
                { value: 'custom_field', label: this.$tc('jv-catalog-category-attributes.connection.productDetails') },
            ];
        },

        editorTitle() {
            if (!this.editableMapping) {
                return '';
            }

            return this.$tc('jv-catalog-category-attributes.editor.title', 0, { attribute: this.editableMapping.attributeName });
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
            const connection = 'property' === storage
                ? connectionForProperty(this.editableMapping.propertyGroupId)
                : connectionForProductDetails();

            Object.assign(this.editableMapping, connection);
        },

        async saveEditedMapping() {
            if (!this.editingMapping || !this.editableMapping) {
                return;
            }

            const connection = 'property' === this.editableMapping.storage
                ? connectionForProperty(this.editableMapping.propertyGroupId)
                : connectionForProductDetails();

            Object.assign(this.editingMapping, connection);

            const saved = await this.saveMapping(this.editingMapping);
            if (saved) {
                this.closeMappingEditor();
            }
        },

        async disconnectMapping(mapping) {
            const currentConnection = {
                customFieldName: mapping.customFieldName,
                enabled: mapping.enabled,
                propertyGroupId: mapping.propertyGroupId,
                storage: mapping.storage,
            };

            Object.assign(mapping, disconnectedConnection());

            const saved = await this.saveMapping(mapping);
            if (!saved) {
                Object.assign(mapping, currentConnection);
            }
        },

        isConnected(mapping) {
            return isCatalogAttributeConnected(mapping);
        },

        connectionLabel(mapping) {
            if (!this.isConnected(mapping)) {
                return this.$tc('jv-catalog-category-attributes.connection.notConnected');
            }

            if ('property' === mapping.storage) {
                return mapping.propertyGroup?.name ?? this.$tc('jv-catalog-category-attributes.target.noProperty');
            }

            return this.$tc('jv-catalog-category-attributes.connection.productDetails');
        },
    },
});
