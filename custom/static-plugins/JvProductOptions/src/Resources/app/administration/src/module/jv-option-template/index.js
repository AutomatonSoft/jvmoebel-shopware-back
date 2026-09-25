const { Module } = Shopware;

Shopware.Component.register('jv-option-template-list', () => import('./page/jv-option-template-list'));
Shopware.Component.register('jv-option-template-detail', () => import('./page/jv-option-template-detail'));

Module.register('jv-option-template', {
    type: 'plugin',
    name: 'jv-option-template',
    title: 'jv-option-template.general.mainMenuItemGeneral',
    description: 'jv-option-template.general.descriptionTextModule',
    color: '#57D9A3',
    icon: 'regular-cog',
    entity: 'jv_option_template',

    routes: {
        index: {
            component: 'jv-option-template-list',
            path: 'index',
            meta: {
                privilege: 'jv_option_template.viewer',
            },
        },
        create: {
            component: 'jv-option-template-detail',
            path: 'create',
            meta: {
                parentPath: 'jv.option.template.index',
                privilege: 'jv_option_template.creator',
            },
        },
        detail: {
            component: 'jv-option-template-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'jv.option.template.index',
                privilege: 'jv_option_template.editor',
            },
        },
    },

    navigation: [
        {
            id: 'jv-option-template',
            label: 'jv-option-template.general.mainMenuItemGeneral',
            parent: 'sw-catalogue',
            path: 'jv.option.template.index',
            position: 50,
            privilege: 'jv_option_template.viewer',
        },
    ],
});
