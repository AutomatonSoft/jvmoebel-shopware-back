export default class AfterCoolImportApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    getFactories() {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-import/aftercool/factories', { headers }));
    }

    getProducts(params) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-import/aftercool/products', {
            headers,
            params,
        }));
    }

    createRun(factoryId) {
        return this.withAuthentication((headers) => this.httpClient.post('/_action/jv-import/aftercool/runs', { factoryId }, { headers }));
    }

    getRun(runId) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-import/aftercool/runs/${runId}`, { headers }));
    }

    getErrors(runId, params) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-import/aftercool/runs/${runId}/errors`, {
            headers,
            params,
        }));
    }

    async withAuthentication(request) {
        try {
            return await request(this.getBasicHeaders());
        } catch (error) {
            if (error?.response?.status !== 401) throw error;

            await this.loginService.refreshToken();

            return request(this.getBasicHeaders());
        }
    }

    getBasicHeaders() {
        const headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };

        if (typeof Shopware.Context?.api?.languageId === 'string') {
            headers['sw-language-id'] = Shopware.Context.api.languageId;
        }

        return headers;
    }
}
