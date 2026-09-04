Shopware.Component.register('sw-cms-preview-jv-cart', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-cart', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-cart',
    label: 'cms.blocks.jv-cart.label',
    category: 'commerce',
    component: 'sw-cms-block-jv-cart',
    previewComponent: 'sw-cms-preview-jv-cart',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-cart',
        },
    },
});
