const isFilledString = (value) => typeof value === 'string' && Boolean(value.trim());

export default [
    (element) => {
        const items = element?.config?.items?.value;
        if (!Array.isArray(items)) {
            return [];
        }

        if (items.length === 0) {
            return [{
                code: 'JV_CMS_SOCIAL_BLOCK_ITEM_REQUIRED',
                fieldPath: 'items',
                blockPath: 'items',
                message: 'cms.elements.jv-social-block.config.items.required',
                presentation: 'block',
            }];
        }

        return items.flatMap((item, itemIndex) => {
            if (!item || typeof item !== 'object' || Array.isArray(item) || isFilledString(item.url)) {
                return [];
            }

            const blockPath = `items.${itemIndex}`;

            return [{
                code: 'JV_CMS_SOCIAL_BLOCK_URL_REQUIRED',
                fieldPath: `${blockPath}.url`,
                blockPath,
                message: 'cms.elements.jv-social-block.config.items.url.required',
            }];
        });
    },
];
