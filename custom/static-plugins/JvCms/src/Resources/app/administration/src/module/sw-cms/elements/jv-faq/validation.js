const isFilledString = (value) => typeof value === 'string' && Boolean(value.trim());

export default [
    (element) => {
        const items = element?.config?.items?.value;
        if (!Array.isArray(items)) {
            return [];
        }

        return items.flatMap((item, itemIndex) => {
            if (!item || typeof item !== 'object' || Array.isArray(item)) {
                return [];
            }

            const blockPath = `items.${itemIndex}`;
            const errors = [];

            if (!isFilledString(item.question)) {
                errors.push({
                    code: 'JV_CMS_FAQ_QUESTION_REQUIRED',
                    fieldPath: `${blockPath}.question`,
                    blockPath,
                    message: 'cms.elements.jv-faq.config.items.question.required',
                });
            }

            if (!isFilledString(item.answer)) {
                errors.push({
                    code: 'JV_CMS_FAQ_ANSWER_REQUIRED',
                    fieldPath: `${blockPath}.answer`,
                    blockPath,
                    message: 'cms.elements.jv-faq.config.items.answer.required',
                });
            }

            return errors;
        });
    },
];
