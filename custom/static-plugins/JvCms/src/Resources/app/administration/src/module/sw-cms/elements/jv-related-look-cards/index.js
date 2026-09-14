/**
 * Registers CMS element `jv-related-look-cards`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-related-look-cards', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-related-look-cards', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-related-look-cards', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-related-look-cards',
    label: 'cms.elements.jv-related-look-cards.label',
    component: 'sw-cms-el-jv-related-look-cards',
    configComponent: 'sw-cms-el-config-jv-related-look-cards',
    previewComponent: 'sw-cms-el-preview-jv-related-look-cards',
    defaultConfig: {
        title: { source: 'static', value: '' },
        cards: { source: 'static', value: [] },
    },
});
