import './page/jv-catalog-category-attributes';
import './page/jv-catalog-category-attributes-overview';

const { Module } = Shopware;

Module.register('jv-catalog-category-attributes', {
    type: 'plugin',
    name: 'JvCatalogCategoryAttributes',
    title: 'jv-catalog-category-attributes.overview.title',
    description: 'jv-catalog-category-attributes.overview.description',

    routes: {
        index: {
            component: 'jv-catalog-category-attributes-overview',
            path: 'index',
            meta: {
                privilege: 'category.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'jv-catalog-category-attributes',
            label: 'jv-catalog-category-attributes.overview.title',
            color: '#9AA8B5',
            icon: 'regular-tag',
            path: 'jv.catalog.category.attributes.index',
            parent: 'sw-catalogue',
            privilege: 'category.viewer',
            position: 110,
        },
    ],
});

const categoryModule = Module.getModuleByEntityName('category');
const categoryDetailRoute = categoryModule?.routes.get('sw.category.detail');

if (categoryDetailRoute) {
    const route = {
        component: 'jv-catalog-category-attributes',
        isChildren: true,
        meta: {
            parentPath: 'sw.category.index',
            privilege: 'category.viewer',
        },
        name: 'sw.category.detail.importAttributes',
        path: '/sw/category/index/:id/import-attributes',
    };

    categoryDetailRoute.children.push(route);
    categoryModule.routes.set(route.name, route);
}
