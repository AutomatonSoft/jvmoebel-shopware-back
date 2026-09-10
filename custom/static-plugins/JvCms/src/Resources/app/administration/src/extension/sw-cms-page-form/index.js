import template from './sw-cms-page-form.html.twig';

Shopware.Component.override('sw-cms-page-form', {
    template,

    methods: {
        jvCmsBlockHasValidationErrors(block) {
            return Shopware.Service('jvCmsValidationService').hasBlockErrors(block);
        },
    },
});
