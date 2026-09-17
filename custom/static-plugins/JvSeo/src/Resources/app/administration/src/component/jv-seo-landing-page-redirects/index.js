import template from './jv-seo-landing-page-redirects.html.twig';

export default Shopware.Component.register('jv-seo-landing-page-redirects', {
    template,

    inject: ['jvSeoRedirectApiService', 'acl'],

    props: {
        landingPageId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            rows: [],
            redirectId: null,
            isLoading: false,
        };
    },

    computed: {
        columns() {
            return [
                { property: 'salesChannelName', label: this.$t('jv-seo.list.salesChannels'), primary: true },
                { property: 'targetUrl', label: this.$t('jv-seo.list.target') },
                { property: 'sources', label: this.$t('jv-seo.list.sources') },
            ];
        },
    },

    watch: {
        landingPageId: {
            immediate: true,
            handler() {
                this.load();
            },
        },
    },

    methods: {
        async load() {
            if (!this.landingPageId) return;
            this.isLoading = true;
            try {
                const response = await this.jvSeoRedirectApiService.list({
                    type: 'pages',
                    landingPageId: this.landingPageId,
                    page: 1,
                    limit: 100,
                });
                const redirect = response.data.data?.[0] ?? null;
                this.redirectId = redirect?.id ?? null;
                this.rows = (redirect?.channels ?? []).map((channel) => ({
                    ...channel,
                    sourceUrls: channel.sources.map((source) => source.url),
                }));
            } finally {
                this.isLoading = false;
            }
        },
    },
});
