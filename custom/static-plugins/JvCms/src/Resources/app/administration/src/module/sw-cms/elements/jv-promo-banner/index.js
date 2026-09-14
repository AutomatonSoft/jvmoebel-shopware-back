/**
 * Registers CMS element `jv-promo-banner`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-promo-banner', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-promo-banner', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-promo-banner', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-promo-banner',
    label: 'cms.elements.jv-promo-banner.label',
    component: 'sw-cms-el-jv-promo-banner',
    configComponent: 'sw-cms-el-config-jv-promo-banner',
    previewComponent: 'sw-cms-el-preview-jv-promo-banner',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        contentPosition: { source: 'static', value: 'right' },
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
