import redirectList from './page/jv-seo-redirect-list';
import redirectDetail from './page/jv-seo-redirect-detail';
import seoIndex from './page/jv-seo-index';
import robotsEditor from './page/jv-seo-robots';
import '../../component/jv-seo-product-redirects';
import '../../component/jv-seo-category-redirects';
import '../../component/jv-seo-landing-page-redirects';
import '../../component/jv-seo-image-redirects';

Shopware.Component.register('jv-seo-redirect-list', redirectList);
Shopware.Component.register('jv-seo-redirect-detail', redirectDetail);
Shopware.Component.register('jv-seo-index', seoIndex);
Shopware.Component.register('jv-seo-robots', robotsEditor);

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
            path: '',
            children: {
                redirects: {
                    component: 'jv-seo-redirect-list',
                    path: '',
                    meta: { parentPath: 'sw.settings.index', privilege: 'jv_seo_redirect:read' },
                },
                robots: {
                    component: 'jv-seo-robots',
                    path: 'robots',
                    meta: { parentPath: 'sw.settings.index', privilege: 'jv_seo_robots:read' },
                },
                legacyRedirects: {
                    component: 'jv-seo-redirect-list',
                    path: 'redirects',
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

    settingsItem: [
        {
            id: 'jv-seo-redirects',
            name: 'jv-seo-redirects',
            group: 'seo',
            label: 'jv-seo.redirects.title',
            to: 'jv.seo.index.redirects',
            icon: 'regular-search',
            privilege: 'jv_seo_redirect:read',
        },
        {
            id: 'jv-seo-robots',
            name: 'jv-seo-robots',
            group: 'seo',
            label: 'jv-seo.robots.title',
            to: 'jv.seo.index.robots',
            icon: 'regular-file-text',
            privilege: 'jv_seo_robots:read',
        },
        {
            id: 'jv-seo-sitemap',
            name: 'jv-seo-sitemap',
            group: 'seo',
            label: 'sw-settings-sitemap.general.mainMenuItemGeneral',
            to: 'sw.settings.sitemap.index',
            icon: 'regular-map',
            privilege: 'system.system_config',
        },
    ],

    navigation: [
        {
            id: 'jv-seo',
            label: 'jv-seo.general.title',
            color: '#52667a',
            icon: 'regular-search',
            path: 'jv.seo.index.redirects',
            parent: 'sw-settings',
            privilege: 'jv_seo_redirect:read',
            position: 95,
        },
    ],
});
