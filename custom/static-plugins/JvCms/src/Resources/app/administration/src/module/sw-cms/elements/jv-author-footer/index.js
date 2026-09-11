/**
 * Registers CMS element `jv-author-footer`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-author-footer', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-author-footer', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-author-footer', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-author-footer',
    label: 'cms.elements.jv-author-footer.label',
    component: 'sw-cms-el-jv-author-footer',
    configComponent: 'sw-cms-el-config-jv-author-footer',
    previewComponent: 'sw-cms-el-preview-jv-author-footer',
    defaultConfig: {
        authorName: { source: 'static', value: '' },
        expertise: { source: 'static', value: '' },
        bio: { source: 'static', value: '' },
        imageMedia: { source: 'static', value: null },
        link: { source: 'static', value: { label: '', url: '' } },
    },
});
