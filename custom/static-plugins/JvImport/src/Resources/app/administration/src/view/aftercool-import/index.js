import template from './aftercool-import.html.twig';

export default {
    template,

    inject: ['acl'],

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
        };
    },

    computed: {
        canStart() {
            return this.acl.can('system.import_export') && Number.isInteger(this.factoryId) && !this.starting;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        window.clearInterval(this.polling);
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
            } catch {
                this.createNotificationError({ message: this.$t('jv-import.aftercool.factoriesError') });
            } finally {
                this.loadingFactories = false;
            }
        },

        async start() {
            this.starting = true;
            try {
                const response = await this.httpClient.post('/_action/jv-import/aftercool/runs', {
                    factoryId: this.factoryId,
                });
                await this.loadRun(response.data.data.id);
                this.polling = window.setInterval(() => this.loadRun(this.run.id), 3000);
            } catch {
                this.createNotificationError({ message: this.$t('jv-import.aftercool.startError') });
            } finally {
                this.starting = false;
            }
        },

        async loadRun(id) {
            const response = await this.httpClient.get(`/_action/jv-import/aftercool/runs/${id}`);
            this.run = response.data.data;
            await this.loadErrors(id);
            if (['completed', 'completed_with_errors', 'failed'].includes(this.run.status)) {
                window.clearInterval(this.polling);
                this.polling = null;
            }
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
