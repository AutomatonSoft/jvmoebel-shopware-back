Shopware.Component.register('sw-cms-preview-jv-countdown-promo', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-countdown-promo', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-countdown-promo',
    label: 'cms.blocks.jv-countdown-promo.label',
    category: 'promo',
    component: 'sw-cms-block-jv-countdown-promo',
    previewComponent: 'sw-cms-preview-jv-countdown-promo',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-countdown-promo',
        },
    },
});
