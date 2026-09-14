/**
 * Registers CMS element `jv-trend-look-grid`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-trend-look-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-trend-look-grid', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-trend-look-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-trend-look-grid',
    label: 'cms.elements.jv-trend-look-grid.label',
    component: 'sw-cms-el-jv-trend-look-grid',
    configComponent: 'sw-cms-el-config-jv-trend-look-grid',
    previewComponent: 'sw-cms-el-preview-jv-trend-look-grid',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        cards: { source: 'static', value: [] },
    },
});
