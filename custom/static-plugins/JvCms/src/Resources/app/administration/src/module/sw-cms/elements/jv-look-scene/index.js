/** Registers CMS element `jv-look-scene`. */
Shopware.Component.register('sw-cms-el-preview-jv-look-scene', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-look-scene', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-look-scene', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-look-scene',
    label: 'cms.elements.jv-look-scene.label',
    component: 'sw-cms-el-jv-look-scene',
    configComponent: 'sw-cms-el-config-jv-look-scene',
    previewComponent: 'sw-cms-el-preview-jv-look-scene',
    defaultConfig: {
        title: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        imageMedia: {
            source: 'static',
            value: null,
            entity: { name: 'media' },
        },
        products: { source: 'static', value: [] },
        viewAll: { source: 'static', value: { label: '', url: '' } },
    },
});
