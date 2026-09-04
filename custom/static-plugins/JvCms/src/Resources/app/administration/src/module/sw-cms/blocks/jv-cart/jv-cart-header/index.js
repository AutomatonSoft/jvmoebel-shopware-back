Shopware.Component.register('sw-cms-preview-jv-cart-header', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-cart-header', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-cart-header',
    label: 'cms.blocks.jv-cart-header.label',
    category: 'navigation',
    component: 'sw-cms-block-jv-cart-header',
    previewComponent: 'sw-cms-preview-jv-cart-header',
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
