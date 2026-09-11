/** Registers CMS element `jv-color-world-picker`. */
Shopware.Component.register('sw-cms-el-preview-jv-color-world-picker', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-color-world-picker', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-color-world-picker', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-color-world-picker',
    label: 'cms.elements.jv-color-world-picker.label',
    component: 'sw-cms-el-jv-color-world-picker',
    configComponent: 'sw-cms-el-config-jv-color-world-picker',
    previewComponent: 'sw-cms-el-preview-jv-color-world-picker',
    defaultConfig: {
        title: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        colors: { source: 'static', value: [] },
    },
});
