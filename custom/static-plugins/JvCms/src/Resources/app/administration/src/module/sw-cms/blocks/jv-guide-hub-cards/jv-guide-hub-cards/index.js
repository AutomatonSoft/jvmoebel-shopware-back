Shopware.Component.register('sw-cms-preview-jv-guide-hub-cards', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-guide-hub-cards', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-guide-hub-cards',
    label: 'cms.blocks.jv-guide-hub-cards.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-guide-hub-cards',
    previewComponent: 'sw-cms-preview-jv-guide-hub-cards',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-guide-hub-cards',
        },
    },
});
