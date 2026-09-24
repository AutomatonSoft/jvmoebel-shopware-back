import template from './jv-product-option-template-card.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    inject: [
        'repositoryFactory',
        'syncService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        product: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            assignment: null,
        };
    },

    computed: {
        templateProductRepository() {
            return this.repositoryFactory.create('jv_option_template_product');
        },

        selectedTemplateId() {
            return this.assignment?.templateId ?? null;
        },
    },

    watch: {
        'product.id': {
            immediate: true,
            handler(productId) {
                if (productId) {
                    this.loadAssignment(productId);
                } else {
                    this.assignment = null;
                }
            },
        },
    },

    methods: {
        async loadAssignment(productId) {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('productId', productId));
            criteria.setLimit(1);

            try {
                const results = await this.templateProductRepository.search(criteria, Shopware.Context.api);
                this.assignment = results.total > 0 ? results.first() : null;
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onTemplateChange(newTemplateId) {
            if (!this.product || !this.product.id) {
                return;
            }

            this.isLoading = true;

            try {
                if (!newTemplateId) {
                    if (this.assignment) {
                        await this.deleteAssignment(this.assignment);
                        this.assignment = null;
                    }
                } else {
                    await this.upsertAssignment(newTemplateId);
                    await this.loadAssignment(this.product.id);
                }

                this.createNotificationSuccess({
                    message: this.$t('jv-option-template.product.saveSuccess'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        upsertAssignment(templateId) {
            return this.syncService.sync({
                'jv-option-template-product-upsert': {
                    entity: 'jv_option_template_product',
                    action: 'upsert',
                    payload: [{
                        productId: this.product.id,
                        productVersionId: this.product.versionId,
                        templateId,
                    }],
                },
            });
        },

        deleteAssignment(assignment) {
            return this.syncService.sync({
                'jv-option-template-product-delete': {
                    entity: 'jv_option_template_product',
                    action: 'delete',
                    payload: [{
                        productId: assignment.productId,
                        productVersionId: assignment.productVersionId,
                    }],
                },
            });
        },
    },
};
