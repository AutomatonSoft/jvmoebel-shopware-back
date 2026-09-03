Shopware.Component.register('sw-cms-preview-jv-hero', () => import('./preview'));
Shopware.Component.register('sw-cms-block-jv-hero', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'jv-hero',
    label: 'cms.blocks.jv-hero.label',
    category: 'hero',
    component: 'sw-cms-block-jv-hero',
    previewComponent: 'sw-cms-preview-jv-hero',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        content: {
            type: 'jv-hero',
            default: {
                config: {
                    title: {
                        source: 'static',
                        value: '',
                    },
                    eyebrow: {
                        source: 'static',
                        value: '',
                    },
                    description: {
                        source: 'static',
                        value: '',
                    },
                    imageMedia: {
                        source: 'static',
                        value: null,
                    },
                    primaryLink: {
                        source: 'static',
                        value: {
                            label: '',
                            url: '',
                            size: 'medium',
                        },
                    },
                    secondaryLink: {
                        source: 'static',
                        value: {
                            label: '',
                            url: '',
                            size: 'medium',
                        },
                    },
                },
            },
        },
    },
});
