export default class JvLegacyCatalogApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    salesChannels() {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-legacy-catalog/sales-channels', { headers }));
    }

    sources(salesChannelId) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-legacy-catalog/sources', {
            headers,
            params: { salesChannelId },
        }));
    }

    allCategories(sourceId, params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-legacy-catalog/categories', {
            headers,
            params: { sourceId, all: true, ...params },
        }));
    }

    search(sourceId, term) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-legacy-catalog/search', {
            headers,
            params: { sourceId, term },
        }));
    }

    category(sourceId, categoryId) {
        return this.withAuthentication((headers) => this.httpClient.get(
            '/_action/jv-legacy-catalog/sources/' + sourceId + '/categories/' + categoryId,
            { headers },
        ));
    }

    async withAuthentication(request) {
        try {
            return await request(this.headers());
        } catch (error) {
            if (error?.response?.status !== 401) throw error;
            await this.loginService.refreshToken();
            return request(this.headers());
        }
    }

    headers() {
        return {
            Accept: 'application/json',
            Authorization: 'Bearer ' + this.loginService.getToken(),
            'Content-Type': 'application/json',
        };
    }
}
