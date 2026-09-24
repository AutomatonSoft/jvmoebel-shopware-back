export default class JvSeoRobotsApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    salesChannels() {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/robots/sales-channels', { headers }));
    }

    latest(salesChannelId) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/robots', {
            headers,
            params: { salesChannelId },
        }));
    }

    publications(params) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/robots/publications', {
            headers,
            params,
        }));
    }

    publish(salesChannelId, content) {
        return this.withAuthentication((headers) => this.httpClient.post('/_action/jv-seo/robots', {
            salesChannelId,
            content,
        }, { headers }));
    }

    getRun(runId) {
        return this.withAuthentication((headers) => this.httpClient.get('/_action/jv-seo/robots/runs/' + runId, { headers }));
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
