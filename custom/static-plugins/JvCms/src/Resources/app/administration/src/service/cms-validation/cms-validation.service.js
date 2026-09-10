const DEFAULT_ERROR_CODE = 'JV_CMS_VALIDATION_ERROR';

export default class CmsValidationService {
    constructor() {
        this.rules = new Map();
    }

    register(elementType, rules) {
        const registeredRules = this.rules.get(elementType) ?? [];
        this.rules.set(elementType, [...registeredRules, ...rules]);
    }

    validateElement(element) {
        const rules = this.rules.get(element?.type) ?? [];

        return rules.flatMap((rule) => {
            const errors = rule(element);
            if (!Array.isArray(errors)) {
                return [];
            }

            return errors.map((error) => ({
                code: error.code ?? DEFAULT_ERROR_CODE,
                fieldPath: error.fieldPath,
                blockPath: error.blockPath ?? null,
                message: error.message,
                parameters: error.parameters ?? {},
                presentation: error.presentation ?? 'field',
                blockSave: error.blockSave === true,
            }));
        });
    }

    validateBlock(block) {
        const errors = [];

        (block?.slots ?? []).forEach((element) => {
            this.validateElement(element).forEach((error) => {
                errors.push({
                    ...error,
                    blockId: block.id,
                    elementId: element.id,
                    elementType: element.type,
                });
            });
        });

        return errors;
    }

    validatePage(page) {
        const errors = [];

        (page?.sections ?? []).forEach((section) => {
            (section.blocks ?? []).forEach((block) => {
                this.validateBlock(block).forEach((error) => {
                    errors.push({
                        ...error,
                        sectionId: section.id,
                    });
                });
            });
        });

        return errors;
    }

    hasBlockErrors(block) {
        return this.validateBlock(block).length > 0;
    }

    canSave(page) {
        return !this.validatePage(page).some((error) => error.blockSave);
    }
}
