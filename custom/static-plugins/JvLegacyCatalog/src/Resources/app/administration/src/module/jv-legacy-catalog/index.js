import legacyCatalogIndex from './page/jv-legacy-catalog-index';
import legacyCatalogCategoryDetail from './page/jv-legacy-catalog-category-detail';
import legacyCatalogDetails from './component/jv-legacy-catalog-details';

Shopware.Component.register('jv-legacy-catalog-index', legacyCatalogIndex);
Shopware.Component.register('jv-legacy-catalog-category-detail', legacyCatalogCategoryDetail);
Shopware.Component.register('jv-legacy-catalog-details', legacyCatalogDetails);

Shopware.Module.register('jv-legacy-catalog', {
    type: 'plugin',
    name: 'JvLegacyCatalog',
    title: 'jv-legacy-catalog.general.title',
    description: 'jv-legacy-catalog.general.description',
    color: '#52667a',
    icon: 'regular-folder-open',

    routes: {
        index: {
            component: 'jv-legacy-catalog-index',
            path: '',
            meta: {
                privilege: 'jv_legacy_catalog:read',
            },
        },
        detail: {
            component: 'jv-legacy-catalog-category-detail',
            path: 'detail/:sourceId/:categoryId',
            meta: {
                privilege: 'jv_legacy_catalog:read',
                parentPath: 'jv.legacy.catalog.index',
            },
            props: {
                default(route) {
                    return {
                        sourceId: route.params.sourceId,
                        categoryId: route.params.categoryId,
                    };
                },
            },
        },
    },

    navigation: [{
        id: 'jv-legacy-catalog',
        label: 'jv-legacy-catalog.general.title',
        color: '#52667a',
        icon: 'regular-folder-open',
        path: 'jv.legacy.catalog.index',
        parent: 'sw-catalogue',
        privilege: 'jv_legacy_catalog:read',
        position: 120,
    }],
});
