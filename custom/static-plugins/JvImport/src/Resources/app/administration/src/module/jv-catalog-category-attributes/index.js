import './page/jv-catalog-category-attributes';

const { Module } = Shopware;

const categoryModule = Module.getModuleByName('sw-category');
categoryModule.routes.detail.children.importAttributes = {
    component: 'jv-catalog-category-attributes',
    path: 'import-attributes',
    meta: {
        parentPath: 'sw.category.index',
        privilege: 'category.viewer',
    },
};
