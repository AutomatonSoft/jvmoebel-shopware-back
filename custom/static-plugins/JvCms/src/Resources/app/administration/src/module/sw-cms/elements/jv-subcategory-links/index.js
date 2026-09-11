/**
 * Registers CMS element `jv-subcategory-links`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-subcategory-links', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-subcategory-links', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-subcategory-links', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-subcategory-links',
    label: 'cms.elements.jv-subcategory-links.label',
    component: 'sw-cms-el-jv-subcategory-links',
    configComponent: 'sw-cms-el-config-jv-subcategory-links',
    previewComponent: 'sw-cms-el-preview-jv-subcategory-links',
    defaultConfig: {
        title: { source: 'static', value: '' },
        links: { source: 'static', value: [] },
    },
});
