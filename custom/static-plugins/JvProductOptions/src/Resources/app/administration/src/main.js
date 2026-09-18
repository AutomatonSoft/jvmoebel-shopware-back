import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import jvProductOptionTemplateCard from './component/jv-product-option-template-card';
import './acl';
import './extension/sw-product-detail-base';
import './module/jv-option-template';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

Shopware.Component.register('jv-product-option-template-card', jvProductOptionTemplateCard);
