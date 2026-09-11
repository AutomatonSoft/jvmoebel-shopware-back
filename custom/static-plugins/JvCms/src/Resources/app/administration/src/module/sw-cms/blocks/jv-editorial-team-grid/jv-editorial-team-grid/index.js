Shopware.Component.register('sw-cms-preview-jv-editorial-team-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-editorial-team-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-editorial-team-grid',
    label: 'cms.blocks.jv-editorial-team-grid.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-editorial-team-grid',
    previewComponent: 'sw-cms-preview-jv-editorial-team-grid',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-editorial-team-grid',
        },
    },
});
