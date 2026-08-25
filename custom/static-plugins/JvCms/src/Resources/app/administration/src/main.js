import './module/sw-cms/elements/jv-button';
import './module/sw-cms/blocks/jv-button/jv-button-primary';
import './module/sw-cms/blocks/jv-button/jv-button-secondary';
import './module/sw-cms/blocks/jv-button/jv-button-link';
import './module/sw-cms/elements/jv-side-navigation';
import './module/sw-cms/blocks/jv-side-navigation/jv-side-navigation';
import './module/sw-cms/elements/jv-global-search';
import './module/sw-cms/blocks/jv-global-search/jv-global-search';
import './module/sw-cms/elements/jv-hero';
import './module/sw-cms/blocks/jv-hero/jv-hero';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
