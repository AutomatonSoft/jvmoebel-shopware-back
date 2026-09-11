/**
 * Registers CMS element `jv-app-download-promo`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-app-download-promo', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-app-download-promo', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-app-download-promo', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-app-download-promo',
    label: 'cms.elements.jv-app-download-promo.label',
    component: 'sw-cms-el-jv-app-download-promo',
    configComponent: 'sw-cms-el-config-jv-app-download-promo',
    previewComponent: 'sw-cms-el-preview-jv-app-download-promo',
    defaultConfig: {
        title: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        appStoreUrl: { source: 'static', value: '' },
        playStoreUrl: { source: 'static', value: '' },
        qrImageMedia: { source: 'static', value: null },
        promoCode: { source: 'static', value: '' },
    },
});
