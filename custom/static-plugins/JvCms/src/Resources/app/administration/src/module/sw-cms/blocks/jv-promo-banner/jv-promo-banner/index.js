Shopware.Component.register('sw-cms-preview-jv-promo-banner', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-promo-banner', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-promo-banner',
    label: 'cms.blocks.jv-promo-banner.label',
    category: 'promo',
    component: 'sw-cms-block-jv-promo-banner',
    previewComponent: 'sw-cms-preview-jv-promo-banner',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-promo-banner',
        },
    },
});
