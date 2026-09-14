Shopware.Component.register('sw-cms-preview-jv-text-custom-tables', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-text-custom-tables', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-text-custom-tables',
    label: 'cms.blocks.jv-text-custom-tables.label',
    category: 'text',
    component: 'sw-cms-block-jv-text-custom-tables',
    previewComponent: 'sw-cms-preview-jv-text-custom-tables',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-text-custom-tables',
        },
    },
});
