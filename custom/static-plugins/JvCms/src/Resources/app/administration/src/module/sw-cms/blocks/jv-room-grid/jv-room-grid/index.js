Shopware.Component.register('sw-cms-preview-jv-room-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-room-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-room-grid',
    label: 'cms.blocks.jv-room-grid.label',
    category: 'room',
    component: 'sw-cms-block-jv-room-grid',
    previewComponent: 'sw-cms-preview-jv-room-grid',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-room-grid',
        },
    },
});
