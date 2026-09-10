import CmsValidationService from './cms-validation.service';
import cmsValidationMixin from './cms-validation.mixin';

Shopware.Application.addServiceProvider('jvCmsValidationService', () => new CmsValidationService());
Shopware.Mixin.register('jv-cms-validation', cmsValidationMixin);
