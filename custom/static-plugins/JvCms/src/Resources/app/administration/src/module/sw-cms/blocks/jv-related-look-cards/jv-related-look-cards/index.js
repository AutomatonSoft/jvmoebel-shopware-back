Shopware.Component.register('sw-cms-preview-jv-related-look-cards', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-related-look-cards', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-related-look-cards',
    label: 'cms.blocks.jv-related-look-cards.label',
    category: 'inspiration',
    component: 'sw-cms-block-jv-related-look-cards',
    previewComponent: 'sw-cms-preview-jv-related-look-cards',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-related-look-cards',
        },
    },
});
