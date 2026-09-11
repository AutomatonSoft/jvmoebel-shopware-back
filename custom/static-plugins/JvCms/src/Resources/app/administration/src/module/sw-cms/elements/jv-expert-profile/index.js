/**
 * Registers CMS element `jv-expert-profile`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-expert-profile', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-expert-profile', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-expert-profile', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-expert-profile',
    label: 'cms.elements.jv-expert-profile.label',
    component: 'sw-cms-el-jv-expert-profile',
    configComponent: 'sw-cms-el-config-jv-expert-profile',
    previewComponent: 'sw-cms-el-preview-jv-expert-profile',
    defaultConfig: {
        name: { source: 'static', value: '' },
        role: { source: 'static', value: '' },
        bio: { source: 'static', value: '' },
        imageMedia: { source: 'static', value: null },
        link: { source: 'static', value: { label: '', url: '' } },
    },
});
