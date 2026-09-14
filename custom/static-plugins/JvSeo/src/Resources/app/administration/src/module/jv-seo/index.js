import redirectList from './page/jv-seo-redirect-list';
import redirectDetail from './page/jv-seo-redirect-detail';
import '../../component/jv-seo-product-redirects';

Shopware.Component.register('jv-seo-redirect-list', redirectList);
Shopware.Component.register('jv-seo-redirect-detail', redirectDetail);

Shopware.Module.register('jv-seo', {
    type: 'plugin',
    name: 'JvSeo',
    title: 'jv-seo.general.title',
    description: 'jv-seo.general.description',
    color: '#52667a',
    icon: 'regular-search',

    routes: {
        index: {
            component: 'jv-seo-redirect-list',
            path: 'redirects',
            meta: { privilege: 'product.viewer' },
        },
        create: {
            component: 'jv-seo-redirect-detail',
            path: 'redirects/create',
            meta: { parentPath: 'jv.seo.index', privilege: 'product.editor' },
        },
        detail: {
            component: 'jv-seo-redirect-detail',
            path: 'redirects/:id',
            meta: { parentPath: 'jv.seo.index', privilege: 'product.viewer' },
        },
    },

    navigation: [
        {
            id: 'jv-seo',
            label: 'jv-seo.general.title',
            color: '#52667a',
            icon: 'regular-search',
            privilege: 'product.viewer',
            position: 85,
        },
        {
            id: 'jv-seo-redirects',
            label: 'jv-seo.redirects.title',
            path: 'jv.seo.index',
            parent: 'jv-seo',
            privilege: 'product.viewer',
            position: 10,
        },
    ],
});
