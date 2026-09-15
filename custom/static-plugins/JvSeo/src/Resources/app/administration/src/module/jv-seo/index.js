import redirectList from './page/jv-seo-redirect-list';
import redirectDetail from './page/jv-seo-redirect-detail';
import seoIndex from './page/jv-seo-index';
import '../../component/jv-seo-product-redirects';
import '../../component/jv-seo-category-redirects';

Shopware.Component.register('jv-seo-redirect-list', redirectList);
Shopware.Component.register('jv-seo-redirect-detail', redirectDetail);
Shopware.Component.register('jv-seo-index', seoIndex);

Shopware.Module.register('jv-seo', {
    type: 'plugin',
    name: 'JvSeo',
    title: 'jv-seo.general.title',
    description: 'jv-seo.general.description',
    color: '#52667a',
    icon: 'regular-search',

    routes: {
        index: {
            component: 'jv-seo-index',
            path: 'redirects',
            meta: { parentPath: 'sw.settings.index', privilege: 'jv_seo_redirect:read' },
            children: {
                redirects: {
                    component: 'jv-seo-redirect-list',
                    path: '',
                    meta: { parentPath: 'sw.settings.index', privilege: 'jv_seo_redirect:read' },
                },
            },
        },
        create: {
            component: 'jv-seo-redirect-detail',
            path: 'redirects/create',
            meta: { parentPath: 'jv.seo.index.redirects', privilege: 'jv_seo_redirect:create' },
        },
        detail: {
            component: 'jv-seo-redirect-detail',
            path: 'redirects/:id',
            meta: { parentPath: 'jv.seo.index.redirects', privilege: 'jv_seo_redirect:read' },
        },
    },

    navigation: [
        {
            id: 'jv-seo',
            label: 'jv-seo.general.title',
            color: '#52667a',
            icon: 'regular-search',
            path: 'jv.seo.index',
            parent: 'sw-settings',
            privilege: 'jv_seo_redirect:read',
            position: 95,
        },
    ],
});
