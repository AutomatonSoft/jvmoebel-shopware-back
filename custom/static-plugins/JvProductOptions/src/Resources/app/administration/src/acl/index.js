Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'jv_option_template',
    roles: {
        viewer: {
            privileges: [
                'jv_option_template:read',
                'jv_option_template_group:read',
                'jv_option_template_value:read',
                'jv_option_template_product_stream:read',
                'jv_option_template_product:read',
                'product_stream:read',
                'media:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'jv_option_template:update',
                'jv_option_template_group:create',
                'jv_option_template_group:update',
                'jv_option_template_group:delete',
                'jv_option_template_value:create',
                'jv_option_template_value:update',
                'jv_option_template_value:delete',
                'jv_option_template_product_stream:create',
                'jv_option_template_product_stream:delete',
            ],
            dependencies: [
                'jv_option_template.viewer',
            ],
        },
        creator: {
            privileges: [
                'jv_option_template:create',
            ],
            dependencies: [
                'jv_option_template.viewer',
                'jv_option_template.editor',
            ],
        },
        deleter: {
            privileges: [
                'jv_option_template:delete',
            ],
            dependencies: [
                'jv_option_template.viewer',
            ],
        },
    },
});

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'product',
    roles: {
        viewer: {
            privileges: [
                'jv_option_template_product:read',
                'jv_option_template:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'jv_option_template_product:create',
                'jv_option_template_product:update',
                'jv_option_template_product:delete',
            ],
            dependencies: [
                'product.viewer',
            ],
        },
    },
});
