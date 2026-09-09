Shopware.Component.register('sw-cms-preview-jv-home-editorial', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-home-editorial', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-home-editorial',
    label: 'cms.blocks.jv-home-editorial.label',
    category: 'text',
    component: 'sw-cms-block-jv-home-editorial',
    previewComponent: 'sw-cms-preview-jv-home-editorial',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-home-editorial',
        },
    },
});
