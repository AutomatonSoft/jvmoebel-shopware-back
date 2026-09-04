/**
 * Registers CMS element `jv-cart`.
 */
Shopware.Component.register('sw-cms-el-preview-jv-cart', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-cart', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-cart', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-cart',
    label: 'cms.elements.jv-cart.label',
    component: 'sw-cms-el-jv-cart',
    configComponent: 'sw-cms-el-config-jv-cart',
    previewComponent: 'sw-cms-el-preview-jv-cart',
    defaultConfig: {
        headerTrigger: {
            source: 'static',
            value: { label: '', url: '' },
        },
        titleSingular: { source: 'static', value: '' },
        titlePlural: { source: 'static', value: '' },
        loginHint: {
            source: 'static',
            value: { message: '', loginLabel: '', loginUrl: '' },
        },
        services: {
            source: 'static',
            value: {
                title: '',
                postalCode: { label: '', placeholder: '', submitLabel: '' },
                options: [],
            },
        },
        summaryLabels: {
            source: 'static',
            value: {
                titleSingular: '',
                titlePlural: '',
                subtotalLabel: '',
                shippingLabel: '',
                shippingUrl: '',
                totalLabel: '',
                savingsLabel: '',
                checkoutLabel: '',
                checkoutUrl: '',
            },
        },
        promoCode: {
            source: 'static',
            value: {
                title: '',
                expanded: false,
                description: '',
                inputName: '',
                inputLabel: '',
                inputPlaceholder: '',
                submitLabel: '',
            },
        },
        giftCard: {
            source: 'static',
            value: {
                title: '',
                expanded: false,
                description: '',
                inputName: '',
                inputLabel: '',
                inputPlaceholder: '',
                submitLabel: '',
            },
        },
        trust: { source: 'static', value: [] },
    },
});
