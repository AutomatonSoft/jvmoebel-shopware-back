Shopware.Component.register('sw-cms-preview-jv-button-secondary', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-button-secondary', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-button-secondary',
    label: 'cms.blocks.jv-button-secondary.label',
    category: 'button',
    component: 'sw-cms-block-jv-button-secondary',
    previewComponent: 'sw-cms-preview-jv-button-secondary',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: {
            type: 'jv-button',
            default: {
                config: {
                    label: {
                        source: 'static',
                        value: 'Button',
                    },
                    url: {
                        source: 'static',
                        value: '',
                    },
                    variant: {
                        source: 'static',
                        value: 'secondary',
                    },
                    openInNewTab: {
                        source: 'static',
                        value: false,
                    },
                },
            },
        },
    },
});