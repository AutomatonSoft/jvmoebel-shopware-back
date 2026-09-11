Shopware.Component.register('sw-cms-preview-jv-expert-tip', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-expert-tip', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-expert-tip',
    label: 'cms.blocks.jv-expert-tip.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-expert-tip',
    previewComponent: 'sw-cms-preview-jv-expert-tip',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-expert-tip',
        },
    },
});
