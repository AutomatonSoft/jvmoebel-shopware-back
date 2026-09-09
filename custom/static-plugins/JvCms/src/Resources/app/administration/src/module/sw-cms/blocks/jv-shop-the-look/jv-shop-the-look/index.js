Shopware.Component.register('sw-cms-preview-jv-shop-the-look', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-shop-the-look', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-shop-the-look',
    label: 'cms.blocks.jv-shop-the-look.label',
    category: 'commerce',
    component: 'sw-cms-block-jv-shop-the-look',
    previewComponent: 'sw-cms-preview-jv-shop-the-look',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-shop-the-look',
        },
    },
});
