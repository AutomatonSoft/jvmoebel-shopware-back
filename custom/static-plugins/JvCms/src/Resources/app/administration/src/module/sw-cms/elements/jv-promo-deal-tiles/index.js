/**
 * Registers CMS element `jv-promo-deal-tiles`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-promo-deal-tiles', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-promo-deal-tiles', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-promo-deal-tiles', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-promo-deal-tiles',
    label: 'cms.elements.jv-promo-deal-tiles.label',
    component: 'sw-cms-el-jv-promo-deal-tiles',
    configComponent: 'sw-cms-el-config-jv-promo-deal-tiles',
    previewComponent: 'sw-cms-el-preview-jv-promo-deal-tiles',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        tiles: { source: 'static', value: [] },
    },
});
