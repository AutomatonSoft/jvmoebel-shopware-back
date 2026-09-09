/** Registers CMS element `jv-home-editorial`. */
Shopware.Component.register('sw-cms-el-preview-jv-home-editorial', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-home-editorial', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-home-editorial', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-home-editorial',
    label: 'cms.elements.jv-home-editorial.label',
    component: 'sw-cms-el-jv-home-editorial',
    configComponent: 'sw-cms-el-config-jv-home-editorial',
    previewComponent: 'sw-cms-el-preview-jv-home-editorial',
    defaultConfig: {
        appearance: { source: 'static', value: 'card' },
        statement: { source: 'static', value: '' },
        title: { source: 'static', value: '' },
        introduction: { source: 'static', value: [] },
        sections: { source: 'static', value: [] },
        showMoreLabel: { source: 'static', value: '' },
        showLessLabel: { source: 'static', value: '' },
    },
});
