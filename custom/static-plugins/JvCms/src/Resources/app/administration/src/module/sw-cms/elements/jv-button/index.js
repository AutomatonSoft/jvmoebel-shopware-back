Shopware.Component.register('sw-cms-el-preview-jv-button', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-button', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-button', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-button',
    label: 'cms.elements.jv-button.label',
    component: 'sw-cms-el-jv-button',
    configComponent: 'sw-cms-el-config-jv-button',
    previewComponent: 'sw-cms-el-preview-jv-button',
    defaultConfig: {
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
});