Shopware.Component.register('sw-cms-preview-jv-instagram-style', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-instagram-style', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-instagram-style',
    label: 'cms.blocks.jv-instagram-style.label',
    category: 'inspiration',
    component: 'sw-cms-block-jv-instagram-style',
    previewComponent: 'sw-cms-preview-jv-instagram-style',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-instagram-style',
        },
    },
});
