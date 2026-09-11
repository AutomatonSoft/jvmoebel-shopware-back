Shopware.Component.register('sw-cms-preview-jv-trend-look-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-trend-look-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-trend-look-grid',
    label: 'cms.blocks.jv-trend-look-grid.label',
    category: 'inspiration',
    component: 'sw-cms-block-jv-trend-look-grid',
    previewComponent: 'sw-cms-preview-jv-trend-look-grid',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-trend-look-grid',
        },
    },
});
