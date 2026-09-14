Shopware.Component.register('sw-cms-preview-jv-cross-room-section', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-cross-room-section', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-cross-room-section',
    label: 'cms.blocks.jv-cross-room-section.label',
    category: 'room',
    component: 'sw-cms-block-jv-cross-room-section',
    previewComponent: 'sw-cms-preview-jv-cross-room-section',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-cross-room-section',
        },
    },
});
