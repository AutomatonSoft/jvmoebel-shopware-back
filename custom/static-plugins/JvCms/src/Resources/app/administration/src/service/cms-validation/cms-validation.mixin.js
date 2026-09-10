export default {
    computed: {
        cmsValidationErrors() {
            return Shopware.Service('jvCmsValidationService').validateElement(this.element);
        },
    },

    methods: {
        cmsValidationFieldError(fieldPath) {
            const error = this.cmsValidationErrors.find((candidate) => candidate.fieldPath === fieldPath);

            return error ? this.cmsValidationShopwareError(error) : null;
        },

        cmsValidationBlockClass(blockPath) {
            return {
                'jv-cms-validation-block--invalid': this.cmsValidationErrors.some(
                    (error) => error.blockPath === blockPath,
                ),
            };
        },

        cmsValidationBlockError(blockPath) {
            const error = this.cmsValidationErrors.find(
                (candidate) => candidate.blockPath === blockPath && candidate.presentation === 'block',
            );

            return error ? this.cmsValidationShopwareError(error) : null;
        },

        cmsValidationShopwareError(error) {
            return {
                code: error.code,
                detail: this.$t(error.message, error.parameters),
                parameters: error.parameters,
            };
        },
    },
};
