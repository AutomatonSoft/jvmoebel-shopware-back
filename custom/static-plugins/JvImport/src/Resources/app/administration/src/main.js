import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import './extension/sw-product-deliverability-form';
import './extension/sw-product-detail';
import './extension/sw-category-view';
import './module/jv-catalog-category-attributes';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

const { Module } = Shopware;

Module.register('jv-import', {
    type: 'plugin',
    name: 'JvImport',
    title: 'jv-import.general.title',
    description: 'jv-import.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        index: {
            component: 'sw-import-export',
            path: 'index',
            redirect: {
                name: 'sw.import.export.index.import',
            },
            meta: {
                privilege: 'system.import_export',
            },
        },
    },

    navigation: [
        {
            id: 'jv-import',
            label: 'jv-import.general.title',
            color: '#9AA8B5',
            icon: 'regular-cloud-upload',
            path: 'sw.import.export.index.import',
            parent: 'sw-settings',
            privilege: 'system.import_export',
            position: 90,
        },
    ],
});
