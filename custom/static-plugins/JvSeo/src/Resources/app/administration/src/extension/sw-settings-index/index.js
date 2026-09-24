Shopware.Component.override('sw-settings-index', {
    computed: {
        settingsGroups() {
            const settingsGroups = this.$super('settingsGroups');
            if (!Array.isArray(settingsGroups.seo)) return settingsGroups;

            const itemOrder = {
                'jv-seo-redirects': 0,
                'jv-seo-robots': 1,
                'jv-seo-sitemap': 2,
            };

            return {
                ...settingsGroups,
                seo: [...settingsGroups.seo].sort((left, right) => {
                    const leftOrder = itemOrder[left.id] ?? 3;
                    const rightOrder = itemOrder[right.id] ?? 3;
                    if (leftOrder !== rightOrder) return leftOrder - rightOrder;

                    const leftLabel = this.getLabel(left);
                    const rightLabel = this.getLabel(right);
                    return leftLabel.localeCompare(rightLabel);
                }),
            };
        },
    },
});
