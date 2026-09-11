Shopware.Component.register('sw-cms-preview-jv-chip-rail', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-chip-rail', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-chip-rail',
    label: 'cms.blocks.jv-chip-rail.label',
    category: 'category',
    component: 'sw-cms-block-jv-chip-rail',
    previewComponent: 'sw-cms-preview-jv-chip-rail',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-chip-rail',
        },
    },
});
