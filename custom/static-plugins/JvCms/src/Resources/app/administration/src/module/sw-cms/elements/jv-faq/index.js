/** Registers CMS element `jv-faq`. */
Shopware.Component.register('sw-cms-el-preview-jv-faq', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-faq', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-faq', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-faq',
    label: 'cms.elements.jv-faq.label',
    component: 'sw-cms-el-jv-faq',
    configComponent: 'sw-cms-el-config-jv-faq',
    previewComponent: 'sw-cms-el-preview-jv-faq',
    defaultConfig: {
        title: { source: 'static', value: '' },
        eyebrow: { source: 'static', value: '' },
        description: { source: 'static', value: '' },
        items: { source: 'static', value: [] },
    },
});
