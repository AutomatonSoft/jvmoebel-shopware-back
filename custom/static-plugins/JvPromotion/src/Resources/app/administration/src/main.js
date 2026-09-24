import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import './extension/sw-promotion-v2-detail';
import jvPromotionAftercoolTargeting from './view/jv-promotion-aftercool-targeting';
import JvPromotionApiService from './service/jv-promotion.api.service';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Component.register('jv-promotion-aftercool-targeting', jvPromotionAftercoolTargeting);

Shopware.Application.addServiceProvider('jvPromotionApiService', () => {
    return new JvPromotionApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

const { Module } = Shopware;
const promotionModule = Module.getModuleRegistry().get('sw-promotion-v2');

if (!promotionModule) {
    console.warn('[JvPromotion] sw-promotion-v2 module is not registered; AfterCool tab was not added.');
} else {
    const detailRoute = promotionModule.routes.get('sw.promotion.v2.detail');

    if (!detailRoute || !Array.isArray(detailRoute.children)) {
        console.warn('[JvPromotion] sw.promotion.v2.detail route is not available; AfterCool tab was not added.');
    } else {
        const aftercoolRoute = {
            name: 'sw.promotion.v2.detail.aftercool',
            path: `${detailRoute.path}/aftercool`,
            component: 'jv-promotion-aftercool-targeting',
            meta: {
                parentPath: 'sw.promotion.v2.index',
                privilege: 'promotion.viewer',
            },
            isChildren: true,
            routeKey: 'aftercool',
        };

        promotionModule.manifest.routes.detail.children.aftercool = {
            component: 'jv-promotion-aftercool-targeting',
            path: 'aftercool',
            meta: aftercoolRoute.meta,
        };

        detailRoute.children.push(aftercoolRoute);
        promotionModule.routes.set(aftercoolRoute.name, aftercoolRoute);
    }
}
