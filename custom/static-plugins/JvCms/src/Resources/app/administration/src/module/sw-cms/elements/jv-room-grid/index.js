/**
 * Registers CMS element `jv-room-grid`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-room-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-room-grid', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-room-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-room-grid',
    label: 'cms.elements.jv-room-grid.label',
    component: 'sw-cms-el-jv-room-grid',
    configComponent: 'sw-cms-el-config-jv-room-grid',
    previewComponent: 'sw-cms-el-preview-jv-room-grid',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        rooms: { source: 'static', value: [] },
    },
});
