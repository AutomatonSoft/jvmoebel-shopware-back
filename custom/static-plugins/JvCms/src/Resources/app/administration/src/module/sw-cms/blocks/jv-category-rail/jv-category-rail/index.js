Shopware.Component.register('sw-cms-preview-jv-category-rail', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-category-rail', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-category-rail',
    label: 'cms.blocks.jv-category-rail.label',
    category: 'category',
    component: 'sw-cms-block-jv-category-rail',
    previewComponent: 'sw-cms-preview-jv-category-rail',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-category-rail',
        },
    },
});
