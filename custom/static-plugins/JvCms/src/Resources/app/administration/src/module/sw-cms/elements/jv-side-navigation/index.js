/**
 * Registers CMS element `jv-side-navigation` with Shopware Administration.
 *
 * `defaultConfig` is copied into a new slot once (when the editor drops the block).
 * Keep the field shape (logoMedia, tabs, footer, …) — empty values only.
 * Existing layouts keep their saved JSON until the editor saves again.
 */
Shopware.Component.register('sw-cms-el-preview-jv-side-navigation', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-side-navigation', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-side-navigation', () => import('./component'));

Shopware.Component.register(
    'sw-cms-el-config-jv-side-navigation-manual-item',
    () => import('./config/manual-item'),
);

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-side-navigation',
    label: 'cms.elements.jv-side-navigation.label',
    component: 'sw-cms-el-jv-side-navigation',
    configComponent: 'sw-cms-el-config-jv-side-navigation',
    previewComponent: 'sw-cms-el-preview-jv-side-navigation',
    defaultConfig: {
        logoMedia: {
            source: 'static',
            value: null,
        },
        logoLink: {
            source: 'static',
            value: '',
        },
        defaultTabId: {
            source: 'static',
            value: '',
        },
        tabs: {
            source: 'static',
            value: [],
        },
        footer: {
            source: 'static',
            value: {
                items: [],
            },
        },
    },
});