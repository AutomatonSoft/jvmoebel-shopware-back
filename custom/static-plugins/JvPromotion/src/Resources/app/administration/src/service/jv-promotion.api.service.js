export default class JvPromotionApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    getFactories(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-promotion/factories', { headers, params }));
    }

    getFactoryPrefixes(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-promotion/factory-prefixes', { headers, params }));
    }

    getCollections(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-promotion/collections', { headers, params }));
    }

    getProducts(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-promotion/products', { headers, params }));
    }

    getTargets(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-promotion/targets', { headers, params }));
    }

    preview(payload) {
        return this.withAuthentication((headers) => this.httpClient.post('/_action/jv-promotion/preview', payload, { headers }));
    }

    sync(payload) {
        return this.withAuthentication((headers) => this.httpClient.post('/_action/jv-promotion/sync', payload, { headers }));
    }

    async withAuthentication(request) {
        try {
            return await request(this.getBasicHeaders());
        } catch (error) {
            if (error?.response?.status !== 401) {
                throw error;
            }

            await this.loginService.refreshToken();

            return request(this.getBasicHeaders());
        }
    }

    getBasicHeaders() {
        const headers = {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };

        if (typeof Shopware.Context?.api?.languageId === 'string') {
            headers['sw-language-id'] = Shopware.Context.api.languageId;
        }

        return headers;
    }
}
