Shopware.Component.register('sw-cms-preview-jv-benefit-strip', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-benefit-strip', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-benefit-strip',
    label: 'cms.blocks.jv-benefit-strip.label',
    category: 'brand',
    component: 'sw-cms-block-jv-benefit-strip',
    previewComponent: 'sw-cms-preview-jv-benefit-strip',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-benefit-strip',
        },
    },
});
