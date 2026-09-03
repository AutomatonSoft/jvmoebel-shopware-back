import template from './aftercool-import.html.twig';

const { Mixin } = Shopware;

export default {
    template,

    inject: ['acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            factories: [],
            factoryId: null,
            loadingFactories: false,
            starting: false,
            run: null,
            errors: [],
            errorsTotal: 0,
            errorsPage: 1,
            errorsLimit: 50,
            polling: null,
            httpClient: null,
            preview: [], previewTotal: 0, factoryTotal: null, previewPage: 1, previewLimit: 25, previewQuery: '', previewLoading: false, previewReady: false, previewRequest: 0, previewDetail: null,
        };
    },

    computed: {
        canStart() {
            return this.acl.can('system.import_export') && Number.isInteger(this.factoryId) && this.previewReady && this.factoryTotal > 0 && !this.starting;
        },
        previewColumns() {
            return [
                { property: 'previewImage', label: this.$t('jv-import.aftercool.image') },
                { property: 'name', label: this.$t('jv-import.aftercool.name') },
                { property: 'ean', label: this.$t('jv-import.aftercool.ean') },
                { property: 'source', label: this.$t('jv-import.aftercool.source') },
                { property: 'manufacturer', label: this.$t('jv-import.aftercool.manufacturer') },
                { property: 'price', label: this.$t('jv-import.aftercool.price') },
                { property: 'stock', label: this.$t('jv-import.aftercool.stock') },
                { property: 'dimensions', label: this.$t('jv-import.aftercool.dimensions') },
                { property: 'weight', label: this.$t('jv-import.aftercool.weight') },
                { property: 'updatedAt', label: this.$t('jv-import.aftercool.updatedAt') },
                { property: 'status', label: this.$t('jv-import.aftercool.status') },
                { property: 'details', label: this.$t('jv-import.aftercool.details') },
            ];
        },
        errorColumns() {
            return [
                { property: 'code', label: this.$t('jv-import.aftercool.errorCode') },
                { property: 'ean', label: this.$t('jv-import.aftercool.errorProduct') },
                { property: 'message', label: this.$t('jv-import.aftercool.errorMessage') },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    watch: {
        factoryId() { this.previewPage = 1; this.previewQuery = ''; this.preview = []; this.previewTotal = 0; this.factoryTotal = null; this.previewDetail = null; this.loadPreview(); },
    },

    methods: {
        async createdComponent() {
            this.httpClient = Shopware.Application.getContainer('init').httpClient;
            await this.loadFactories();
        },

        async loadFactories() {
            this.loadingFactories = true;
            try {
                const response = await this.httpClient.get('/_action/jv-import/aftercool/factories');
                this.factories = response.data.data;
            } catch (error) {
                this.createNotificationError({ message: this.afterCoolError(error, 'jv-import.aftercool.factoriesError') });
            } finally {
                this.loadingFactories = false;
            }
        },

        async start() {
            this.stopPolling();
            this.starting = true;
            try {
                const response = await this.httpClient.post('/_action/jv-import/aftercool/runs', {
                    factoryId: this.factoryId,
                });
                const runId = response.data.data.id;
                await this.loadRun(runId);
                if (this.run?.id === runId && !this.isTerminal(this.run.status)) this.startPolling(runId);
            } catch (error) {
                this.createNotificationError({ message: this.afterCoolError(error, 'jv-import.aftercool.startError') });
            } finally {
                this.starting = false;
            }
        },

        async loadPreview() {
            if (!Number.isInteger(this.factoryId)) { this.preview = []; this.previewTotal = 0; this.factoryTotal = null; this.previewReady = false; return; }
            const request = ++this.previewRequest;
            this.previewLoading = true;
            this.previewReady = false;
            try {
                const response = await this.httpClient.get('/_action/jv-import/aftercool/products', { params: { factoryId: this.factoryId, q: this.previewQuery || undefined, limit: this.previewLimit, offset: (this.previewPage - 1) * this.previewLimit } });
                if (request !== this.previewRequest) return;
                this.preview = response.data.data;
                this.previewTotal = response.data.total;
                if ('' === this.previewQuery) this.factoryTotal = response.data.total;
                this.previewReady = true;
            } catch (error) {
                if (request === this.previewRequest) this.createNotificationError({ message: this.afterCoolError(error, 'jv-import.aftercool.previewError') });
            } finally { if (request === this.previewRequest) this.previewLoading = false; }
        },

        async onPreviewSearch() { this.previewPage = 1; await this.loadPreview(); },
        async onPreviewPageChange({ page }) { this.previewPage = page; await this.loadPreview(); },

        afterCoolError(error, fallbackKey) {
            const detail = error?.response?.data?.errors?.[0]?.detail;
            return typeof detail === 'string' && detail.length > 0 ? detail : this.$t(fallbackKey);
        },

        isTerminal(status) {
            return ['completed', 'completed_with_errors', 'failed'].includes(status);
        },

        startPolling(runId) {
            this.stopPolling();
            this.polling = window.setInterval(async () => {
                try {
                    await this.loadRun(runId);
                } catch (error) {
                    this.stopPolling();
                    this.createNotificationError({ message: this.afterCoolError(error, 'jv-import.aftercool.runError') });
                }
            }, 3000);
        },

        stopPolling() {
            if (this.polling !== null) window.clearInterval(this.polling);
            this.polling = null;
        },

        async loadRun(id) {
            const response = await this.httpClient.get(`/_action/jv-import/aftercool/runs/${id}`);
            this.run = response.data.data;
            await this.loadErrors(id);
            if (this.isTerminal(this.run.status)) this.stopPolling();
        },

        async loadErrors(id) {
            const response = await this.httpClient.get(`/_action/jv-import/aftercool/runs/${id}/errors`, {
                params: { limit: this.errorsLimit, offset: (this.errorsPage - 1) * this.errorsLimit },
            });
            this.errors = response.data.data;
            this.errorsTotal = response.data.total;
        },

        async onErrorsPageChange({ page }) {
            this.errorsPage = page;
            await this.loadErrors(this.run.id);
        },
    },
};
