import template from './jv-product-option-template-card.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    inject: [
        'repositoryFactory',
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
            selectedTemplateId: null,
            assignmentRecordId: null,
        };
    },

    computed: {
        templateProductRepository() {
            return this.repositoryFactory.create('jv_option_template_product');
        },
    },

    watch: {
        'product.id': {
            immediate: true,
            handler(productId) {
                if (productId) {
                    this.loadAssignment(productId);
                } else {
                    this.selectedTemplateId = null;
                    this.assignmentRecordId = null;
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
                if (results.total > 0) {
                    const record = results.first();
                    this.assignmentRecordId = record.id;
                    this.selectedTemplateId = record.templateId;
                } else {
                    this.assignmentRecordId = null;
                    this.selectedTemplateId = null;
                }
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
                    if (this.assignmentRecordId) {
                        await this.templateProductRepository.delete(this.assignmentRecordId, Shopware.Context.api);
                        this.assignmentRecordId = null;
                        this.selectedTemplateId = null;
                    }
                } else if (this.assignmentRecordId) {
                    await this.templateProductRepository.save({
                        id: this.assignmentRecordId,
                        productId: this.product.id,
                        templateId: newTemplateId,
                    }, Shopware.Context.api);
                    this.selectedTemplateId = newTemplateId;
                } else {
                    const newRecord = this.templateProductRepository.create(Shopware.Context.api);
                    newRecord.productId = this.product.id;
                    newRecord.templateId = newTemplateId;
                    await this.templateProductRepository.save(newRecord, Shopware.Context.api);
                    this.assignmentRecordId = newRecord.id;
                    this.selectedTemplateId = newTemplateId;
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
    },
};
