import './page/jv-catalog-category-attributes';

const { Module } = Shopware;

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
