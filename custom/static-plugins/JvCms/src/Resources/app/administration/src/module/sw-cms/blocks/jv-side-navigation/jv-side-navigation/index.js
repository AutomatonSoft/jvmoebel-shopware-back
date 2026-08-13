Shopware.Component.register('sw-cms-preview-jv-side-navigation', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-side-navigation', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-side-navigation',
    label: 'cms.blocks.jv-side-navigation.label',
    category: 'navigation',
    component: 'sw-cms-block-jv-side-navigation',
    previewComponent: 'sw-cms-preview-jv-side-navigation',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-side-navigation',
        },
    },
});