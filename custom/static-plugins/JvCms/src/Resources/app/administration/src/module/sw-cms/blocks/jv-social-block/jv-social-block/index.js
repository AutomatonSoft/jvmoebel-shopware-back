Shopware.Component.register('sw-cms-preview-jv-social-block', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-social-block', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-social-block',
    label: 'cms.blocks.jv-social-block.label',
    category: 'social-media-blocks',
    component: 'sw-cms-block-jv-social-block',
    previewComponent: 'sw-cms-preview-jv-social-block',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-social-block',
        },
    },
});
