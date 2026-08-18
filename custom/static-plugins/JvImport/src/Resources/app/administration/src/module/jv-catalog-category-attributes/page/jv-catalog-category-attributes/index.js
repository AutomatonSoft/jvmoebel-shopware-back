import template from './jv-catalog-category-attributes.html.twig';
import {
    availableAttributes,
    connectionForProductDetails,
    connectionForProperty,
    disconnectedConnection,
    isConnected as isCatalogAttributeConnected,
    usedAttributes,
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
            isMappingLoading: false,
            savingMappingId: null,
            editingMapping: null,
            editableMapping: null,
            isAddingMapping: false,
        };
    },

    computed: {
        categoryId() {
            return this.$route.params.id;
        },

        mappingRepository() {
            return this.repositoryFactory.create('jv_catalog_category_attribute');
        },

        mappingColumns() {
            return [
                { property: 'attributeName', label: this.$tc('jv-catalog-category-attributes.columns.attribute'), primary: true },
                { property: 'usage', label: this.$tc('jv-catalog-category-attributes.columns.usage') },
                { property: 'target', label: this.$tc('jv-catalog-category-attributes.columns.target') },
            ];
        },

        storageOptions() {
            return [
                { value: 'property', label: this.$tc('jv-catalog-category-attributes.usage.property') },
                { value: 'custom_field', label: this.$tc('jv-catalog-category-attributes.usage.productInformation') },
            ];
        },

        usedMappings() {
            return usedAttributes(this.mappings);
        },

        availableMappings() {
            return availableAttributes(this.mappings);
        },

        sourceAttributeOptions() {
            return this.availableMappings.map((mapping) => ({
                value: mapping.id,
                label: mapping.attributeName,
            }));
        },

        editorTitle() {
            if (!this.editableMapping) {
                return '';
            }

            if (this.isAddingMapping) {
                return this.$tc('jv-catalog-category-attributes.editor.addTitle');
            }

            return this.$tc('jv-catalog-category-attributes.editor.editTitle', 0, { attribute: this.editableMapping.attributeName });
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

            try {
                const mappings = await this.mappingRepository.search(mappingCriteria, Shopware.Context.api);
                this.mappings = [...mappings];
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
            this.isAddingMapping = false;
            this.editableMapping = {
                id: mapping.id,
                attributeName: mapping.attributeName,
                customFieldName: mapping.customFieldName,
                enabled: mapping.enabled,
                propertyGroupId: mapping.propertyGroupId,
                storage: mapping.storage,
            };
        },

        openAttributeCreator() {
            const [mapping] = this.availableMappings;
            if (!mapping) {
                return;
            }

            this.isAddingMapping = true;
            this.editingMapping = null;
            this.selectSourceAttribute(mapping.id);
        },

        selectSourceAttribute(mappingId) {
            const mapping = this.availableMappings.find((candidate) => candidate.id === mappingId);
            if (!mapping) {
                return;
            }

            this.editableMapping = {
                id: mapping.id,
                attributeName: mapping.attributeName,
                ...connectionForProperty(null),
            };
        },

        closeMappingEditor() {
            if (this.savingMappingId) {
                return;
            }

            this.editingMapping = null;
            this.editableMapping = null;
            this.isAddingMapping = false;
        },

        onEditorStorageChange(storage) {
            const connection = 'property' === storage
                ? connectionForProperty(this.editableMapping.propertyGroupId)
                : connectionForProductDetails();

            Object.assign(this.editableMapping, connection);
        },

        async saveEditedMapping() {
            if (!this.editableMapping) {
                return;
            }

            const mapping = this.editingMapping ?? this.availableMappings.find((candidate) => candidate.id === this.editableMapping.id);
            if (!mapping) {
                return;
            }

            const connection = 'property' === this.editableMapping.storage
                ? connectionForProperty(this.editableMapping.propertyGroupId)
                : connectionForProductDetails();

            Object.assign(mapping, connection);

            const saved = await this.saveMapping(mapping);
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

        usageLabel(mapping) {
            if ('property' === mapping.storage) {
                return this.$tc('jv-catalog-category-attributes.usage.property');
            }

            return this.$tc('jv-catalog-category-attributes.usage.productInformation');
        },

        targetLabel(mapping) {
            if ('property' === mapping.storage) {
                return mapping.propertyGroup?.name ?? this.$tc('jv-catalog-category-attributes.target.notSelected');
            }

            return this.$tc('jv-catalog-category-attributes.target.notApplicable');
        },
    },
});
