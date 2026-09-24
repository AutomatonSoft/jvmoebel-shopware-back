export default class JvSeoSitemapExportApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    salesChannels() {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/sitemap-exports/sales-channels', { headers }));
    }

    create(salesChannelId) {
        return this.withAuthentication((headers) => this.httpClient.post('/_action/jv-seo/sitemap-exports', { salesChannelId }, { headers }));
    }

    get(runId) {
        return this.withAuthentication((headers) => this.httpClient.get(`/_action/jv-seo/sitemap-exports/${runId}`, { headers }));
    }

    list(params = {}) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/sitemap-exports', { headers, params }));
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
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}
