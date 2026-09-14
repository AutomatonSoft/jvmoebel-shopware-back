Shopware.Component.register('sw-cms-preview-jv-loyalty-promo', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-loyalty-promo', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-loyalty-promo',
    label: 'cms.blocks.jv-loyalty-promo.label',
    category: 'promo',
    component: 'sw-cms-block-jv-loyalty-promo',
    previewComponent: 'sw-cms-preview-jv-loyalty-promo',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-loyalty-promo',
        },
    },
});
