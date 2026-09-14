/** Registers CMS element `jv-social-block`. */
import validationRules from './validation';

Shopware.Component.register('sw-cms-el-preview-jv-social-block', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-jv-social-block', () => import('./config'));
Shopware.Component.register('sw-cms-el-jv-social-block', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'jv-social-block',
    label: 'cms.elements.jv-social-block.label',
    component: 'sw-cms-el-jv-social-block',
    configComponent: 'sw-cms-el-config-jv-social-block',
    previewComponent: 'sw-cms-el-preview-jv-social-block',
    defaultConfig: {
        title: { source: 'static', value: '' },
        items: { source: 'static', value: [] },
    },
});

Shopware.Service('jvCmsValidationService').register('jv-social-block', validationRules);
