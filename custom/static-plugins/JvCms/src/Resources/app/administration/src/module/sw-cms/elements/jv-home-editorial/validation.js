const isFilledString = (value) => typeof value === 'string' && Boolean(value.trim());

export default [
    (element) => {
        const sections = element?.config?.sections?.value;
        if (!Array.isArray(sections)) {
            return [];
        }

        return sections.flatMap((section, sectionIndex) => {
            if (!section || typeof section !== 'object' || Array.isArray(section)) {
                return [];
            }

            const paragraphs = Array.isArray(section.paragraphs) ? section.paragraphs : [];
            if (paragraphs.some(isFilledString)) {
                return [];
            }

            const blockPath = `sections.${sectionIndex}`;
            if (paragraphs.length === 0) {
                return [{
                    code: 'JV_CMS_HOME_EDITORIAL_PARAGRAPH_REQUIRED',
                    fieldPath: `${blockPath}.paragraphs`,
                    blockPath,
                    message: 'cms.elements.jv-home-editorial.config.sections.paragraphRequired',
                    presentation: 'block',
                }];
            }

            return paragraphs.map((_paragraph, paragraphIndex) => ({
                code: 'JV_CMS_HOME_EDITORIAL_PARAGRAPH_REQUIRED',
                fieldPath: `${blockPath}.paragraphs.${paragraphIndex}`,
                blockPath,
                message: 'cms.elements.jv-home-editorial.config.sections.paragraphRequired',
            }));
        });
    },
];
