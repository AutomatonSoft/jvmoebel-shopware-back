import template from './sw-product-deliverability-form.html.twig';
import {
    discardDeliveryTimeChanges,
    pendingDeliveryTimeChange,
    stageDeliveryTimeChange,
} from './pending-delivery-time-changes';

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-product-deliverability-form', {
    template,

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            jvImportMarketSalesChannelId: null,
            jvImportDeliveryTimeLink: null,
            jvImportParentDeliveryTimeLink: null,
            jvImportParentProduct: null,
            jvImportPendingDeliveryTimeId: undefined,
            jvImportDeliveryTimeLoading: false,
            jvImportLoadRequest: 0,
        };
    },

    computed: {
        jvImportLanguageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        jvImportDeliveryTimeId() {
            const pendingChange = this.jvImportMarketSalesChannelId
                ? pendingDeliveryTimeChange(this.product.id, this.jvImportMarketSalesChannelId)
                : null;

            if (this.jvImportPendingDeliveryTimeId !== undefined) {
                return this.jvImportPendingDeliveryTimeId;
            }

            return pendingChange?.deliveryTimeId
                ?? this.jvImportDeliveryTimeLink?.deliveryTimeId
                ?? this.jvImportParentDeliveryTimeLink?.deliveryTimeId
                ?? this.product.deliveryTimeId
                ?? this.jvImportParentProduct?.deliveryTimeId;
        },

        jvImportSalesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        jvImportLanguageRepository() {
            return this.repositoryFactory.create('language');
        },

        jvImportDeliveryTimeLinkRepository() {
            return this.repositoryFactory.create('jv_import_product_sales_channel_delivery_time');
        },

        jvImportProductRepository() {
            return this.repositoryFactory.create('product');
        },
    },

    watch: {
        jvImportLanguageId: {
            immediate: true,
            handler() {
                this.jvImportLoadDeliveryTimeLink();
            },
        },

        'product.id'() {
            this.jvImportLoadDeliveryTimeLink();
        },
    },

    methods: {
        async jvImportLoadDeliveryTimeLink() {
            const request = ++this.jvImportLoadRequest;
            const languageId = this.jvImportLanguageId;
            const productId = this.product.id;
            this.jvImportMarketSalesChannelId = null;
            this.jvImportDeliveryTimeLink = null;
            this.jvImportParentDeliveryTimeLink = null;
            this.jvImportParentProduct = null;
            this.jvImportPendingDeliveryTimeId = undefined;
            this.jvImportDeliveryTimeLoading = true;

            const language = await this.jvImportLanguageRepository.get(languageId, Shopware.Context.api);
            if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                return;
            }
            if (!language.parentId) {
                this.jvImportDeliveryTimeLoading = false;

                return;
            }

            const salesChannelCriteria = new Criteria(1, 2);
            salesChannelCriteria.addFilter(Criteria.equals('languageId', languageId));
            const salesChannels = await this.jvImportSalesChannelRepository.search(salesChannelCriteria, Shopware.Context.api);
            if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                return;
            }
            const salesChannel = salesChannels.first();
            if (!salesChannel) {
                this.jvImportDeliveryTimeLoading = false;

                return;
            }

            this.jvImportMarketSalesChannelId = salesChannel.id;
            if (!this.product.id) {
                this.jvImportDeliveryTimeLoading = false;

                return;
            }

            const link = await this.jvImportFindDeliveryTimeLink(productId, salesChannel.id);
            if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                return;
            }

            this.jvImportDeliveryTimeLink = link;
            if (!link && this.product.parentId) {
                const parent = await this.jvImportProductRepository.get(this.product.parentId, Shopware.Context.api);
                if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                    return;
                }

                this.jvImportParentProduct = parent;
                this.jvImportParentDeliveryTimeLink = await this.jvImportFindDeliveryTimeLink(parent.id, salesChannel.id);
                if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                    return;
                }
            }
            this.jvImportDeliveryTimeLoading = false;
        },

        async jvImportFindDeliveryTimeLink(productId, salesChannelId) {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('productId', productId));
            criteria.addFilter(Criteria.equals('productVersionId', Shopware.Defaults.versionId));
            criteria.addFilter(Criteria.equals('salesChannelId', salesChannelId));

            return (await this.jvImportDeliveryTimeLinkRepository.search(criteria, Shopware.Context.api)).first() ?? null;
        },

        jvImportUpdateDeliveryTime(deliveryTimeId) {
            if (!this.jvImportMarketSalesChannelId || !this.product.id) {
                return;
            }

            this.jvImportPendingDeliveryTimeId = deliveryTimeId || null;
            stageDeliveryTimeChange({
                id: this.jvImportDeliveryTimeLink?.id,
                productId: this.product.id,
                productVersionId: Shopware.Defaults.versionId,
                salesChannelId: this.jvImportMarketSalesChannelId,
                deliveryTimeId: deliveryTimeId || null,
            });
        },
    },

});
