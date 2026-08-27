/**
 * Registers CMS element `jv-side-navigation`.
 * defaultConfig: logo, search placeholder, root category, footer. No tabs.
 */
Shopware.Component.register('sw-cms-el-preview-jv-side-navigation', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-side-navigation', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-side-navigation', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-side-navigation',
    label: 'cms.elements.jv-side-navigation.label',
    component: 'sw-cms-el-jv-side-navigation',
    configComponent: 'sw-cms-el-config-jv-side-navigation',
    previewComponent: 'sw-cms-el-preview-jv-side-navigation',
    defaultConfig: {
        logoMedia: { source: 'static', value: null },
        logoLink: { source: 'static', value: '' },
        searchPlaceholder: { source: 'static', value: '' },
        rootCategoryId: { source: 'static', value: null },
        showIcons: { source: 'static', value: true },
        footer: { source: 'static', value: { items: [] } },
    },
});
