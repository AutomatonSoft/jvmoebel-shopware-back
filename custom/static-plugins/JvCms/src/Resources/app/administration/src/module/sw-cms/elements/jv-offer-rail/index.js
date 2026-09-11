/**
 * Registers CMS element `jv-offer-rail`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-offer-rail', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-offer-rail', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-offer-rail', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-offer-rail',
    label: 'cms.elements.jv-offer-rail.label',
    component: 'sw-cms-el-jv-offer-rail',
    configComponent: 'sw-cms-el-config-jv-offer-rail',
    previewComponent: 'sw-cms-el-preview-jv-offer-rail',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        ariaLabel: { source: 'static', value: '' },
        offers: { source: 'static', value: [] },
    },
});
