/**
 * Registers CMS element `jv-newsletter` (platform SPEC-007).
 * defaultConfig is an empty safe form — no shop domain, no demo seed.
 */
Shopware.Component.register('sw-cms-el-preview-jv-newsletter', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-newsletter', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-newsletter', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-newsletter',
    label: 'cms.elements.jv-newsletter.label',
    component: 'sw-cms-el-jv-newsletter',
    configComponent: 'sw-cms-el-config-jv-newsletter',
    previewComponent: 'sw-cms-el-preview-jv-newsletter',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        buttonLabel: { source: 'static', value: '' },
        buttonSize: { source: 'static', value: 'medium' },
        placeholder: { source: 'static', value: '' },
        storefrontUrl: { source: 'static', value: '' },
        successMessage: { source: 'static', value: '' },
        invalidEmailMessage: { source: 'static', value: '' },
        errorMessage: { source: 'static', value: '' },
    },
});
