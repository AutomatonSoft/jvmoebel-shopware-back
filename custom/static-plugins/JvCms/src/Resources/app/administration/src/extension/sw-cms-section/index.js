Shopware.Component.override('sw-cms-section', {
    methods: {
        hasBlockErrors(block) {
            return this.$super('hasBlockErrors', block)
                || Shopware.Service('jvCmsValidationService').hasBlockErrors(block);
        },
    },
});
