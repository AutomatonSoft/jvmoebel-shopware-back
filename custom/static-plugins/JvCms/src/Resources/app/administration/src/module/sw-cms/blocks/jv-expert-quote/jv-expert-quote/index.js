Shopware.Component.register('sw-cms-preview-jv-expert-quote', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-expert-quote', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-expert-quote',
    label: 'cms.blocks.jv-expert-quote.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-expert-quote',
    previewComponent: 'sw-cms-preview-jv-expert-quote',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-expert-quote',
        },
    },
});
