Shopware.Component.register('sw-cms-preview-jv-inline-product-teaser', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-inline-product-teaser', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-inline-product-teaser',
    label: 'cms.blocks.jv-inline-product-teaser.label',
    category: 'commerce',
    component: 'sw-cms-block-jv-inline-product-teaser',
    previewComponent: 'sw-cms-preview-jv-inline-product-teaser',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-inline-product-teaser',
        },
    },
});
