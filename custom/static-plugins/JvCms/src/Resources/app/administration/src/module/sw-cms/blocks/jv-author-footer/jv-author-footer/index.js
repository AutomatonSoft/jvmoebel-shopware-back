Shopware.Component.register('sw-cms-preview-jv-author-footer', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-author-footer', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-author-footer',
    label: 'cms.blocks.jv-author-footer.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-author-footer',
    previewComponent: 'sw-cms-preview-jv-author-footer',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-author-footer',
        },
    },
});
