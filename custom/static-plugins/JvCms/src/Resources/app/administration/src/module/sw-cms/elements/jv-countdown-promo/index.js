/**
 * Registers CMS element `jv-countdown-promo`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-countdown-promo', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-countdown-promo', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-countdown-promo', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-countdown-promo',
    label: 'cms.elements.jv-countdown-promo.label',
    component: 'sw-cms-el-jv-countdown-promo',
    configComponent: 'sw-cms-el-config-jv-countdown-promo',
    previewComponent: 'sw-cms-el-preview-jv-countdown-promo',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        endsAt: { source: 'static', value: '' },
        promoCode: { source: 'static', value: '' },
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
