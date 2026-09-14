/**
 * Registers CMS element `jv-expert-quote`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-expert-quote', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-expert-quote', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-expert-quote', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-expert-quote',
    label: 'cms.elements.jv-expert-quote.label',
    component: 'sw-cms-el-jv-expert-quote',
    configComponent: 'sw-cms-el-config-jv-expert-quote',
    previewComponent: 'sw-cms-el-preview-jv-expert-quote',
    defaultConfig: {
        quote: { source: 'static', value: '' },
        authorName: { source: 'static', value: '' },
        authorRole: { source: 'static', value: '' },
    },
});
