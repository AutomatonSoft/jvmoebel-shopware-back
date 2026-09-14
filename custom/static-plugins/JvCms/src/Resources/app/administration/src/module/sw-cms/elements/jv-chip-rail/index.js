/**
 * Registers CMS element `jv-chip-rail`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-chip-rail', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-chip-rail', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-chip-rail', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-chip-rail',
    label: 'cms.elements.jv-chip-rail.label',
    component: 'sw-cms-el-jv-chip-rail',
    configComponent: 'sw-cms-el-config-jv-chip-rail',
    previewComponent: 'sw-cms-el-preview-jv-chip-rail',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        chips: { source: 'static', value: [] },
    },
});
