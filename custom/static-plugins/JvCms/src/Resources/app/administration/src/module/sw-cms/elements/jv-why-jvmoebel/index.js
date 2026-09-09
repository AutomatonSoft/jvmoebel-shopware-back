/**
 * Registers CMS element `jv-why-jvmoebel`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-why-jvmoebel', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-why-jvmoebel', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-why-jvmoebel', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-why-jvmoebel',
    label: 'cms.elements.jv-why-jvmoebel.label',
    component: 'sw-cms-el-jv-why-jvmoebel',
    configComponent: 'sw-cms-el-config-jv-why-jvmoebel',
    previewComponent: 'sw-cms-el-preview-jv-why-jvmoebel',
    defaultConfig: {
        mark: { source: 'static', value: '' },
        tagline: { source: 'static', value: '' },
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        benefits: { source: 'static', value: [] },
        viewAll: {
            source: 'static',
            value: {
                label: '',
                url: '',
            },
        },
    },
});
