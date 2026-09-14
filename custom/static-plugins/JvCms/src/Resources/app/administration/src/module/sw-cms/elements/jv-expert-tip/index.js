/**
 * Registers CMS element `jv-expert-tip`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-expert-tip', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-expert-tip', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-expert-tip', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-expert-tip',
    label: 'cms.elements.jv-expert-tip.label',
    component: 'sw-cms-el-jv-expert-tip',
    configComponent: 'sw-cms-el-config-jv-expert-tip',
    previewComponent: 'sw-cms-el-preview-jv-expert-tip',
    defaultConfig: {
        label: { source: 'static', value: '' },
        title: { source: 'static', value: '' },
        body: { source: 'static', value: '' },
    },
});
