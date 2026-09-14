import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import './module/jv-seo';
import './extension/sw-product-detail-seo';
import JvSeoRedirectApiService from './service/jv-seo-redirect.api.service';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Application.addServiceProvider('jvSeoRedirectApiService', () => {
    return new JvSeoRedirectApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
