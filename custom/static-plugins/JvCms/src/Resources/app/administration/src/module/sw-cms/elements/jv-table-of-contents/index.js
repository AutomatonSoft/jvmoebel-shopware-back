/** Registers CMS element `jv-table-of-contents`. */
Shopware.Component.register('sw-cms-el-preview-jv-table-of-contents', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-table-of-contents', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-table-of-contents', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-table-of-contents',
    label: 'cms.elements.jv-table-of-contents.label',
    component: 'sw-cms-el-jv-table-of-contents',
    configComponent: 'sw-cms-el-config-jv-table-of-contents',
    previewComponent: 'sw-cms-el-preview-jv-table-of-contents',
    defaultConfig: {
        title: { source: 'static', value: '' },
        items: { source: 'static', value: [] },
    },
});
