import './page/jv-catalog-attribute-mapping-list';

const { Module } = Shopware;

Module.register('jv-catalog-attribute-mapping', {
    type: 'plugin',
    name: 'Catalog attribute mappings',
    title: 'jv-catalog-attribute-mapping.title',
    description: 'jv-catalog-attribute-mapping.description',
    color: '#9AA8B5',
    icon: 'regular-tags',
    routes: {
        index: {
            component: 'jv-catalog-attribute-mapping-list',
            path: 'index',
            meta: { privilege: 'product.editor' },
        },
    },
    navigation: [{
        id: 'jv-catalog-attribute-mapping',
        label: 'jv-catalog-attribute-mapping.title',
        color: '#9AA8B5',
        icon: 'regular-tags',
        path: 'jv.catalog.attribute.mapping.index',
        parent: 'sw-catalogue',
        privilege: 'product.editor',
        position: 120,
    }],
});
