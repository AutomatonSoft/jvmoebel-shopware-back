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
        title: {
            source: 'static',
            value: '',
        },
        eyebrow: {
            source: 'static',
            value: '',
        },
        description: {
            source: 'static',
            value: '',
        },
        imageMedia: {
            source: 'static',
            value: null,
        },
        primaryLink: {
            source: 'static',
            value: {
                label: '',
                url: '',
                size: 'medium',
            },
        },
        secondaryLink: {
            source: 'static',
            value: {
                label: '',
                url: '',
                size: 'medium',
            },
        },
    },
});
