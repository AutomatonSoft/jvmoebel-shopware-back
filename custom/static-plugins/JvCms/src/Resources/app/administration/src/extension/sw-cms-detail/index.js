Shopware.Component.override('sw-cms-detail', {
    methods: {
        pageIsValid() {
            const shopwarePageIsValid = this.$super('pageIsValid');
            const customPageCanBeSaved = Shopware.Service('jvCmsValidationService').canSave(this.page);

            return shopwarePageIsValid && customPageCanBeSaved;
        },

        onSaveEntity() {
            if (!Shopware.Service('jvCmsValidationService').canSave(this.page)) {
                this.createNotificationError({
                    message: this.$t('sw-cms.detail.notification.pageInvalid'),
                });

                return Promise.reject();
            }

            return this.$super('onSaveEntity');
        },
    },
});
