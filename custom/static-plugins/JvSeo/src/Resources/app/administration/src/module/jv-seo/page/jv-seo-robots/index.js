import template from './jv-seo-robots.html.twig';

const { Mixin } = Shopware;
const unsupportedControlCharacters = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F-\u009F]/g;
const maxRobotsBytes = 32768;

export default {
    template,

    inject: ['acl', 'jvSeoRobotsApiService'],

    props: {
        searchTerm: {
            type: String,
            default: '',
        },
    },

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            channels: [],
            salesChannelId: null,
            content: '',
            run: null,
            isLoading: false,
            isPublishing: false,
            polling: null,
            publicationRows: [],
            publicationLoading: false,
        };
    },

    computed: {
        channelOptions() {
            return this.channels.map((channel) => ({
                value: channel.id,
                label: channel.name + ' — ' + channel.hosts.map((entry) => entry.host).join(', '),
            }));
        },

        contentSize() {
            return new TextEncoder().encode(this.content).byteLength;
        },

        isTerminal() {
            return ['published', 'failed'].includes(this.run?.status);
        },

        publicRobotsUrls() {
            return this.run?.publicationResult?.publications ?? [];
        },

        publicationColumns() {
            return [
                { property: 'salesChannelName', label: this.$t('jv-seo.robots.salesChannel'), primary: true },
                { property: 'publicRobotsUrl', label: this.$t('jv-seo.robots.file') },
                { property: 'publishedAtLabel', label: this.$t('jv-seo.robots.publishedAt') },
            ];
        },

        runInProgress() {
            return ['pending', 'running'].includes(this.run?.status);
        },

        canPublish() {
            return this.acl.can('jv_seo_robots:write')
                && Boolean(this.salesChannelId)
                && !this.isLoading
                && !this.isPublishing
                && !this.runInProgress
                && this.contentSize <= maxRobotsBytes;
        },
    },

    created() {
        this.loadSalesChannels();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        async loadSalesChannels() {
            if (!this.acl.can('jv_seo_robots:read')) return;
            this.isLoading = true;
            try {
                const response = await this.jvSeoRobotsApiService.salesChannels();
                this.channels = response.data.data ?? [];
                if (this.channels.length > 0) {
                    this.salesChannelId = this.channels[0].id;
                    await this.loadLatest();
                }
                await this.loadPublicationRows();
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error, 'jv-seo.robots.channelsError') });
            } finally {
                this.isLoading = false;
            }
        },

        async onSalesChannelChange(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.stopPolling();
            this.run = null;
            this.content = '';
            await this.loadLatest();
        },

        async loadLatest() {
            if (!this.salesChannelId) return;
            this.isLoading = true;
            try {
                const response = await this.jvSeoRobotsApiService.latest(this.salesChannelId);
                const data = response.data.data;
                this.content = data?.content ?? '';
                this.run = data?.run ?? null;
                if (this.run && !this.isTerminal) this.startPolling(this.run.id);
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error, 'jv-seo.robots.loadError') });
            } finally {
                this.isLoading = false;
            }
        },

        async loadPublicationRows() {
            if (!this.acl.can('jv_seo_robots:read')) return;
            this.publicationLoading = true;
            try {
                const channelById = new Map(this.channels.map((channel) => [channel.id, channel]));
                const latestPublications = new Map();
                const channelsWithPublications = new Set();
                const expectedFiles = new Set(this.channels.flatMap((channel) =>
                    channel.hosts.map((entry) => [channel.id, 'https://' + entry.host + '/robots.txt'].join(':')),
                ));
                const pageSize = 100;
                let offset = 0;
                let total = 0;

                do {
                    const response = await this.jvSeoRobotsApiService.publications({ limit: pageSize, offset });
                    const runs = response.data.data ?? [];
                    total = response.data.total ?? offset + runs.length;

                    runs.forEach((run) => {
                        (run.publicationResult?.publications ?? []).forEach((publication) => {
                            const channel = channelById.get(publication.salesChannelId ?? run.salesChannelId);
                            if (!channel || typeof publication.publicRobotsUrl !== 'string') return;

                            channelsWithPublications.add(channel.id);
                            const key = [channel.id, publication.publicRobotsUrl].join(':');
                            const publishedAt = Date.parse(publication.publishedAt ?? '') || 0;
                            const previous = latestPublications.get(key);
                            if (previous && previous.publishedAt >= publishedAt) return;

                            latestPublications.set(key, {
                                id: publication.publicationId,
                                salesChannelId: channel.id,
                                salesChannelName: channel.name,
                                publicRobotsUrl: publication.publicRobotsUrl,
                                publishedAt,
                                publishedAtLabel: publishedAt > 0 ? new Date(publishedAt).toLocaleString() : '—',
                            });
                        });
                    });

                    offset += runs.length;
                } while (offset < total && [...expectedFiles].some((key) => !latestPublications.has(key)));

                channelById.forEach((channel) => {
                    if (channelsWithPublications.has(channel.id)) return;
                    latestPublications.set(channel.id + '-empty', {
                        id: channel.id + '-empty',
                        salesChannelId: channel.id,
                        salesChannelName: channel.name,
                        publicRobotsUrl: null,
                        publishedAt: 0,
                        publishedAtLabel: '—',
                    });
                });

                this.publicationRows = [...latestPublications.values()].sort((left, right) => {
                    const channelOrder = left.salesChannelName.localeCompare(right.salesChannelName);
                    return channelOrder || (left.publicRobotsUrl ?? '').localeCompare(right.publicRobotsUrl ?? '');
                });
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error, 'jv-seo.robots.publicationsError') });
            } finally {
                this.publicationLoading = false;
            }
        },

        onContentInput(value) {
            this.content = String(value ?? '').replace(unsupportedControlCharacters, '');
        },

        async publish() {
            if (!this.canPublish) return;
            this.stopPolling();
            this.isPublishing = true;
            try {
                const response = await this.jvSeoRobotsApiService.publish(this.salesChannelId, this.content);
                await this.loadRun(response.data.data.id);
                if (!this.isTerminal) this.startPolling(response.data.data.id);
            } catch (error) {
                this.createNotificationError({ message: this.errorMessage(error, 'jv-seo.robots.publishError') });
            } finally {
                this.isPublishing = false;
            }
        },

        async loadRun(runId) {
            const response = await this.jvSeoRobotsApiService.getRun(runId);
            this.run = response.data.data;
            if (this.isTerminal) {
                this.stopPolling();
                await this.loadPublicationRows();
            }
        },

        startPolling(runId) {
            this.stopPolling();
            this.polling = window.setInterval(async () => {
                try {
                    await this.loadRun(runId);
                } catch (error) {
                    this.stopPolling();
                    this.createNotificationError({ message: this.errorMessage(error, 'jv-seo.robots.statusError') });
                }
            }, 1500);
        },

        stopPolling() {
            if (this.polling !== null) window.clearInterval(this.polling);
            this.polling = null;
        },

        errorMessage(error, fallbackKey) {
            const detail = error?.response?.data?.errors?.[0]?.detail;
            return typeof detail === 'string' && detail.length > 0 ? detail : this.$t(fallbackKey);
        },
    },
};
