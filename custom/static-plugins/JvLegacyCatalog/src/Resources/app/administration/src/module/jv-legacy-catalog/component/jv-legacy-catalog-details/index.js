import template from './jv-legacy-catalog-details.html.twig';
import './jv-legacy-catalog-details.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [Mixin.getByName('notification')],

    props: {
        detail: {
            type: Object,
            default: null,
        },
        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        fullUrl() {
            if (!this.detail?.baseUrl || !Array.isArray(this.detail.path) || this.detail.path.length === 0) {
                return null;
            }

            const urlKeys = this.detail.path.map((item) => item.urlKey);
            if (urlKeys.some((urlKey) => typeof urlKey !== 'string' || urlKey.length === 0)) {
                return null;
            }

            const category = this.detail.path[this.detail.path.length - 1];
            const path = urlKeys
                .map((urlKey) => encodeURI(urlKey).replace(/[?#]/g, (character) => `%${character.charCodeAt(0).toString(16).toUpperCase()}`))
                .join('/');
            const trailingSlash = category.sourceParentId !== 0 && this.detail.hasChildren;

            return this.detail.baseUrl.replace(/\/+$/, '') + '/' + path + (trailingSlash ? '/' : '');
        },
    },

    methods: {
        async copyFullUrl() {
            try {
                await navigator.clipboard.writeText(this.fullUrl);
                this.createNotificationSuccess({ message: this.$t('jv-legacy-catalog.notifications.fullUrlCopied') });
            } catch {
                this.createNotificationError({ message: this.$t('jv-legacy-catalog.errors.copyFullUrl') });
            }
        },

        stringify(value) {
            return JSON.stringify(value, null, 2);
        },

        valueLabel(value) {
            return value === null || value === undefined || value === '' ? '—' : String(value);
        },
    },
};
