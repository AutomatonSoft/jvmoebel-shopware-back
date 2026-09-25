import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import './module/jv-legacy-catalog';
import JvLegacyCatalogApiService from './service/jv-legacy-catalog.api.service';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Application.addServiceProvider('jvLegacyCatalogApiService', () => new JvLegacyCatalogApiService(
    Shopware.Application.getContainer('init').httpClient,
    Shopware.Service('loginService'),
));
