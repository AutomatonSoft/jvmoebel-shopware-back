import template from './sw-product-deliverability-form.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-product-deliverability-form', {
    template,

    data() {
        return {
            jvImportMarketSalesChannelId: null,
            jvImportDeliveryTimeLink: null,
        };
    },

    computed: {
        jvImportLanguageId() {
            return Shopware.Context.api.languageId;
        },

        jvImportDeliveryTimeId() {
            return this.jvImportDeliveryTimeLink?.deliveryTimeId ?? null;
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
            this.jvImportMarketSalesChannelId = null;
            this.jvImportDeliveryTimeLink = null;

            const language = await this.jvImportLanguageRepository.get(this.jvImportLanguageId, Shopware.Context.api);
            if (!language.parentId) {
                return;
            }

            const salesChannelCriteria = new Criteria(1, 2);
            salesChannelCriteria.addFilter(Criteria.equals('languageId', this.jvImportLanguageId));
            const salesChannels = await this.jvImportSalesChannelRepository.search(salesChannelCriteria, Shopware.Context.api);
            const salesChannel = salesChannels.first();
            if (!salesChannel) {
                return;
            }

            this.jvImportMarketSalesChannelId = salesChannel.id;
            if (!this.product.id) {
                return;
            }

            const linkCriteria = new Criteria(1, 1);
            linkCriteria.addFilter(Criteria.equals('productId', this.product.id));
            linkCriteria.addFilter(Criteria.equals('salesChannelId', salesChannel.id));
            this.jvImportDeliveryTimeLink = (await this.jvImportDeliveryTimeLinkRepository.search(linkCriteria, Shopware.Context.api)).first() ?? null;
        },

        async jvImportUpdateDeliveryTime(deliveryTimeId) {
            if (!this.jvImportMarketSalesChannelId) {
                return;
            }

            if (!deliveryTimeId && this.jvImportDeliveryTimeLink) {
                await this.jvImportDeliveryTimeLinkRepository.delete([this.jvImportDeliveryTimeLink.id], Shopware.Context.api);
                this.jvImportDeliveryTimeLink = null;

                return;
            }

            if (!deliveryTimeId || !this.product.id) {
                return;
            }

            const link = this.jvImportDeliveryTimeLink ?? this.jvImportDeliveryTimeLinkRepository.create(Shopware.Context.api);
            Object.assign(link, {
                productId: this.product.id,
                salesChannelId: this.jvImportMarketSalesChannelId,
                deliveryTimeId,
            });
            await this.jvImportDeliveryTimeLinkRepository.save(link, Shopware.Context.api);
            this.jvImportDeliveryTimeLink = link;
        },
    },
});
