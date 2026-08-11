import {
    discardDeliveryTimeChanges,
    pendingDeliveryTimeChanges,
} from '../sw-product-deliverability-form/pending-delivery-time-changes';

Shopware.Component.override('sw-product-detail', {
    methods: {
        abortOnLanguageChange() {
            return pendingDeliveryTimeChanges(this.product.id).length > 0 || this.$super('abortOnLanguageChange');
        },

        onChangeLanguage(languageId) {
            discardDeliveryTimeChanges(this.product.id);

            return this.$super('onChangeLanguage', languageId);
        },

        onCancel() {
            discardDeliveryTimeChanges(this.product.id);

            return this.$super('onCancel');
        },

        async saveProduct() {
            const response = await this.$super('saveProduct');
            if (response !== 'success' && response !== 'empty') {
                return response;
            }

            const changes = pendingDeliveryTimeChanges(this.product.id);
            if (changes.length === 0) {
                return response;
            }

            const repository = this.repositoryFactory.create('jv_import_product_sales_channel_delivery_time');
            try {
                for (const change of changes) {
                    if (change.deliveryTimeId === null) {
                        if (change.id) {
                            await repository.delete([change.id], this.productApiContext);
                        }

                        continue;
                    }

                    const entity = repository.create(this.productApiContext);
                    Object.assign(entity, change);
                    await repository.save(entity, this.productApiContext);
                }
            } catch (error) {
                return error;
            }

            discardDeliveryTimeChanges(this.product.id);
            await this.loadAll();

            return 'success';
        },
    },
});
