Shopware.Component.register('jv-storefront-settings-index', () => import('./page/jv-storefront-settings-index'));

Shopware.Module.register('jv-storefront-settings', {
    type: 'plugin',
    name: 'jv-storefront-settings',
    title: 'jv-storefront-settings.general.title',
    description: 'jv-storefront-settings.general.description',
    color: '#189eff',
    icon: 'regular-storefront',

    routes: {
        index: {
            component: 'jv-storefront-settings-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'jv.storefront.settings.index',
        icon: 'regular-storefront',
        privilege: 'sales_channel.viewer',
    },
});
