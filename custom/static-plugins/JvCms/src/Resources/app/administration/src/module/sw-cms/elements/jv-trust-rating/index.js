/**
 * Registers CMS element `jv-trust-rating`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-trust-rating', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-trust-rating', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-trust-rating', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-trust-rating',
    label: 'cms.elements.jv-trust-rating.label',
    component: 'sw-cms-el-jv-trust-rating',
    configComponent: 'sw-cms-el-config-jv-trust-rating',
    previewComponent: 'sw-cms-el-preview-jv-trust-rating',
    defaultConfig: {
        rating: { source: 'static', value: null },
        reviewCount: { source: 'static', value: null },
        providerLabel: { source: 'static', value: '' },
        link: {
            source: 'static',
            value: {
                label: '',
                url: '',
            },
        },
    },
});
