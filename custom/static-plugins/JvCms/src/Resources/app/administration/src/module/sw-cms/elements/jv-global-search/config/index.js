import template from './sw-cms-el-config-jv-global-search.html.twig';

const { Mixin } = Shopware;

const DEFAULTS = {
    searchPlaceholder: { source: 'static', value: '' },
    suggestMinChars: { source: 'static', value: 3 },
    suggestLimit: { source: 'static', value: 10 },
    historyMaxItems: { source: 'static', value: 8 },
};

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-global-search');
        this.ensureConfigShape();
        this.normalizeConfig();
    },

    methods: {
        onUpdate() {
            this.ensureConfigShape();
            this.normalizeConfig();
            this.$emit('element-update', this.element);
        },

        /**
         * Persisted layouts may miss keys or store non-objects after copy/paste / legacy saves.
         */
        ensureConfigShape() {
            if (!this.element.config || typeof this.element.config !== 'object') {
                this.element.config = {};
            }

            Object.keys(DEFAULTS).forEach((key) => {
                const current = this.element.config[key];
                if (!current || typeof current !== 'object' || Array.isArray(current)) {
                    this.element.config[key] = {
                        source: DEFAULTS[key].source,
                        value: DEFAULTS[key].value,
                    };
                    return;
                }

                if (!Object.prototype.hasOwnProperty.call(current, 'source') || !current.source) {
                    current.source = 'static';
                }
                if (!Object.prototype.hasOwnProperty.call(current, 'value')) {
                    current.value = DEFAULTS[key].value;
                }
            });
        },

        normalizeConfig() {
            const placeholder = this.element.config.searchPlaceholder.value;
            this.element.config.searchPlaceholder.value =
                typeof placeholder === 'string' ? placeholder : '';

            this.element.config.suggestMinChars.value = this.normalizeNumber(
                this.element.config.suggestMinChars.value,
                3,
                0,
                10,
                false,
            );
            this.element.config.suggestLimit.value = this.normalizeNumber(
                this.element.config.suggestLimit.value,
                10,
                1,
                20,
                true,
            );
            this.element.config.historyMaxItems.value = this.normalizeNumber(
                this.element.config.historyMaxItems.value,
                8,
                0,
                20,
                false,
            );
        },

        /**
         * Invalid / below min → fallback.
         * Above max → fallback, unless clampHighToMax (suggestLimit 999 → 20).
         * Matches GlobalSearchCmsElementResolver / SPEC-004.
         */
        normalizeNumber(value, fallback, min, max, clampHighToMax) {
            if (value === null || value === undefined || value === '') {
                return fallback;
            }
            const parsed = Number.parseInt(String(value), 10);
            if (Number.isNaN(parsed)) {
                return fallback;
            }
            if (parsed < min) {
                return fallback;
            }
            if (parsed > max) {
                return clampHighToMax ? max : fallback;
            }
            return parsed;
        },
    },
};
