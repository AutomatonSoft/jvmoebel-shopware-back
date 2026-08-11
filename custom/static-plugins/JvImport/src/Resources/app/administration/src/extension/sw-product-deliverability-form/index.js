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

            return pendingChange?.deliveryTimeId ?? this.jvImportDeliveryTimeLink?.deliveryTimeId ?? this.product.deliveryTimeId;
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

            const language = await this.jvImportLanguageRepository.get(languageId, Shopware.Context.api);
            if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                return;
            }
            if (!language.parentId) {
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
                return;
            }

            this.jvImportMarketSalesChannelId = salesChannel.id;
            if (!this.product.id) {
                return;
            }

            const linkCriteria = new Criteria(1, 1);
            linkCriteria.addFilter(Criteria.equals('productId', productId));
            linkCriteria.addFilter(Criteria.equals('productVersionId', Shopware.Defaults.versionId));
            linkCriteria.addFilter(Criteria.equals('salesChannelId', salesChannel.id));
            const link = (await this.jvImportDeliveryTimeLinkRepository.search(linkCriteria, Shopware.Context.api)).first() ?? null;
            if (request !== this.jvImportLoadRequest || languageId !== this.jvImportLanguageId || productId !== this.product.id) {
                return;
            }

            this.jvImportDeliveryTimeLink = link;
        },

        jvImportUpdateDeliveryTime(deliveryTimeId) {
            if (!this.jvImportMarketSalesChannelId || !this.product.id) {
                return;
            }

            stageDeliveryTimeChange({
                id: this.jvImportDeliveryTimeLink?.id,
                productId: this.product.id,
                productVersionId: Shopware.Defaults.versionId,
                salesChannelId: this.jvImportMarketSalesChannelId,
                deliveryTimeId: deliveryTimeId || null,
            });
        },
    },

    beforeUnmount() {
        discardDeliveryTimeChanges(this.product.id);
    },
});
