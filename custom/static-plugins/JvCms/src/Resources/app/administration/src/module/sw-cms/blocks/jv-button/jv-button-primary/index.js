Shopware.Component.register('sw-cms-preview-jv-button-primary', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-button-primary', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-button-primary',
    label: 'cms.blocks.jv-button-primary.label',
    category: 'button',
    component: 'sw-cms-block-jv-button-primary',
    previewComponent: 'sw-cms-preview-jv-button-primary',
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
                        value: 'primary',
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