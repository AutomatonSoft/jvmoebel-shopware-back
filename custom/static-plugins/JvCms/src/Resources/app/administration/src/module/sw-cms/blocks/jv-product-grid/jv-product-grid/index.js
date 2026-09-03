Shopware.Component.register('sw-cms-preview-jv-product-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-product-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-product-grid',
    label: 'cms.blocks.jv-product-grid.label',
    category: 'commerce',
    component: 'sw-cms-block-jv-product-grid',
    previewComponent: 'sw-cms-preview-jv-product-grid',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-product-grid',
        },
    },
});
