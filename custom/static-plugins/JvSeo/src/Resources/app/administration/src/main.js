import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import './module/jv-seo';
import './extension/sw-product-detail-seo';
import './extension/sw-category-detail-seo';
import './extension/sw-landing-page-detail-base';
import './extension/sw-media-quickinfo';
import JvSeoRedirectApiService from './service/jv-seo-redirect.api.service';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Application.addServiceProvider('jvSeoRedirectApiService', () => {
    return new JvSeoRedirectApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});
