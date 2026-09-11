Shopware.Component.register('sw-cms-preview-jv-article-hero', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-article-hero', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-article-hero',
    label: 'cms.blocks.jv-article-hero.label',
    category: 'editorial',
    component: 'sw-cms-block-jv-article-hero',
    previewComponent: 'sw-cms-preview-jv-article-hero',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: { type: 'jv-article-hero' },
    },
});
