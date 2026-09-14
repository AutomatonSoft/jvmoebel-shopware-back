Shopware.Component.register('sw-cms-preview-jv-trust-rating', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-trust-rating', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-trust-rating',
    label: 'cms.blocks.jv-trust-rating.label',
    category: 'trust',
    component: 'sw-cms-block-jv-trust-rating',
    previewComponent: 'sw-cms-preview-jv-trust-rating',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-trust-rating',
        },
    },
});
