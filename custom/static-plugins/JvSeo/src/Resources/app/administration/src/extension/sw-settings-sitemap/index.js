import template from './sw-settings-sitemap.html.twig';

const { Mixin } = Shopware;

Shopware.Component.override('sw-settings-sitemap', {
    template,
    inject: ['acl', 'jvSeoSitemapExportApiService'],
    mixins: [Mixin.getByName('notification')],
    data() {
        return {
            sitemapExportChannels: [],
            sitemapExportSalesChannelId: null,
            sitemapExportRun: null,
            sitemapExportLoading: false,
            sitemapExportPolling: null,
            sitemapPublicationRows: [],
            sitemapPublicationLoading: false,
        };
    },
    computed: {
        canGenerateAndPublishSitemap() { return this.acl.can('jv_seo_sitemap_export:write') && !this.sitemapExportLoading; },
        sitemapExportChannelOptions() { return [{ value: null, label: this.$t('jv-seo.sitemap.allEligible') }, ...this.sitemapExportChannels.map((channel) => ({ value: channel.id, label: channel.name }))]; },
        sitemapExportIsTerminal() { return ['published', 'failed'].includes(this.sitemapExportRun?.status); },
        sitemapExportPublications() { return this.sitemapExportRun?.publicationResult?.publications ?? []; },
        sitemapPublicationColumns() {
            return [
                { property: 'salesChannelName', label: this.$t('jv-seo.sitemap.salesChannel'), primary: true },
                { property: 'publicSitemapUrl', label: this.$t('jv-seo.sitemap.sitemapUrl') },
                { property: 'publishedAtLabel', label: this.$t('jv-seo.sitemap.publishedAt') },
                { property: 'artifactCountLabel', label: this.$t('jv-seo.sitemap.artifactCount') },
            ];
        },
    },
    created() { this.loadSitemapExportChannels(); },
    beforeUnmount() { this.stopSitemapExportPolling(); },
    methods: {
        async loadSitemapExportChannels() {
            if (!this.acl.can('jv_seo_sitemap_export:read')) return;
            try {
                const response = await this.jvSeoSitemapExportApiService.salesChannels();
                this.sitemapExportChannels = response.data.data;
            } catch (error) {
                this.createNotificationError({ message: this.sitemapExportError(error, 'jv-seo.sitemap.channelsError') });
            }
            await this.loadSitemapPublicationRows();
        },
        async createSitemapExport() {
            this.stopSitemapExportPolling(); this.sitemapExportLoading = true;
            try { const response = await this.jvSeoSitemapExportApiService.create(this.sitemapExportSalesChannelId); await this.loadSitemapExportRun(response.data.data.id); if (!this.sitemapExportIsTerminal) this.startSitemapExportPolling(response.data.data.id); }
            catch (error) { this.createNotificationError({ message: this.sitemapExportError(error, 'jv-seo.sitemap.startError') }); }
            finally { this.sitemapExportLoading = false; }
        },
        async loadSitemapExportRun(runId) {
            const response = await this.jvSeoSitemapExportApiService.get(runId);
            this.sitemapExportRun = response.data.data;
            if (this.sitemapExportIsTerminal) {
                this.stopSitemapExportPolling();
                await this.loadSitemapPublicationRows();
            }
        },
        async loadSitemapPublicationRows() {
            this.sitemapPublicationLoading = true;
            try {
                const channelById = new Map(this.sitemapExportChannels.map((channel) => [channel.id, channel]));
                const latestPublications = new Map();
                const channelsWithPublications = new Set();
                const pageSize = 100;
                let offset = 0;
                let total = 0;

                do {
                    const response = await this.jvSeoSitemapExportApiService.list({ limit: pageSize, offset });
                    const runs = response.data.data ?? [];
                    total = response.data.total ?? offset + runs.length;

                    runs.forEach((run) => {
                        const publications = run.publicationResult?.publications ?? [];
                        publications.forEach((publication) => {
                            const channel = channelById.get(publication.salesChannelId);
                            if (!channel || typeof publication.publicSitemapUrl !== 'string') return;

                            channelsWithPublications.add(channel.id);
                            const key = [channel.id, publication.publicSitemapUrl].join(':');
                            const previous = latestPublications.get(key);
                            const publishedAt = Date.parse(publication.publishedAt ?? '') || 0;
                            const previousPublishedAt = Date.parse(previous?.publishedAt ?? '') || 0;
                            if (previous && previousPublishedAt >= publishedAt) return;

                            latestPublications.set(key, {
                                id: publication.publicationId,
                                salesChannelId: channel.id,
                                salesChannelName: channel.name,
                                publicSitemapUrl: publication.publicSitemapUrl,
                                publishedAt,
                                publishedAtLabel: publishedAt > 0 ? new Date(publishedAt).toLocaleString() : '—',
                                artifactCountLabel: Number.isInteger(publication.artifactCount) ? publication.artifactCount : '—',
                            });
                        });
                    });

                    offset += runs.length;
                } while (offset < total && channelsWithPublications.size < channelById.size);

                channelById.forEach((channel) => {
                    if (channelsWithPublications.has(channel.id)) return;
                    latestPublications.set(channel.id + '-empty', {
                        id: channel.id + '-empty',
                        salesChannelId: channel.id,
                        salesChannelName: channel.name,
                        publicSitemapUrl: null,
                        publishedAt: 0,
                        publishedAtLabel: '—',
                        artifactCountLabel: '—',
                    });
                });

                this.sitemapPublicationRows = [...latestPublications.values()].sort((left, right) => {
                    const channelOrder = left.salesChannelName.localeCompare(right.salesChannelName);
                    return channelOrder || (left.publicSitemapUrl ?? '').localeCompare(right.publicSitemapUrl ?? '');
                });
            } catch (error) {
                this.createNotificationError({ message: this.sitemapExportError(error, 'jv-seo.sitemap.publicationsError') });
            } finally {
                this.sitemapPublicationLoading = false;
            }
        },
        startSitemapExportPolling(runId) {
            this.stopSitemapExportPolling();
            this.sitemapExportPolling = window.setInterval(async () => {
                try { await this.loadSitemapExportRun(runId); }
                catch (error) { this.stopSitemapExportPolling(); this.createNotificationError({ message: this.sitemapExportError(error, 'jv-seo.sitemap.runError') }); }
            }, 3000);
        },
        stopSitemapExportPolling() { if (this.sitemapExportPolling !== null) window.clearInterval(this.sitemapExportPolling); this.sitemapExportPolling = null; },
        sitemapExportError(error, fallbackKey) { const detail = error?.response?.data?.errors?.[0]?.detail; return typeof detail === 'string' && detail.length > 0 ? detail : this.$t(fallbackKey); },
    },
});
