/** Registers CMS element `jv-text-custom-tables`. */
Shopware.Component.register('sw-cms-el-preview-jv-text-custom-tables', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-text-custom-tables', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-text-custom-tables', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-text-custom-tables',
    label: 'cms.elements.jv-text-custom-tables.label',
    component: 'sw-cms-el-jv-text-custom-tables',
    configComponent: 'sw-cms-el-config-jv-text-custom-tables',
    previewComponent: 'sw-cms-el-preview-jv-text-custom-tables',
    defaultConfig: {
        topText: { source: 'static', value: '' },
        sections: { source: 'static', value: [] },
        bottomText: { source: 'static', value: '' },
    },
});
