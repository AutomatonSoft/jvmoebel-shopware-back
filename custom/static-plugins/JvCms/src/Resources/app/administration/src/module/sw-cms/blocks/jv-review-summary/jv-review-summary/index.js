Shopware.Component.register('sw-cms-preview-jv-review-summary', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-review-summary', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-review-summary',
    label: 'cms.blocks.jv-review-summary.label',
    category: 'trust',
    component: 'sw-cms-block-jv-review-summary',
    previewComponent: 'sw-cms-preview-jv-review-summary',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-review-summary',
        },
    },
});
