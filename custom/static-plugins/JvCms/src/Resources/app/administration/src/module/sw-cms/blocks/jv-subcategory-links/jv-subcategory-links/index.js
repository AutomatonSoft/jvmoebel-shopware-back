Shopware.Component.register('sw-cms-preview-jv-subcategory-links', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-subcategory-links', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-subcategory-links',
    label: 'cms.blocks.jv-subcategory-links.label',
    category: 'category',
    component: 'sw-cms-block-jv-subcategory-links',
    previewComponent: 'sw-cms-preview-jv-subcategory-links',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-subcategory-links',
        },
    },
});
