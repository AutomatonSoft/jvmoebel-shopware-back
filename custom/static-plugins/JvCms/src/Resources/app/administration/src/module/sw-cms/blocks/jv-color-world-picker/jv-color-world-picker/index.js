Shopware.Component.register('sw-cms-preview-jv-color-world-picker', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-color-world-picker', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-color-world-picker',
    label: 'cms.blocks.jv-color-world-picker.label',
    category: 'inspiration',
    component: 'sw-cms-block-jv-color-world-picker',
    previewComponent: 'sw-cms-preview-jv-color-world-picker',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: { type: 'jv-color-world-picker' },
    },
});
