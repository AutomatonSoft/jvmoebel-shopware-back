Shopware.Component.register('sw-cms-preview-jv-newsletter', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-newsletter', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-newsletter',
    label: 'cms.blocks.jv-newsletter.label',
    category: 'newsletter',
    component: 'sw-cms-block-jv-newsletter',
    previewComponent: 'sw-cms-preview-jv-newsletter',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-newsletter',
            default: {
                config: {
                    title: { source: 'static', value: '' },
                    eyebrow: { source: 'static', value: '' },
                    description: { source: 'static', value: '' },
                    buttonLabel: { source: 'static', value: '' },
                    buttonSize: { source: 'static', value: 'medium' },
                    placeholder: { source: 'static', value: '' },
                    storefrontUrl: { source: 'static', value: '' },
                    successMessage: { source: 'static', value: '' },
                    invalidEmailMessage: { source: 'static', value: '' },
                    errorMessage: { source: 'static', value: '' },
                },
            },
        },
    },
});
