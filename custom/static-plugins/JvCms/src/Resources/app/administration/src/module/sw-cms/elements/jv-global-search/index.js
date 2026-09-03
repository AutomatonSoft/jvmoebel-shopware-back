/**
 * Registers CMS element `jv-global-search`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-global-search', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-global-search', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-global-search', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-global-search',
    label: 'cms.elements.jv-global-search.label',
    component: 'sw-cms-el-jv-global-search',
    configComponent: 'sw-cms-el-config-jv-global-search',
    previewComponent: 'sw-cms-el-preview-jv-global-search',
    defaultConfig: {
        searchPlaceholder: { source: 'static', value: '' },
        suggestMinChars: { source: 'static', value: 3 },
        suggestLimit: { source: 'static', value: 10 },
        historyMaxItems: { source: 'static', value: 8 },
    },
});
