/**
 * Registers CMS element `jv-category-rail`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-category-rail', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-category-rail', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-category-rail', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-category-rail',
    label: 'cms.elements.jv-category-rail.label',
    component: 'sw-cms-el-jv-category-rail',
    configComponent: 'sw-cms-el-config-jv-category-rail',
    previewComponent: 'sw-cms-el-preview-jv-category-rail',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        layout: { source: 'static', value: 'rail' },
        categories: { source: 'static', value: [] },
        viewAll: {
            source: 'static',
            value: {
                label: '',
                url: '',
            },
        },
    },
});
