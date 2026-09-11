Shopware.Component.register('sw-cms-preview-jv-app-download-promo', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-app-download-promo', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-app-download-promo',
    label: 'cms.blocks.jv-app-download-promo.label',
    category: 'promo',
    component: 'sw-cms-block-jv-app-download-promo',
    previewComponent: 'sw-cms-preview-jv-app-download-promo',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-app-download-promo',
        },
    },
});
