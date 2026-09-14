import template from './jv-seo-redirect-list.html.twig';
import './jv-seo-redirect-list.scss';

const { Mixin } = Shopware;

export default {
    template,

    inject: ['jvSeoRedirectApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            redirects: [],
            total: 0,
            page: 1,
            limit: 25,
            term: '',
            type: 'all',
            isLoading: false,
        };
    },

    computed: {
        columns() {
            return [
                { property: 'type', label: this.$t('jv-seo.list.type'), width: '130px' },
                { property: 'target', label: this.$t('jv-seo.list.target'), primary: true },
                { property: 'channels', label: this.$t('jv-seo.list.salesChannels') },
                { property: 'sources', label: this.$t('jv-seo.list.sources') },
                { property: 'updatedAt', label: this.$t('jv-seo.list.updatedAt'), width: '160px' },
            ];
        },

        typeOptions() {
            return [
                { value: 'all', label: this.$t('jv-seo.filters.all') },
                { value: 'general', label: this.$t('jv-seo.filters.general') },
                { value: 'product', label: this.$t('jv-seo.filters.product') },
            ];
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;
            try {
                const response = await this.jvSeoRedirectApiService.list({
                    type: this.type,
                    term: this.term,
                    page: this.page,
                    limit: this.limit,
                });
                this.redirects = response.data.data ?? [];
                this.total = response.data.total ?? 0;
            } catch (error) {
                this.createNotificationError({ message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.load();
        },

        onTypeChange() {
            this.page = 1;
            this.load();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.load();
        },

        typeLabel(type) {
            return this.$t(`jv-seo.filters.${type}`);
        },

        targetLabel(item) {
            if (item.type === 'product') {
                return item.productName
                    ? `${item.productName} (${item.productNumber})`
                    : item.productNumber ?? item.productId;
            }
            return item.channels.map((channel) => channel.targetUrl).filter(Boolean).join('\n');
        },

        channelLabel(item) {
            return item.channels.map((channel) => channel.salesChannelName).join(', ');
        },

        sourceUrls(item) {
            return item.channels.flatMap((channel) => channel.sources.map((source) => source.url));
        },
    },
};
