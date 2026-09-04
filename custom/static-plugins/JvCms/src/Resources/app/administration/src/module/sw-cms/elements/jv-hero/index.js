/**
 * Registers CMS element `jv-hero` (platform SPEC-006).
 * defaultConfig is an empty safe form — no shop domain, no demo seed.
 */
Shopware.Component.register('sw-cms-el-preview-jv-hero', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-hero', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-hero', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-hero',
    label: 'cms.elements.jv-hero.label',
    component: 'sw-cms-el-jv-hero',
    configComponent: 'sw-cms-el-config-jv-hero',
    previewComponent: 'sw-cms-el-preview-jv-hero',
    defaultConfig: {
        ariaLabel: {
            source: 'static',
            value: '',
        },
        autoplay: {
            source: 'static',
            value: true,
        },
        autoplayIntervalMs: {
            source: 'static',
            value: 7000,
        },
        slides: {
            source: 'static',
            value: [],
        },
    },
});
