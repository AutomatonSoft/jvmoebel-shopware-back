Shopware.Component.register('sw-cms-preview-jv-faq', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-faq', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-faq',
    label: 'cms.blocks.jv-faq.label',
    category: 'text',
    component: 'sw-cms-block-jv-faq',
    previewComponent: 'sw-cms-preview-jv-faq',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-faq',
        },
    },
});
