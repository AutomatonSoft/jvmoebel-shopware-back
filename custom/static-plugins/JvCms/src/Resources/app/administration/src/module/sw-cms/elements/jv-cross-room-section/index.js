/**
 * Registers CMS element `jv-cross-room-section`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-cross-room-section', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-cross-room-section', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-cross-room-section', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-cross-room-section',
    label: 'cms.elements.jv-cross-room-section.label',
    component: 'sw-cms-el-jv-cross-room-section',
    configComponent: 'sw-cms-el-config-jv-cross-room-section',
    previewComponent: 'sw-cms-el-preview-jv-cross-room-section',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        rooms: { source: 'static', value: [] },
    },
});
