Shopware.Component.register('sw-cms-preview-jv-button-link', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-button-link', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-button-link',
    label: 'cms.blocks.jv-button-link.label',
    category: 'button',
    component: 'sw-cms-block-jv-button-link',
    previewComponent: 'sw-cms-preview-jv-button-link',
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
                        value: 'link',
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