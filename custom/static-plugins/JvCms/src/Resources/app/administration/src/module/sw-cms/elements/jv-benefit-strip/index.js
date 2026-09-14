/**
 * Registers CMS element `jv-benefit-strip`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-benefit-strip', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-benefit-strip', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-benefit-strip', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-benefit-strip',
    label: 'cms.elements.jv-benefit-strip.label',
    component: 'sw-cms-el-jv-benefit-strip',
    configComponent: 'sw-cms-el-config-jv-benefit-strip',
    previewComponent: 'sw-cms-el-preview-jv-benefit-strip',
    defaultConfig: {
        items: { source: 'static', value: [] },
    },
});
