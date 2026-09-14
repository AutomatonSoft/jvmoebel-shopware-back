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
            shippingBadges: [],
            internationalLinks: [],
            isSocialLinksLoading: false,
            isPaymentBadgesLoading: false,
            isShippingBadgesLoading: false,
            isInternationalLinksLoading: false,
            socialLinkModalOpen: false,
            paymentBadgeModalOpen: false,
            shippingBadgeModalOpen: false,
            internationalLinkModalOpen: false,
            socialLinkDraft: null,
            paymentBadgeDraft: null,
            shippingBadgeDraft: null,
            internationalLinkDraft: null,
            isSocialLinkSaving: false,
            isPaymentBadgeSaving: false,
            isShippingBadgeSaving: false,
            isInternationalLinkSaving: false,
            navigationRootCategoryId: null,
            headerNavigationLinks: [],
            isHeaderNavigationLoading: false,
            headerNavigationLoadToken: 0,
            headerNavigationSortableKey: 0,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
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

        shippingBadgeRepository() {
            return this.repositoryFactory.create('jv_storefront_shipping_badge');
        },

        internationalLinkRepository() {
            return this.repositoryFactory.create('jv_storefront_international_link');
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

        shippingBadgeGridColumns() {
            return [
                { property: 'label', label: this.$t('jv-storefront-settings.list.label'), primary: true },
                { property: 'position', label: this.$t('jv-storefront-settings.list.position'), align: 'right' },
                { property: 'active', label: this.$t('jv-storefront-settings.list.active'), align: 'center' },
            ];
        },

        internationalLinkGridColumns() {
            return [
                { property: 'label', label: this.$t('jv-storefront-settings.list.label'), primary: true },
                { property: 'targetSalesChannel', label: this.$t('jv-storefront-settings.international.targetSalesChannel'), multiLine: true },
                { property: 'position', label: this.$t('jv-storefront-settings.list.position'), align: 'right' },
                { property: 'active', label: this.$t('jv-storefront-settings.list.active'), align: 'center' },
            ];
        },

        targetSalesChannelCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', this.salesChannelId)]));
            }

            return criteria;
        },

        isSocialLinkCreate() {
            return !this.socialLinks.some((item) => item.id === this.socialLinkDraft?.id);
        },

        isPaymentBadgeCreate() {
            return !this.paymentBadges.some((item) => item.id === this.paymentBadgeDraft?.id);
        },

        isShippingBadgeCreate() {
            return !this.shippingBadges.some((item) => item.id === this.shippingBadgeDraft?.id);
        },

        isInternationalLinkCreate() {
            return !this.internationalLinks.some((item) => item.id === this.internationalLinkDraft?.id);
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

        shippingBadgeIconUploadTag() {
            return `jv-storefront-shipping-icon-${this.shippingBadgeDraft?.id ?? 'new'}`;
        },

        shippingBadgeIconPreviewSource() {
            return this.shippingBadgeDraft?.iconMedia ?? this.shippingBadgeDraft?.iconMediaId ?? null;
        },

        internationalLinkIconUploadTag() {
            return `jv-storefront-international-icon-${this.internationalLinkDraft?.id ?? 'new'}`;
        },

        internationalLinkIconPreviewSource() {
            return this.internationalLinkDraft?.iconMedia ?? this.internationalLinkDraft?.iconMediaId ?? null;
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
                this.navigationRootCategoryId = this.salesChannel.navigationCategoryId ?? null;
                await Promise.all([
                    this.loadHeaderNavigationLinks(),
                    this.loadSocialLinks(),
                    this.loadPaymentBadges(),
                    this.loadShippingBadges(),
                    this.loadInternationalLinks(),
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
                jv_header_navigation_visible_category_ids: this.serializeHeaderNavigationWhitelist(),
            };
        },

        normalizeStoredWhitelist(value) {
            if (value === null || value === undefined) {
                return null;
            }

            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed === '') {
                    return null;
                }

                try {
                    value = JSON.parse(trimmed);
                } catch {
                    return null;
                }
            }

            if (!Array.isArray(value)) {
                return null;
            }

            const normalized = [];
            const seen = new Set();

            value.forEach((entry) => {
                if (typeof entry !== 'string') {
                    return;
                }

                const id = entry.trim().toLowerCase();
                if (!id || seen.has(id)) {
                    return;
                }

                seen.add(id);
                normalized.push(id);
            });

            return normalized;
        },

        getStoredHeaderNavigationWhitelist() {
            const fields = this.salesChannel?.customFields ?? {};

            return this.normalizeStoredWhitelist(fields.jv_header_navigation_visible_category_ids);
        },

        buildHeaderNavigationLinks(categories, storedWhitelist) {
            const categoryList = categories.map((category) => ({
                id: category.id,
                label: category.translated?.name ?? category.name ?? category.id,
            }));

            if (storedWhitelist === null) {
                return categoryList.map((item) => ({
                    ...item,
                    visible: true,
                }));
            }

            const remainingById = Object.fromEntries(categoryList.map((item) => [item.id, item]));
            const ordered = [];

            storedWhitelist.forEach((id) => {
                const item = remainingById[id];
                if (!item) {
                    return;
                }

                ordered.push({
                    ...item,
                    visible: true,
                });
                delete remainingById[id];
            });

            categoryList.forEach((item) => {
                if (!remainingById[item.id]) {
                    return;
                }

                ordered.push({
                    ...item,
                    visible: false,
                });
            });

            return ordered;
        },

        serializeHeaderNavigationWhitelist() {
            return this.headerNavigationLinks
                .filter((item) => item.visible)
                .map((item) => item.id);
        },

        buildHeaderNavigationCriteria(rootCategoryId) {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('parentId', rootCategoryId));
            criteria.addSorting(Criteria.sort('autoIncrement', 'ASC'));

            return criteria;
        },

        async loadHeaderNavigationLinks() {
            const token = this.headerNavigationLoadToken + 1;
            this.headerNavigationLoadToken = token;
            const rootCategoryId = this.navigationRootCategoryId;

            if (!rootCategoryId) {
                this.headerNavigationLinks = [];
                this.isHeaderNavigationLoading = false;

                return;
            }

            this.isHeaderNavigationLoading = true;

            try {
                const result = await this.categoryRepository.search(
                    this.buildHeaderNavigationCriteria(rootCategoryId),
                    this.salesChannelLanguageContext(),
                );

                if (token !== this.headerNavigationLoadToken) {
                    return;
                }

                const categories = [];
                result.forEach((category) => {
                    categories.push(category);
                });

                this.headerNavigationLinks = this.buildHeaderNavigationLinks(
                    categories,
                    this.getStoredHeaderNavigationWhitelist(),
                );
                this.headerNavigationSortableKey += 1;
            } catch {
                if (token !== this.headerNavigationLoadToken) {
                    return;
                }

                this.headerNavigationLinks = [];
            } finally {
                if (token !== this.headerNavigationLoadToken) {
                    return;
                }

                this.isHeaderNavigationLoading = false;
            }
        },

        async onNavigationRootChange() {
            await this.loadHeaderNavigationLinks();
        },

        onHeaderNavigationSorted(sortedItems) {
            this.headerNavigationLinks = [...sortedItems];
        },

        onHeaderNavigationVisibilityChange(item, visible) {
            item.visible = !!visible;
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

        openShippingBadgeIconMediaModal() {
            this.iconMediaTarget = 'shipping-badge';
            this.iconMediaModalOpen = true;
        },

        openInternationalLinkIconMediaModal() {
            this.iconMediaTarget = 'international-link';
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

            if (this.iconMediaTarget === 'shipping-badge' && this.shippingBadgeDraft) {
                this.shippingBadgeDraft.iconMediaId = media.id;
                this.shippingBadgeDraft.iconMedia = media;
            }

            if (this.iconMediaTarget === 'international-link' && this.internationalLinkDraft) {
                this.internationalLinkDraft.iconMediaId = media.id;
                this.internationalLinkDraft.iconMedia = media;
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

        async onShippingBadgeIconUpload({ targetId }) {
            if (!this.shippingBadgeDraft) {
                return;
            }

            this.shippingBadgeDraft.iconMediaId = targetId;
            this.shippingBadgeDraft.iconMedia = await this.mediaRepository.get(targetId);
        },

        onShippingBadgeIconRemove() {
            if (!this.shippingBadgeDraft) {
                return;
            }

            this.shippingBadgeDraft.iconMediaId = null;
            this.shippingBadgeDraft.iconMedia = null;
        },

        async onInternationalLinkIconUpload({ targetId }) {
            if (!this.internationalLinkDraft) {
                return;
            }

            this.internationalLinkDraft.iconMediaId = targetId;
            this.internationalLinkDraft.iconMedia = await this.mediaRepository.get(targetId);
        },

        onInternationalLinkIconRemove() {
            if (!this.internationalLinkDraft) {
                return;
            }

            this.internationalLinkDraft.iconMediaId = null;
            this.internationalLinkDraft.iconMedia = null;
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

        buildShippingBadgeCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannelId));
            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addSorting(Criteria.sort('createdAt', 'ASC'));
            criteria.addAssociation('iconMedia');

            return criteria;
        },

        async loadShippingBadges() {
            if (!this.salesChannelId) {
                this.shippingBadges = [];

                return;
            }

            this.isShippingBadgesLoading = true;

            try {
                const result = await this.shippingBadgeRepository.search(this.buildShippingBadgeCriteria());
                this.shippingBadges = result;
            } finally {
                this.isShippingBadgesLoading = false;
            }
        },

        buildInternationalLinkCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannelId));
            criteria.addSorting(Criteria.sort('position', 'ASC'));
            criteria.addSorting(Criteria.sort('createdAt', 'ASC'));
            criteria.addAssociation('iconMedia');
            criteria.addAssociation('targetSalesChannel');

            return criteria;
        },

        async loadInternationalLinks() {
            if (!this.salesChannelId) {
                this.internationalLinks = [];

                return;
            }

            this.isInternationalLinksLoading = true;

            try {
                const result = await this.internationalLinkRepository.search(this.buildInternationalLinkCriteria());
                this.internationalLinks = result;
            } finally {
                this.isInternationalLinksLoading = false;
            }
        },

        formatTargetSalesChannelName(item) {
            return item.targetSalesChannel?.translated?.name
                ?? item.targetSalesChannel?.name
                ?? item.targetSalesChannelId
                ?? '';
        },

        hasDuplicateInternationalTarget(targetSalesChannelId, excludeId = null) {
            return this.internationalLinks.some((item) => {
                if (excludeId && item.id === excludeId) {
                    return false;
                }

                return item.targetSalesChannelId === targetSalesChannelId;
            });
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

        createShippingBadgeDraft() {
            return {
                id: Utils.createId(),
                salesChannelId: this.salesChannelId,
                label: '',
                iconMediaId: null,
                iconMedia: null,
                position: this.shippingBadges.length,
                active: true,
            };
        },

        createInternationalLinkDraft() {
            return {
                id: Utils.createId(),
                salesChannelId: this.salesChannelId,
                targetSalesChannelId: null,
                label: '',
                iconMediaId: null,
                iconMedia: null,
                position: this.internationalLinks.length,
                active: true,
                openInNewTab: true,
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

        openShippingBadgeCreate() {
            this.shippingBadgeDraft = this.createShippingBadgeDraft();
            this.shippingBadgeModalOpen = true;
        },

        openShippingBadgeEdit(item) {
            this.shippingBadgeDraft = {
                id: item.id,
                salesChannelId: item.salesChannelId,
                label: item.label ?? '',
                iconMediaId: item.iconMediaId,
                iconMedia: item.iconMedia ?? null,
                position: item.position,
                active: item.active,
            };
            this.shippingBadgeModalOpen = true;
        },

        closeShippingBadgeModal() {
            this.shippingBadgeModalOpen = false;
            this.shippingBadgeDraft = null;
        },

        onShippingBadgeModalChange(isOpen) {
            this.shippingBadgeModalOpen = isOpen;

            if (!isOpen) {
                this.shippingBadgeDraft = null;
            }
        },

        openInternationalLinkCreate() {
            this.internationalLinkDraft = this.createInternationalLinkDraft();
            this.internationalLinkModalOpen = true;
        },

        openInternationalLinkEdit(item) {
            this.internationalLinkDraft = {
                id: item.id,
                salesChannelId: item.salesChannelId,
                targetSalesChannelId: item.targetSalesChannelId,
                label: item.label ?? '',
                iconMediaId: item.iconMediaId,
                iconMedia: item.iconMedia ?? null,
                position: item.position,
                active: item.active,
                openInNewTab: item.openInNewTab,
            };
            this.internationalLinkModalOpen = true;
        },

        closeInternationalLinkModal() {
            this.internationalLinkModalOpen = false;
            this.internationalLinkDraft = null;
        },

        onInternationalLinkModalChange(isOpen) {
            this.internationalLinkModalOpen = isOpen;

            if (!isOpen) {
                this.internationalLinkDraft = null;
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

        async saveShippingBadgeDraft() {
            if (!this.shippingBadgeDraft || !this.salesChannelId) {
                return;
            }

            if (!this.shippingBadgeDraft.iconMediaId) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.shippingValidationFailed'),
                });

                return;
            }

            this.isShippingBadgeSaving = true;

            try {
                const entity = this.isShippingBadgeCreate
                    ? this.shippingBadgeRepository.create()
                    : await this.shippingBadgeRepository.get(this.shippingBadgeDraft.id);

                Object.assign(entity, {
                    id: this.shippingBadgeDraft.id,
                    salesChannelId: this.salesChannelId,
                    label: this.shippingBadgeDraft.label?.trim() || null,
                    iconMediaId: this.shippingBadgeDraft.iconMediaId,
                    position: Number(this.shippingBadgeDraft.position) || 0,
                    active: !!this.shippingBadgeDraft.active,
                });

                await this.shippingBadgeRepository.save(entity);
                await this.loadShippingBadges();
                this.closeShippingBadgeModal();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.shippingSaved'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.shippingSaveFailed'),
                });
            } finally {
                this.isShippingBadgeSaving = false;
            }
        },

        async deleteShippingBadge(item) {
            try {
                await this.shippingBadgeRepository.delete(item.id);
                await this.loadShippingBadges();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.shippingDeleted'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.shippingDeleteFailed'),
                });
            }
        },

        async saveInternationalLinkDraft() {
            if (!this.internationalLinkDraft || !this.salesChannelId) {
                return;
            }

            if (
                !this.internationalLinkDraft.targetSalesChannelId
                || !this.internationalLinkDraft.iconMediaId
            ) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.internationalValidationFailed'),
                });

                return;
            }

            if (this.internationalLinkDraft.targetSalesChannelId === this.salesChannelId) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.internationalSelfTargetFailed'),
                });

                return;
            }

            if (this.hasDuplicateInternationalTarget(
                this.internationalLinkDraft.targetSalesChannelId,
                this.isInternationalLinkCreate ? null : this.internationalLinkDraft.id,
            )) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.internationalDuplicateTargetFailed'),
                });

                return;
            }

            this.isInternationalLinkSaving = true;

            try {
                const entity = this.isInternationalLinkCreate
                    ? this.internationalLinkRepository.create()
                    : await this.internationalLinkRepository.get(this.internationalLinkDraft.id);

                Object.assign(entity, {
                    id: this.internationalLinkDraft.id,
                    salesChannelId: this.salesChannelId,
                    targetSalesChannelId: this.internationalLinkDraft.targetSalesChannelId,
                    label: this.internationalLinkDraft.label?.trim() || null,
                    iconMediaId: this.internationalLinkDraft.iconMediaId,
                    position: Number(this.internationalLinkDraft.position) || 0,
                    active: !!this.internationalLinkDraft.active,
                    openInNewTab: !!this.internationalLinkDraft.openInNewTab,
                });

                await this.internationalLinkRepository.save(entity);
                await this.loadInternationalLinks();
                this.closeInternationalLinkModal();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.internationalSaved'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.internationalSaveFailed'),
                });
            } finally {
                this.isInternationalLinkSaving = false;
            }
        },

        async deleteInternationalLink(item) {
            try {
                await this.internationalLinkRepository.delete(item.id);
                await this.loadInternationalLinks();
                this.createNotificationSuccess({
                    message: this.$t('jv-storefront-settings.notifications.internationalDeleted'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.$t('jv-storefront-settings.notifications.internationalDeleteFailed'),
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
                    entity.navigationCategoryId = this.navigationRootCategoryId;

                    await this.salesChannelRepository.save(entity, context);
                }

                this.salesChannel.navigationCategoryId = this.navigationRootCategoryId;
                await this.reloadSalesChannelCustomFields();
                await this.loadHeaderNavigationLinks();
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
