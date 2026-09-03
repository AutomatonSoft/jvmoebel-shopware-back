/**
 * Registers CMS element `jv-product-grid`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-product-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-product-grid', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-product-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-product-grid',
    label: 'cms.elements.jv-product-grid.label',
    component: 'sw-cms-el-jv-product-grid',
    configComponent: 'sw-cms-el-config-jv-product-grid',
    previewComponent: 'sw-cms-el-preview-jv-product-grid',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        products: { source: 'static', value: [] },
        viewAll: {
            source: 'static',
            value: {
                label: '',
                url: '',
            },
        },
    },
});
