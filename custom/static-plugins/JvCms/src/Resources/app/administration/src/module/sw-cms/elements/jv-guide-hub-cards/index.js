/**
 * Registers CMS element `jv-guide-hub-cards`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-guide-hub-cards', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-guide-hub-cards', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-guide-hub-cards', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-guide-hub-cards',
    label: 'cms.elements.jv-guide-hub-cards.label',
    component: 'sw-cms-el-jv-guide-hub-cards',
    configComponent: 'sw-cms-el-config-jv-guide-hub-cards',
    previewComponent: 'sw-cms-el-preview-jv-guide-hub-cards',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        cards: { source: 'static', value: [] },
    },
});
