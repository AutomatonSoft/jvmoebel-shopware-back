Shopware.Component.register('sw-cms-preview-jv-why-jvmoebel', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-why-jvmoebel', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-why-jvmoebel',
    label: 'cms.blocks.jv-why-jvmoebel.label',
    category: 'brand',
    component: 'sw-cms-block-jv-why-jvmoebel',
    previewComponent: 'sw-cms-preview-jv-why-jvmoebel',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-why-jvmoebel',
        },
    },
});
