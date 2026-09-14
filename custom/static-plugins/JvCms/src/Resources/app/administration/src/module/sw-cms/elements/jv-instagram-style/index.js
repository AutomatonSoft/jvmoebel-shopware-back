/**
 * Registers CMS element `jv-instagram-style`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-instagram-style', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-instagram-style', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-instagram-style', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-instagram-style',
    label: 'cms.elements.jv-instagram-style.label',
    component: 'sw-cms-el-jv-instagram-style',
    configComponent: 'sw-cms-el-config-jv-instagram-style',
    previewComponent: 'sw-cms-el-preview-jv-instagram-style',
    defaultConfig: {
        handle: { source: 'static', value: '' },
        caption: { source: 'static', value: '' },
        imageMedia: { source: 'static', value: null },
        link: {
            source: 'static',
            value: {
                label: '',
                url: '',
            },
        },
    },
});
