/**
 * Registers CMS element `jv-editorial-team-grid`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-editorial-team-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-editorial-team-grid', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-editorial-team-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-editorial-team-grid',
    label: 'cms.elements.jv-editorial-team-grid.label',
    component: 'sw-cms-el-jv-editorial-team-grid',
    configComponent: 'sw-cms-el-config-jv-editorial-team-grid',
    previewComponent: 'sw-cms-el-preview-jv-editorial-team-grid',
    defaultConfig: {
        title: { source: 'static', value: '' },
        members: { source: 'static', value: [] },
    },
});
