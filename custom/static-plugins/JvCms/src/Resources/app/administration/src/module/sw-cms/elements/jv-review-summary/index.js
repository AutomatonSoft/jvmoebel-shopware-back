/**
 * Registers CMS element `jv-review-summary`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-review-summary', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-review-summary', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-review-summary', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-review-summary',
    label: 'cms.elements.jv-review-summary.label',
    component: 'sw-cms-el-jv-review-summary',
    configComponent: 'sw-cms-el-config-jv-review-summary',
    previewComponent: 'sw-cms-el-preview-jv-review-summary',
    defaultConfig: {
        summary: { source: 'static', value: '' },
        sourceLabel: { source: 'static', value: '' },
        rating: { source: 'static', value: null },
    },
});
