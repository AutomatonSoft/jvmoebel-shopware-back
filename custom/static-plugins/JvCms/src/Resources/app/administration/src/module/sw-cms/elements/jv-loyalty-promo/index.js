/**
 * Registers CMS element `jv-loyalty-promo`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-loyalty-promo', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-loyalty-promo', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-loyalty-promo', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-loyalty-promo',
    label: 'cms.elements.jv-loyalty-promo.label',
    component: 'sw-cms-el-jv-loyalty-promo',
    configComponent: 'sw-cms-el-config-jv-loyalty-promo',
    previewComponent: 'sw-cms-el-preview-jv-loyalty-promo',
    defaultConfig: {
        title: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        benefits: { source: 'static', value: [] },
        promoCode: { source: 'static', value: '' },
        imageMedia: { source: 'static', value: null },
        link: {
            source: 'static',
            value: {
                label: '',
                url: '',
                size: 'medium',
            },
        },
    },
});
