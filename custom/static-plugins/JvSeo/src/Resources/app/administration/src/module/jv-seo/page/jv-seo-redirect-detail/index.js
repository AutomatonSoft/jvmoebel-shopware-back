import template from './jv-seo-redirect-detail.html.twig';
import './jv-seo-redirect-detail.scss';

const { Mixin, Utils } = Shopware;
const { Criteria } = Shopware.Data;

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
                categoryId: null,
                landingPageId: null,
                mediaId: null,
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
            return this.acl.can(this.isEditing ? 'jv_seo_redirect:update' : 'jv_seo_redirect:create');
        },

        typeOptions() {
            return [
                { value: 'general', label: this.$t('jv-seo.filters.general') },
                { value: 'product', label: this.$t('jv-seo.filters.product') },
                { value: 'category', label: this.$t('jv-seo.filters.category') },
                { value: 'pages', label: this.$t('jv-seo.filters.pages') },
                { value: 'image', label: this.$t('jv-seo.filters.image') },
            ];
        },

        imageCriteria() {
            return new Criteria(1, 25)
                .addFilter(Criteria.prefix('mimeType', 'image/'))
                .addFilter(Criteria.equals('private', false));
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
                    const categoryId = productId ? null : this.$route.query.categoryId ?? null;
                    const landingPageId = productId || categoryId ? null : this.$route.query.landingPageId ?? null;
                    const mediaId = productId || categoryId || landingPageId ? null : this.$route.query.mediaId ?? null;
                    this.draft.type = productId ? 'product' : categoryId ? 'category' : landingPageId ? 'pages' : mediaId ? 'image' : 'general';
                    this.draft.productId = productId;
                    this.draft.categoryId = categoryId;
                    this.draft.landingPageId = landingPageId;
                    this.draft.mediaId = mediaId;
                    this.draft.channels = this.salesChannels.map((channel, index) => this.emptyChannel(channel, index === 0));
                    if (productId) await this.loadProductTargets();
                    if (categoryId) await this.loadCategoryTargets();
                    if (landingPageId) await this.loadLandingPageTargets();
                    if (mediaId) await this.loadImageTargets();
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
                categoryId: redirect.categoryId,
                landingPageId: redirect.landingPageId,
                mediaId: redirect.mediaId,
                channels: this.salesChannels.map((salesChannel) => {
                    const channel = existing.get(salesChannel.id);
                    if (!channel) return this.emptyChannel(salesChannel, false);
                    return {
                        id: channel.id,
                        salesChannelId: salesChannel.id,
                        salesChannelName: salesChannel.name,
                        enabled: true,
                        targetUrl: channel.targetUrl ?? '',
                        targetPreview: redirect.type === 'general' ? null : channel.targetUrl,
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

        async onCategoryChange() {
            await this.loadCategoryTargets();
        },

        async onImageChange() {
            await this.loadImageTargets();
        },

        async onLandingPageChange() {
            await this.loadLandingPageTargets();
        },

        async onTypeChange() {
            this.validationMessages = [];
            if (this.draft.type === 'general') {
                this.draft.productId = null;
                this.draft.categoryId = null;
                this.draft.landingPageId = null;
                this.draft.mediaId = null;
                this.draft.channels.forEach((channel) => { channel.targetPreview = null; });
            } else if (this.draft.type === 'product') {
                this.draft.categoryId = null;
                this.draft.landingPageId = null;
                this.draft.mediaId = null;
                if (this.draft.productId) await this.loadProductTargets();
            } else if (this.draft.type === 'category') {
                this.draft.productId = null;
                this.draft.landingPageId = null;
                this.draft.mediaId = null;
                if (this.draft.categoryId) await this.loadCategoryTargets();
            } else if (this.draft.type === 'pages') {
                this.draft.productId = null;
                this.draft.categoryId = null;
                this.draft.mediaId = null;
                if (this.draft.landingPageId) await this.loadLandingPageTargets();
            } else {
                this.draft.productId = null;
                this.draft.categoryId = null;
                this.draft.landingPageId = null;
                if (this.draft.mediaId) await this.loadImageTargets();
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

        async loadCategoryTargets() {
            this.draft.channels.forEach((channel) => { channel.targetPreview = null; });
            if (!this.draft.categoryId) return;
            try {
                const response = await this.jvSeoRedirectApiService.categoryTargets(this.draft.categoryId);
                const targets = new Map((response.data.data ?? []).map((target) => [target.salesChannelId, target.targetUrl]));
                this.draft.channels.forEach((channel) => { channel.targetPreview = targets.get(channel.salesChannelId) ?? null; });
            } catch (error) {
                this.createNotificationError({ message: error.message });
            }
        },

        async loadImageTargets() {
            this.draft.channels.forEach((channel) => { channel.targetPreview = null; });
            if (!this.draft.mediaId) return;
            try {
                const response = await this.jvSeoRedirectApiService.imageTargets(this.draft.mediaId);
                const targets = new Map((response.data.data ?? []).map((target) => [target.salesChannelId, target.targetUrl]));
                this.draft.channels.forEach((channel) => { channel.targetPreview = targets.get(channel.salesChannelId) ?? null; });
            } catch (error) {
                this.createNotificationError({ message: error.message });
            }
        },

        async loadLandingPageTargets() {
            this.draft.channels.forEach((channel) => { channel.targetPreview = null; });
            if (!this.draft.landingPageId) return;
            try {
                const response = await this.jvSeoRedirectApiService.landingPageTargets(this.draft.landingPageId);
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
            if (this.draft.type === 'category' && !this.draft.categoryId) {
                messages.push(this.$t('jv-seo.validation.categoryRequired'));
            }
            if (this.draft.type === 'pages' && !this.draft.landingPageId) {
                messages.push(this.$t('jv-seo.validation.landingPageRequired'));
            }
            if (this.draft.type === 'image' && !this.draft.mediaId) {
                messages.push(this.$t('jv-seo.validation.imageRequired'));
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
                categoryId: this.draft.type === 'category' ? this.draft.categoryId : null,
                landingPageId: this.draft.type === 'pages' ? this.draft.landingPageId : null,
                mediaId: this.draft.type === 'image' ? this.draft.mediaId : null,
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
