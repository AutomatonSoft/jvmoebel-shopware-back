Shopware.Component.register('sw-cms-preview-jv-global-search', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-global-search', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-global-search',
    label: 'cms.blocks.jv-global-search.label',
    category: 'navigation',
    component: 'sw-cms-block-jv-global-search',
    previewComponent: 'sw-cms-preview-jv-global-search',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-global-search',
        },
    },
});
