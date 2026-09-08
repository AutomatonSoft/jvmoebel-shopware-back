/** Registers CMS element `jv-shop-the-look`. */
Shopware.Component.register('sw-cms-el-preview-jv-shop-the-look', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-shop-the-look', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-shop-the-look', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-shop-the-look',
    label: 'cms.elements.jv-shop-the-look.label',
    component: 'sw-cms-el-jv-shop-the-look',
    configComponent: 'sw-cms-el-config-jv-shop-the-look',
    previewComponent: 'sw-cms-el-preview-jv-shop-the-look',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        imageMedia: {
            source: 'static',
            value: null,
            entity: {
                name: 'media',
            },
        },
        items: { source: 'static', value: [] },
        viewAll: { source: 'static', value: { label: '', url: '' } },
    },
});
