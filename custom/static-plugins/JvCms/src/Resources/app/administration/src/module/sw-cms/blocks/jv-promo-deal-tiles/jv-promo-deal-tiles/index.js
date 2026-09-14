Shopware.Component.register('sw-cms-preview-jv-promo-deal-tiles', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-promo-deal-tiles', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-promo-deal-tiles',
    label: 'cms.blocks.jv-promo-deal-tiles.label',
    category: 'promo',
    component: 'sw-cms-block-jv-promo-deal-tiles',
    previewComponent: 'sw-cms-preview-jv-promo-deal-tiles',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-promo-deal-tiles',
        },
    },
});
