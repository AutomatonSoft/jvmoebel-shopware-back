Shopware.Component.register('sw-cms-preview-jv-look-scene', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-look-scene', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-look-scene',
    label: 'cms.blocks.jv-look-scene.label',
    category: 'inspiration',
    component: 'sw-cms-block-jv-look-scene',
    previewComponent: 'sw-cms-preview-jv-look-scene',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: { type: 'jv-look-scene' },
    },
});
