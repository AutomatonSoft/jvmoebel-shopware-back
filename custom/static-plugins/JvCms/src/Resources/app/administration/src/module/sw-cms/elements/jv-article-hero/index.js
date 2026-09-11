/** Registers CMS element `jv-article-hero`. */
Shopware.Component.register('sw-cms-el-preview-jv-article-hero', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-article-hero', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-article-hero', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-article-hero',
    label: 'cms.elements.jv-article-hero.label',
    component: 'sw-cms-el-jv-article-hero',
    configComponent: 'sw-cms-el-config-jv-article-hero',
    previewComponent: 'sw-cms-el-preview-jv-article-hero',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        imageMedia: {
            source: 'static',
            value: null,
            entity: { name: 'media' },
        },
        publishedAt: { source: 'static', value: '' },
        readTimeMinutes: { source: 'static', value: null },
    },
});
