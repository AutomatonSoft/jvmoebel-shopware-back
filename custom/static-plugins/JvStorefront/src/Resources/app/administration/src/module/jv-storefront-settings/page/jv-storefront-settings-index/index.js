import template from './jv-storefront-settings-index.html.twig';
import './jv-storefront-settings-index.scss';

const { Mixin, Utils } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaving: false,
            salesChannelId: null,
            salesChannel: null,
            customFields: {
                jv_storefront_logo_media_id: null,
                jv_footer_about_eyebrow: '',
                jv_footer_about_title: '',
                jv_footer_about_description: '',
                jv_footer_copyright_text: '',
                jv_footer_revocation_enabled: false,
                jv_footer_revocation_button_label: '',
                jv_footer_revocation_recipient_email: '',
            },
            logoMedia: null,
            logoMediaModalOpen: false,
            iconMediaModalOpen: false,
            iconMediaTarget: null,
            socialLinks: [],
            paymentBadges: [],
            isSocialLinksLoading: false,
            isPaymentBadgesLoading: false,
            socialLinkModalOpen: false,
            paymentBadgeModalOpen: false,
            socialLinkDraft: null,
            paymentBadgeDraft: null,
            isSocialLinkSaving: false,
            isPaymentBadgeSaving: false,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        socialLinkRepository() {
            return this.repositoryFactory.create('jv_storefront_social_link');
        },

        paymentBadgeRepository() {
            return this.repositoryFactory.create('jv_storefront_payment_badge');
        },

        socialLinkGridColumns() {
            return [
                { property: 'label', label: this.$t('jv-storefront-settings.list.label'), primary: true },
                { property: 'url', label: this.$t('jv-storefront-settings.list.url') },
                { property: 'position', label: this.$t('jv-storefront-settings.list.position'), align: 'right' },
                { property: 'active', label: this.$t('jv-storefront-settings.list.active'), align: 'center' },
            ];
        },

        paymentBadgeGridColumns() {
            return [
                { property: 'label', label: this.$t('jv-storefront-settings.list.label'), primary: true },
                { property: 'position', label: this.$t('jv-storefront-settings.list.position'), align: 'right' },
                { property: 'active', label: this.$t('jv-storefront-settings.list.active'), align: 'center' },
            ];
        },

        isSocialLinkCreate() {
            return !this.socialLinks.some((item) => item.id === this.socialLinkDraft?.id);
        },

        isPaymentBadgeCreate() {
            return !this.paymentBadges.some((item) => item.id === this.paymentBadgeDraft?.id);
        },

        logoUploadTag() {
            return `jv-storefront-settings-logo-${this.salesChannelId ?? 'default'}`;
        },

        logoPreviewSource() {
            return this.logoMedia ?? this.customFields.jv_storefront_logo_media_id;
        },

        socialLinkIconUploadTag() {
            return `jv-storefront-social-icon-${this.socialLinkDraft?.id ?? 'new'}`;
        },

        paymentBadgeIconUploadTag() {
            return `jv-storefront-payment-icon-${this.paymentBadgeDraft?.id ?? 'new'}`;
        },

        socialLinkIconPreviewSource() {
            return this.socialLinkDraft?.iconMedia ?? this.socialLinkDraft?.iconMediaId ?? null;
        },

        paymentBadgeIconPreviewSource() {
            return this.paymentBadgeDraft?.iconMedia ?? this.paymentBadgeDraft?.iconMediaId ?? null;
        },
    },

    created() {
        this.loadDefaultSalesChannel();
    },

    methods: {
        async loadDefaultSalesChannel() {
            this.isLoading = true;

            try {
                const criteria = new Criteria(1, 1);
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                const result = await this.salesChannelRepository.search(criteria);
                this.salesChannelId = result.first()?.id ?? null;

                if (this.salesChannelId) {
                    await this.loadSalesChannel();
                }
            } finally {
                this.isLoading = false;
            }
        },

        async onSalesChannelChange() {
            await this.loadSalesChannel();
        },

        async loadSalesChannel() {
            if (!this.salesChannelId) {
                return;
            }

            this.isLoading = true;

            try {
                const criteria = new Criteria();
                criteria.addAssociation('languages');

                this.salesChannel = await this.salesChannelRepository.get(
                    this.salesChannelId,
                    Shopware.Context.api,
                    criteria,
                );
                await this.reloadSalesChannelCustomFields();
                await Promise.all([
                    this.loadSocialLinks(),
                    this.loadPaymentBadges(),
                ]);
            } finally {
                this.isLoading = false;
            }
        },

        salesChannelLanguageContext() {
            if (!this.salesChannel?.languageId) {
                return Shopware.Context.api;
            }

            return {
                ...Shopware.Context.api,
                languageId: this.salesChannel.languageId,
            };
        },

        async reloadSalesChannelCustomFields() {
            if (!this.salesChannelId || !this.salesChannel?.languageId) {
                return;
            }

            const fresh = await this.salesChannelRepository.get(
                this.salesChannelId,
                this.salesChannelLanguageContext(),
            );
            this.salesChannel.customFields = fresh.customFields ?? {};
            this.hydrateCustomFields();
            await this.loadLogoMedia();
        },

        hydrateCustomFields() {
            const fields = this.salesChannel?.customFields ?? {};

            this.customFields = {
                jv_storefront_logo_media_id: this.normalizeMediaId(fields.jv_storefront_logo_media_id),
                jv_footer_about_eyebrow: fields.jv_footer_about_eyebrow ?? '',
                jv_footer_about_title: fields.jv_footer_about_title ?? '',
                jv_footer_about_description: fields.jv_footer_about_description ?? '',
                jv_footer_copyright_text: fields.jv_footer_copyright_text ?? '',
                jv_footer_revocation_enabled: !!fields.jv_footer_revocation_enabled,
                jv_footer_revocation_button_label: fields.jv_footer_revocation_button_label ?? '',
                jv_footer_revocation_recipient_email: fields.jv_footer_revocation_recipient_email ?? '',
            };
        },

        normalizeMediaId(value) {
            if (!value) {
                return null;
            }

            if (typeof value === 'string') {
                return value;
            }

            if (typeof value === 'object' && value.id) {
                return value.id;
            }

            return null;
        },

        buildCustomFieldsPayload() {
            return {
                jv_storefront_logo_media_id: this.normalizeMediaId(this.customFields.jv_storefront_logo_media_id),
                jv_footer_about_eyebrow: this.customFields.jv_footer_about_eyebrow ?? '',
                jv_footer_about_title: this.customFields.jv_footer_about_title ?? '',
                jv_footer_about_description: this.customFields.jv_footer_about_description ?? '',
                jv_footer_copyright_text: this.customFields.jv_footer_copyright_text ?? '',
                jv_footer_revocation_enabled: !!this.customFields.jv_footer_revocation_enabled,
                jv_footer_revocation_button_label: this.customFields.jv_footer_revocation_button_label ?? '',
                jv_footer_revocation_recipient_email: this.customFields.jv_footer_revocation_recipient_email ?? '',
            };
        },

        getSalesChannelLanguageIds() {
            const languageIds = (this.salesChannel?.languages ?? [])
                .map((language) => language.id)
                .filter(Boolean);

            if (languageIds.length > 0) {
                return languageIds;
            }

            return this.salesChannel?.languageId ? [this.salesChannel.languageId] : [];
        },

        async loadLogoMedia() {
            const mediaId = this.customFields.jv_storefront_logo_media_id;
            if (!mediaId) {
                this.logoMedia = null;

                return;
            }

            this.logoMedia = await this.mediaRepository.get(mediaId);
        },

        onOpenLogoMediaModal() {
            this.logoMediaModalOpen = true;
        },

        onCloseLogoMediaModal() {
            this.logoMediaModalOpen = false;
        },

        onLogoSelectionChanges(mediaEntities) {
            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            this.customFields.jv_storefront_logo_media_id = media.id;
            this.logoMedia = media;
            this.logoMediaModalOpen = false;
        },

        async onLogoUpload({ targetId }) {
            this.customFields.jv_storefront_logo_media_id = targetId;
            await this.loadLogoMedia();
        },

        onLogoRemove() {
            this.customFields.jv_storefront_logo_media_id = null;
            this.logoMedia = null;
        },

        openSocialLinkIconMediaModal() {
            this.iconMediaTarget = 'social-link';
            this.iconMediaModalOpen = true;
        },

        openPaymentBadgeIconMediaModal() {
            this.iconMediaTarget = 'payment-badge';
            this.iconMediaModalOpen = true;
        },

        onCloseIconMediaModal() {
            this.iconMediaModalOpen = false;
            this.iconMediaTarget = null;
        },

        onIconMediaSelectionChanges(mediaEntities) {
            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            if (this.iconMediaTarget === 'social-link' && this.socialLinkDraft) {
                this.socialLinkDraft.iconMediaId = media.id;
                this.socialLinkDraft.iconMedia = media;
            }

            if (this.iconMediaTarget === 'payment-badge' && this.paymentBadgeDraft) {
                this.paymentBadgeDraft.iconMediaId = media.id;
                this.paymentBadgeDraft.iconMedia = media;
            }

            this.onCloseIconMediaModal();
        },

        async onSocialLinkIconUpload({ targetId }) {
            if (!this.socialLinkDraft) {
                return;
            }

            this.socialLinkDraft.iconMediaId = targetId;
            this.socialLinkDraft.iconMedia = await this.mediaRepository.get(targetId);
        },

        onSocialLinkIconRemove() {
            if (!this.socialLinkDraft) {
                return;
            }

            this.socialLinkDraft.iconMediaId = null;
            this.socialLinkDraft.iconMedia = null;
        },

        async onPaymentBadgeIconUpload({ targetId }) {
            if (!this.paymentBadgeDraft) {
                return;
            }

            this.paymentBadgeDraft.iconMediaId = targetId;
            this.paymentBadgeDraft.iconMedia = await this.mediaRepository.get(targetId);
        },

        onPaymentBadgeIconRemove() {
            if (!this.paymentBadgeDraft) {
                return;
            }

            this.paymentBadgeDraft.iconMediaId = null;
            this.paymentBadgeDraft.iconMedia = null;
        },

        buildSocialLinkCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannelId));
            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addSorting(Criteria.sort('createdAt', 'ASC'));
            criteria.addAssociation('iconMedia');

            return criteria;
        },

        buildPaymentBadgeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannelId));
            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addSorting(Criteria.sort('createdAt', 'ASC'));
            criteria.addAssociation('iconMedia');

            return criteria;
        },

        async loadSocialLinks() {
            if (!this.salesChannelId) {
                this.socialLinks = [];

                return;
            }

            this.isSocialLinksLoading = true;

            try {
                const result = await this.socialLinkRepository.search(this.buildSocialLinkCriteria());
                this.socialLinks = result;
            } finally {
                this.isSocialLinksLoading = false;
            }
        },

        async loadPaymentBadges() {
            if (!this.salesChannelId) {
                this.paymentBadges = [];

                return;
            }

            this.isPaymentBadgesLoading = true;

            try {
                const result = await this.paymentBadgeRepository.search(this.buildPaymentBadgeCriteria());
                this.paymentBadges = result;
            } finally {
                this.isPaymentBadgesLoading = false;
            }
        },

        createSocialLinkDraft() {
            return {
                id: Utils.createId(),
                salesChannelId: this.salesChannelId,
                label: '',
                url: 'https://',
                iconMediaId: null,
                iconMedia: null,
                position: this.socialLinks.length,
                active: true,
                openInNewTab: true,
            };
        },

        createPaymentBadgeDraft() {
            return {
                id: Utils.createId(),
                salesChannelId: this.salesChannelId,
                label: '',
                iconMediaId: null,
                iconMedia: null,
                position: this.paymentBadges.length,
                active: true,
            };
        },

        openSocialLinkCreate() {
            this.socialLinkDraft = this.createSocialLinkDraft();
            this.socialLinkModalOpen = true;
        },

        openSocialLinkEdit(item) {
            this.socialLinkDraft = {
                id: item.id,
                salesChannelId: item.salesChannelId,
                label: item.label,
                url: item.url,
                iconMediaId: item.iconMediaId,
                iconMedia: item.iconMedia ?? null,
                position: item.position,
                active: item.active,
                openInNewTab: item.openInNewTab,
            };
            this.socialLinkModalOpen = true;
        },

        closeSocialLinkModal() {
            this.socialLinkModalOpen = false;
            this.socialLinkDraft = null;
        },

        onSocialLinkModalChange(isOpen) {
            this.socialLinkModalOpen = isOpen;

            if (!isOpen) {
                this.socialLinkDraft = null;
            }
        },

        openPaymentBadgeCreate() {
            this.paymentBadgeDraft = this.createPaymentBadgeDraft();
            this.paymentBadgeModalOpen = true;
        },

        openPaymentBadgeEdit(item) {
            this.paymentBadgeDraft = {
                id: item.id,
                salesChannelId: item.salesChannelId,
                label: item.label,
                iconMediaId: item.iconMediaId,
                iconMedia: item.iconMedia ?? null,
                position: item.position,
                active: item.active,
            };
            this.paymentBadgeModalOpen = true;
        },

        closePaymentBadgeModal() {
            this.paymentBadgeModalOpen = false;
            this.paymentBadgeDraft = null;
        },

        onPaymentBadgeModalChange(isOpen) {
            this.paymentBadgeModalOpen = isOpen;

            if (!isOpen) {
                this.paymentBadgeDraft = null;
            }
        },

        async saveSocialLinkDraft() {
            if (!this.socialLinkDraft || !this.salesChannelId) {
                return;
            }

            if (!this.socialLinkDraft.label?.trim() || !this.socialLinkDraft.url?.trim() || !this.socialLinkDraft.iconMediaId) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.socialValidationFailed'),
                });

                return;
            }

            this.isSocialLinkSaving = true;

            try {
                const entity = this.isSocialLinkCreate
                    ? this.socialLinkRepository.create()
                    : await this.socialLinkRepository.get(this.socialLinkDraft.id);

                Object.assign(entity, {
                    id: this.socialLinkDraft.id,
                    salesChannelId: this.salesChannelId,
                    label: this.socialLinkDraft.label.trim(),
                    url: this.socialLinkDraft.url.trim(),
                    iconMediaId: this.socialLinkDraft.iconMediaId,
                    position: Number(this.socialLinkDraft.position) || 0,
                    active: !!this.socialLinkDraft.active,
                    openInNewTab: !!this.socialLinkDraft.openInNewTab,
                });

                await this.socialLinkRepository.save(entity);
                await this.loadSocialLinks();
                this.closeSocialLinkModal();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.socialSaved'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.socialSaveFailed'),
                });
            } finally {
                this.isSocialLinkSaving = false;
            }
        },

        async savePaymentBadgeDraft() {
            if (!this.paymentBadgeDraft || !this.salesChannelId) {
                return;
            }

            if (!this.paymentBadgeDraft.label?.trim() || !this.paymentBadgeDraft.iconMediaId) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.paymentValidationFailed'),
                });

                return;
            }

            this.isPaymentBadgeSaving = true;

            try {
                const entity = this.isPaymentBadgeCreate
                    ? this.paymentBadgeRepository.create()
                    : await this.paymentBadgeRepository.get(this.paymentBadgeDraft.id);

                Object.assign(entity, {
                    id: this.paymentBadgeDraft.id,
                    salesChannelId: this.salesChannelId,
                    label: this.paymentBadgeDraft.label.trim(),
                    iconMediaId: this.paymentBadgeDraft.iconMediaId,
                    position: Number(this.paymentBadgeDraft.position) || 0,
                    active: !!this.paymentBadgeDraft.active,
                });

                await this.paymentBadgeRepository.save(entity);
                await this.loadPaymentBadges();
                this.closePaymentBadgeModal();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.paymentSaved'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.paymentSaveFailed'),
                });
            } finally {
                this.isPaymentBadgeSaving = false;
            }
        },

        async deleteSocialLink(item) {
            try {
                await this.socialLinkRepository.delete(item.id);
                await this.loadSocialLinks();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.socialDeleted'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.socialDeleteFailed'),
                });
            }
        },

        async deletePaymentBadge(item) {
            try {
                await this.paymentBadgeRepository.delete(item.id);
                await this.loadPaymentBadges();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.paymentDeleted'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.paymentDeleteFailed'),
                });
            }
        },

        async saveCustomFields() {
            if (!this.salesChannel?.id) {
                return;
            }

            this.isSaving = true;

            const payload = this.buildCustomFieldsPayload();
            const languageIds = this.getSalesChannelLanguageIds();

            try {
                for (const languageId of languageIds) {
                    const context = {
                        ...Shopware.Context.api,
                        languageId,
                    };
                    const entity = await this.salesChannelRepository.get(this.salesChannel.id, context);

                    if (!entity) {
                        throw new Error('Sales channel not found');
                    }

                    entity.customFields = {
                        ...(entity.customFields ?? {}),
                        ...payload,
                    };

                    await this.salesChannelRepository.save(entity, context);
                }

                await this.reloadSalesChannelCustomFields();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.saved'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.saveFailed'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        formatActive(value) {
            return value
                ? this.$t('global.default.yes')
                : this.$t('global.default.no');
        },
    },
};
