Shopware.Component.register('sw-cms-preview-jv-table-of-contents', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-table-of-contents', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-table-of-contents',
    label: 'cms.blocks.jv-table-of-contents.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-table-of-contents',
    previewComponent: 'sw-cms-preview-jv-table-of-contents',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: { type: 'jv-table-of-contents' },
    },
});
