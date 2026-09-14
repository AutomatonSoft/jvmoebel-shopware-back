import template from './jv-seo-redirect-detail.html.twig';
import './jv-seo-redirect-detail.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    inject: ['jvSeoRedirectApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isSaving: false,
            salesChannels: [],
            validationMessages: [],
            draft: {
                type: 'general',
                productId: null,
                channels: [],
            },
        };
    },

    computed: {
        redirectId() {
            return this.$route.params.id ?? null;
        },

        isEditing() {
            return !!this.redirectId;
        },

        canEdit() {
            return this.acl.can('product.editor');
        },

        typeOptions() {
            return [
                { value: 'general', label: this.$t('jv-seo.filters.general') },
                { value: 'product', label: this.$t('jv-seo.filters.product') },
            ];
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;
            try {
                const channelsResponse = await this.jvSeoRedirectApiService.salesChannels();
                this.salesChannels = channelsResponse.data.data ?? [];

                if (this.isEditing) {
                    const response = await this.jvSeoRedirectApiService.detail(this.redirectId);
                    this.hydrate(response.data.data);
                } else {
                    const productId = this.$route.query.productId ?? null;
                    this.draft.type = productId ? 'product' : 'general';
                    this.draft.productId = productId;
                    this.draft.channels = this.salesChannels.map((channel, index) => this.emptyChannel(channel, index === 0));
                    if (productId) await this.loadProductTargets();
                }
            } catch (error) {
                this.createNotificationError({ message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        hydrate(redirect) {
            const existing = new Map(redirect.channels.map((channel) => [channel.salesChannelId, channel]));
            this.draft = {
                type: redirect.type,
                productId: redirect.productId,
                channels: this.salesChannels.map((salesChannel) => {
                    const channel = existing.get(salesChannel.id);
                    if (!channel) return this.emptyChannel(salesChannel, false);
                    return {
                        id: channel.id,
                        salesChannelId: salesChannel.id,
                        salesChannelName: salesChannel.name,
                        enabled: true,
                        targetUrl: channel.targetUrl ?? '',
                        targetPreview: redirect.type === 'product' ? channel.targetUrl : null,
                        sources: channel.sources.map((source) => ({ ...source, localKey: source.id })),
                    };
                }),
            };
        },

        emptyChannel(salesChannel, enabled) {
            return {
                id: null,
                salesChannelId: salesChannel.id,
                salesChannelName: salesChannel.name,
                enabled,
                targetUrl: '',
                targetPreview: null,
                sources: [{ id: null, url: '', localKey: Utils.createId() }],
            };
        },

        async onProductChange() {
            await this.loadProductTargets();
        },

        async onTypeChange() {
            this.validationMessages = [];
            if (this.draft.type === 'general') {
                this.draft.productId = null;
                this.draft.channels.forEach((channel) => { channel.targetPreview = null; });
            } else if (this.draft.productId) {
                await this.loadProductTargets();
            }
        },

        async loadProductTargets() {
            this.draft.channels.forEach((channel) => { channel.targetPreview = null; });
            if (!this.draft.productId) return;
            try {
                const response = await this.jvSeoRedirectApiService.productTargets(this.draft.productId);
                const targets = new Map((response.data.data ?? []).map((target) => [target.salesChannelId, target.targetUrl]));
                this.draft.channels.forEach((channel) => { channel.targetPreview = targets.get(channel.salesChannelId) ?? null; });
            } catch (error) {
                this.createNotificationError({ message: error.message });
            }
        },

        addSource(channel) {
            channel.sources.push({ id: null, url: '', localKey: Utils.createId() });
        },

        removeSource(channel, index) {
            if (channel.sources.length > 1) channel.sources.splice(index, 1);
        },

        validUrl(value) {
            if (typeof value !== 'string' || !/^https?:\/\//i.test(value) || /\s/.test(value)) return false;
            try {
                const url = new URL(value);
                return ['http:', 'https:'].includes(url.protocol) && !!url.hostname && !url.username && !url.password && !url.hash;
            } catch {
                return false;
            }
        },

        validate() {
            const messages = [];
            const enabled = this.draft.channels.filter((channel) => channel.enabled);
            if (this.draft.type === 'product' && !this.draft.productId) {
                messages.push(this.$t('jv-seo.validation.productRequired'));
            }
            if (enabled.length === 0) messages.push(this.$t('jv-seo.validation.channelRequired'));
            enabled.forEach((channel) => {
                if (this.draft.type === 'general' && !this.validUrl(channel.targetUrl)) {
                    messages.push(this.$t('jv-seo.validation.invalidTarget', { channel: channel.salesChannelName }));
                }
                if (channel.sources.length === 0) {
                    messages.push(this.$t('jv-seo.validation.sourceRequired', { channel: channel.salesChannelName }));
                }
                channel.sources.forEach((source) => {
                    if (!this.validUrl(source.url)) {
                        messages.push(this.$t('jv-seo.validation.invalidSource', { channel: channel.salesChannelName }));
                    }
                });
            });
            this.validationMessages = [...new Set(messages)];
            return messages.length === 0;
        },

        payload() {
            return {
                type: this.draft.type,
                productId: this.draft.type === 'product' ? this.draft.productId : null,
                channels: this.draft.channels.map((channel) => ({
                    id: channel.id,
                    salesChannelId: channel.salesChannelId,
                    enabled: channel.enabled,
                    targetUrl: this.draft.type === 'general' ? channel.targetUrl : null,
                    sources: channel.sources.map((source) => ({ id: source.id, url: source.url })),
                })),
            };
        },

        async save() {
            if (!this.validate()) return;
            this.isSaving = true;
            try {
                const response = this.isEditing
                    ? await this.jvSeoRedirectApiService.update(this.redirectId, this.payload())
                    : await this.jvSeoRedirectApiService.create(this.payload());
                const id = response.data.data.id;
                this.createNotificationSuccess({ message: this.$t('jv-seo.notifications.saved') });
                if (!this.isEditing) {
                    await this.$router.replace({ name: 'jv.seo.detail', params: { id } });
                }
                this.hydrate(response.data.data);
            } catch (error) {
                const errors = error?.response?.data?.errors ?? [];
                this.validationMessages = errors.map((item) => item.detail).filter(Boolean);
                this.createNotificationError({ message: this.validationMessages[0] ?? error.message });
            } finally {
                this.isSaving = false;
            }
        },
    },
};
