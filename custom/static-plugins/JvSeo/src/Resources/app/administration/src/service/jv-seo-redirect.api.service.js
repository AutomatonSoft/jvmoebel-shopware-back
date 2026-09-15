export default class JvSeoRedirectApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    list(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/redirects', { headers, params }));
    }

    detail(id) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-seo/redirects/${id}`, { headers }));
    }

    create(payload) {
        return this.withAuthentication((headers) => this.httpClient.post('/_action/jv-seo/redirects', payload, { headers }));
    }

    update(id, payload) {
        return this.withAuthentication((headers) => this.httpClient.put(`/_action/jv-seo/redirects/${id}`, payload, { headers }));
    }

    salesChannels() {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/sales-channels', { headers }));
    }

    productTargets(productId) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-seo/products/${productId}/targets`, { headers }));
    }

    categoryTargets(categoryId) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-seo/categories/${categoryId}/targets`, { headers }));
    }

    imageTargets(mediaId) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-seo/images/${mediaId}/targets`, { headers }));
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
