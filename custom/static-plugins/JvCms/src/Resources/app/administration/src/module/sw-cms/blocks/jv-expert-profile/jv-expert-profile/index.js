Shopware.Component.register('sw-cms-preview-jv-expert-profile', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-expert-profile', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-expert-profile',
    label: 'cms.blocks.jv-expert-profile.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-expert-profile',
    previewComponent: 'sw-cms-preview-jv-expert-profile',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-expert-profile',
        },
    },
});
