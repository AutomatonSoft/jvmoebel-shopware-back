/**
 * Registers CMS element `jv-inline-product-teaser`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-inline-product-teaser', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-inline-product-teaser', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-inline-product-teaser', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-inline-product-teaser',
    label: 'cms.elements.jv-inline-product-teaser.label',
    component: 'sw-cms-el-jv-inline-product-teaser',
    configComponent: 'sw-cms-el-config-jv-inline-product-teaser',
    previewComponent: 'sw-cms-el-preview-jv-inline-product-teaser',
    defaultConfig: {
        productId: { source: 'static', value: null },
        name: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        imageMedia: { source: 'static', value: null },
        url: { source: 'static', value: '' },
    },
});
