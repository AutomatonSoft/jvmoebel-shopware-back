Shopware.Component.register('sw-cms-preview-jv-offer-rail', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-offer-rail', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-offer-rail',
    label: 'cms.blocks.jv-offer-rail.label',
    category: 'promo',
    component: 'sw-cms-block-jv-offer-rail',
    previewComponent: 'sw-cms-preview-jv-offer-rail',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-offer-rail',
        },
    },
});
