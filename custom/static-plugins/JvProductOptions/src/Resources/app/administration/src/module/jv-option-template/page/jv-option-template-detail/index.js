import template from './jv-option-template-detail.html.twig';
import './jv-option-template-detail.scss';

const { Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

export default {
    template,

    inject: [
        'repositoryFactory',
        'syncService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            templateEntity: null,
            isLoading: false,
            isSaveSuccessful: false,
            currencyId: Shopware.Context.app.systemCurrencyId,
            currencyName: null,
            currencyIsoCode: null,
            activeMediaTarget: null,
            mediaModalIsOpen: false,
            originalGroupIds: [],
            originalValueIdsByGroup: {},
            originalProductStreamIds: [],
            originalNamesById: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return (this.templateEntity && this.displayName(this.templateEntity)) || this.$t('jv-option-template.detail.titleNew');
        },

        templateRepository() {
            return this.repositoryFactory.create('jv_option_template');
        },

        groupRepository() {
            return this.repositoryFactory.create('jv_option_template_group');
        },

        valueRepository() {
            return this.repositoryFactory.create('jv_option_template_value');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        systemCurrencyLabel() {
            if (this.currencyName && this.currencyIsoCode) {
                return `${this.currencyName} (${this.currencyIsoCode})`;
            }

            return this.currencyIsoCode || this.$t('jv-option-template.detail.systemCurrency');
        },

        surchargeTypeOptions() {
            return [
                {
                    value: 'fixed',
                    label: this.$t('jv-option-template.detail.surchargeTypeFixed'),
                },
                {
                    value: 'percentage',
                    label: this.$t('jv-option-template.detail.surchargeTypePercentage'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;

            await this.loadSystemCurrency();
            await this.loadEntityData();

            this.isLoading = false;
        },

        async loadEntityData() {
            const templateId = this.$route.params.id;

            if (templateId) {
                await this.loadTemplate(templateId);
            } else {
                this.templateEntity = this.templateRepository.create(Shopware.Context.api);
                this.templateEntity.active = true;
                this.templateEntity.priority = 0;
                this.templateEntity.name = '';
                this.originalGroupIds = [];
                this.originalValueIdsByGroup = {};
                this.originalProductStreamIds = [];
                this.originalNamesById = {};
            }
        },

        async loadTemplate(id) {
            const criteria = new Criteria([id]);
            criteria.addAssociation('productStreams');
            criteria.addAssociation('translations');
            criteria.addAssociation('groups.translations');
            criteria.addAssociation('groups.paletteMedia');
            criteria.addAssociation('groups.values.media');
            criteria.addAssociation('groups.values.translations');
            criteria.addSorting(Criteria.sort('groups.position', 'ASC'));
            criteria.addSorting(Criteria.sort('groups.values.position', 'ASC'));

            try {
                this.templateEntity = await this.templateRepository.get(id, Shopware.Context.api, criteria);
                if (this.templateEntity.productStreams) {
                    const productStreams = this.templateEntity.productStreams;
                    const systemLanguageContext = {
                        ...Shopware.Context.api,
                        languageId: Shopware.Context.api.systemLanguageId,
                    };
                    const systemLanguageProductStreams = new EntityCollection(
                        productStreams.source,
                        productStreams.entity,
                        systemLanguageContext,
                        productStreams.criteria
                    );

                    productStreams.forEach((productStream) => systemLanguageProductStreams.push(productStream));
                    this.templateEntity.productStreams = systemLanguageProductStreams;
                }
                this.originalGroupIds = this.templateEntity.groups.map((group) => group.id);
                this.originalProductStreamIds = (this.templateEntity.productStreams || []).map((stream) => stream.id);
                this.originalValueIdsByGroup = Object.fromEntries(this.templateEntity.groups.map((group) => [
                    group.id,
                    group.values.map((value) => value.id),
                ]));
                this.originalNamesById = {
                    [this.templateEntity.id]: this.displayName(this.templateEntity),
                };
                for (const group of this.templateEntity.groups) {
                    this.originalNamesById[group.id] = this.displayName(group);
                    for (const value of group.values) {
                        this.originalNamesById[value.id] = this.displayName(value);
                    }
                }
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('jv-option-template.detail.saveError'),
                });
            }
        },

        async loadSystemCurrency() {
            try {
                const currency = await this.currencyRepository.get(this.currencyId, Shopware.Context.api);
                this.currencyName = currency.name || null;
                this.currencyIsoCode = currency.isoCode || null;
            } catch (error) {
                this.currencyName = null;
                this.currencyIsoCode = null;
            }
        },

        defaultValueOptions(group) {
            return group.values.map((value) => ({ value: value.id, label: this.displayName(value) || value.id }));
        },

        displayName(entity) {
            const systemLanguageId = Shopware.Context.api.systemLanguageId;
            const translations = entity.translations;
            const systemTranslation = translations?.find?.((translation) => translation.languageId === systemLanguageId);

            return entity.name || entity.translated?.name || systemTranslation?.name || '';
        },

        setEntityName(entity, name) {
            entity.name = name;
        },

        systemName(entity) {
            const systemLanguageId = Shopware.Context.api.systemLanguageId;
            if (Shopware.Context.api.languageId === systemLanguageId) {
                return this.displayName(entity);
            }

            const systemTranslation = entity.translations?.find?.((translation) => translation.languageId === systemLanguageId);
            return systemTranslation?.name || this.displayName(entity);
        },

        shouldWriteActiveTranslation(entity) {
            const languageId = Shopware.Context.api.languageId;
            return languageId !== Shopware.Context.api.systemLanguageId
                && (entity.isNew() || this.originalNamesById[entity.id] !== this.displayName(entity));
        },

        setNumericField(entity, field, value) {
            entity[field] = value === null || value === undefined ? 0 : Number(value);
        },

        addGroup() {
            const group = this.groupRepository.create(Shopware.Context.api);
            group.templateId = this.templateEntity.id;
            group.name = '';
            group.position = this.templateEntity.groups.length + 1;
            group.paletteMediaId = null;
            group.defaultValueId = null;

            this.templateEntity.groups.push(group);
        },

        removeGroup(groupIndex) {
            this.templateEntity.groups.splice(groupIndex, 1);
        },

        addValue(group) {
            const value = this.valueRepository.create(Shopware.Context.api);
            value.groupId = group.id;
            value.name = '';
            value.position = group.values.length + 1;
            value.colorHex = null;
            value.mediaId = null;
            value.surchargeType = 'fixed';
            value.surchargePrice = [{
                currencyId: this.currencyId,
                gross: 0,
                net: 0,
                linked: false,
            }];
            value.surchargePercentage = null;

            group.values.push(value);

            if (!group.defaultValueId) {
                group.defaultValueId = value.id;
            }
        },

        removeValue(group, valueIndex) {
            const removed = group.values.splice(valueIndex, 1)[0];
            if (group.defaultValueId === removed.id) {
                group.defaultValueId = group.values.length > 0 ? group.values[0].id : null;
            }
        },

        findDefaultCurrencyPrice(value) {
            if (!Array.isArray(value.surchargePrice)) {
                return null;
            }

            return value.surchargePrice.find((price) => price.currencyId === this.currencyId) || null;
        },

        valueGrossPrice(value) {
            return this.findDefaultCurrencyPrice(value)?.gross ?? 0;
        },

        valueNetPrice(value) {
            return this.findDefaultCurrencyPrice(value)?.net ?? 0;
        },

        setValueGrossPrice(value, gross) {
            this.updateCurrencyPrice(value, {
                gross: Number(gross) || 0,
                net: this.valueNetPrice(value),
            });
        },

        setValueNetPrice(value, net) {
            this.updateCurrencyPrice(value, {
                gross: this.valueGrossPrice(value),
                net: Number(net) || 0,
            });
        },

        updateCurrencyPrice(value, amounts) {
            const prices = Array.isArray(value.surchargePrice) ? [...value.surchargePrice] : [];
            const priceIndex = prices.findIndex((price) => price.currencyId === this.currencyId);
            const price = {
                currencyId: this.currencyId,
                gross: amounts.gross,
                net: amounts.net,
                linked: false,
            };

            if (priceIndex === -1) {
                prices.push(price);
            } else {
                prices.splice(priceIndex, 1, price);
            }

            value.surchargePrice = prices;
        },

        setValueSurchargeType(value, surchargeType) {
            value.surchargeType = surchargeType;

            if (surchargeType === 'percentage') {
                value.surchargePrice = null;
                value.surchargePercentage = Number(value.surchargePercentage) || 0;
            } else {
                value.surchargePercentage = null;
                this.updateCurrencyPrice(value, {
                    gross: this.valueGrossPrice(value),
                    net: this.valueNetPrice(value),
                });
            }
        },

        valueColorHex(value) {
            return value.colorHex || '';
        },

        setValueColorHex(value, colorHex) {
            value.colorHex = colorHex && colorHex.trim() !== '' ? colorHex.trim() : null;
        },

        mediaUploadTag(scope, groupId, valueId = '') {
            return `jv-option-template-${scope}-${groupId}-${valueId}`;
        },

        mediaPreviewSource(mediaId) {
            return mediaId || null;
        },

        openGroupPaletteMediaModal(groupId) {
            this.activeMediaTarget = {
                type: 'group',
                groupId,
            };
            this.mediaModalIsOpen = true;
        },

        openValueMediaModal(groupId, valueId) {
            this.activeMediaTarget = {
                type: 'value',
                groupId,
                valueId,
            };
            this.mediaModalIsOpen = true;
        },

        closeMediaModal() {
            this.mediaModalIsOpen = false;
            this.activeMediaTarget = null;
        },

        async onGroupPaletteMediaUpload(group, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId, Shopware.Context.api);
            group.paletteMediaId = mediaEntity.id;
        },

        onGroupPaletteMediaRemove(group) {
            group.paletteMediaId = null;
        },

        async onValueMediaUpload(value, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId, Shopware.Context.api);
            value.mediaId = mediaEntity.id;
        },

        onValueMediaRemove(value) {
            value.mediaId = null;
        },

        onMediaSelectionChanges(mediaEntities) {
            if (!this.activeMediaTarget) {
                return;
            }

            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            const group = this.templateEntity.groups.find((item) => item.id === this.activeMediaTarget.groupId);
            if (!group) {
                this.closeMediaModal();
                return;
            }

            if (this.activeMediaTarget.type === 'group') {
                group.paletteMediaId = media.id;
            } else {
                const value = group.values.find((item) => item.id === this.activeMediaTarget.valueId);
                if (value) {
                    value.mediaId = media.id;
                }
            }

            this.closeMediaModal();
        },

        blockedByLanguage() {
            const systemLanguageId = Shopware.Context.api.systemLanguageId;
            const languageId = Shopware.Context.api.languageId;

            return this.templateEntity.isNew() && languageId !== systemLanguageId;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        abortOnLanguageChange() {
            return this.templateRepository.hasChanges(this.templateEntity);
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        addSyncOperation(operations, key, entity, action, payload) {
            if (payload.length === 0) {
                return;
            }

            operations[key] = { entity, action, payload };
        },

        async onSave() {
            const isSystemLanguage = Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
            const apiContext = Shopware.Context.api;

            if (isSystemLanguage && (!this.templateEntity.name || this.templateEntity.name.trim() === '')) {
                this.createNotificationError({
                    message: this.$t('jv-option-template.detail.placeholderName'),
                });
                return;
            }

            if (this.blockedByLanguage()) {
                this.createNotificationError({
                    message: this.$t('jv-option-template.detail.systemLanguageRequired'),
                });
                return;
            }

            this.isLoading = true;

            try {
                const templateId = this.templateEntity.id;
                const groups = this.templateEntity.groups.slice();
                const operations = {};
                const templatePayload = {
                    id: templateId,
                    name: this.systemName(this.templateEntity),
                    active: !!this.templateEntity.active,
                    priority: parseInt(this.templateEntity.priority, 10) || 0,
                };

                this.addSyncOperation(operations, 'jv-option-template-upsert', 'jv_option_template', 'upsert', [templatePayload]);

                const groupPayload = [];
                const valuePayload = [];
                const templateTranslationPayload = [];
                const groupTranslationPayload = [];
                const valueTranslationPayload = [];

                if (this.shouldWriteActiveTranslation(this.templateEntity)) {
                    templateTranslationPayload.push({
                        jvOptionTemplateId: templateId,
                        languageId: apiContext.languageId,
                        name: this.displayName(this.templateEntity),
                    });
                }

                for (const group of groups) {
                    const groupPayloadItem = {
                        id: group.id,
                        templateId,
                        name: this.systemName(group),
                        position: parseInt(group.position, 10) || 0,
                        paletteMediaId: group.paletteMediaId || null,
                    };

                    if (this.shouldWriteActiveTranslation(group)) {
                        groupTranslationPayload.push({
                            jvOptionTemplateGroupId: group.id,
                            languageId: apiContext.languageId,
                            name: this.displayName(group),
                        });
                    }

                    groupPayload.push(groupPayloadItem);

                    for (const value of group.values) {
                        const valuePayloadItem = {
                            id: value.id,
                            groupId: group.id,
                            name: this.systemName(value),
                            position: parseInt(value.position, 10) || 0,
                            colorHex: value.colorHex && value.colorHex.trim() !== '' ? value.colorHex.trim() : null,
                            mediaId: value.mediaId || null,
                            surchargeType: value.surchargeType,
                            surchargePrice: value.surchargeType === 'fixed' ? value.surchargePrice : null,
                            surchargePercentage: value.surchargeType === 'percentage'
                                ? Number(value.surchargePercentage) || 0
                                : null,
                        };

                        if (this.shouldWriteActiveTranslation(value)) {
                            valueTranslationPayload.push({
                                jvOptionTemplateValueId: value.id,
                                languageId: apiContext.languageId,
                                name: this.displayName(value),
                            });
                        }

                        valuePayload.push(valuePayloadItem);
                    }
                }

                this.addSyncOperation(operations, 'jv-option-template-group-upsert', 'jv_option_template_group', 'upsert', groupPayload);
                this.addSyncOperation(operations, 'jv-option-template-value-upsert', 'jv_option_template_value', 'upsert', valuePayload);
                this.addSyncOperation(operations, 'jv-option-template-translation-upsert', 'jv_option_template_translation', 'upsert', templateTranslationPayload);
                this.addSyncOperation(operations, 'jv-option-template-group-translation-upsert', 'jv_option_template_group_translation', 'upsert', groupTranslationPayload);
                this.addSyncOperation(operations, 'jv-option-template-value-translation-upsert', 'jv_option_template_value_translation', 'upsert', valueTranslationPayload);

                const groupDefaults = groups.map((group) => ({
                    id: group.id,
                    defaultValueId: group.defaultValueId || null,
                }));
                this.addSyncOperation(operations, 'jv-option-template-group-default-upsert', 'jv_option_template_group', 'upsert', groupDefaults);

                const currentGroupIds = new Set(groups.map((group) => group.id));
                const removedGroupIds = this.originalGroupIds.filter((id) => !currentGroupIds.has(id));
                const removedValuePayload = [];
                for (const group of groups) {
                    const originalValueIds = this.originalValueIdsByGroup[group.id] || [];
                    const currentValueIds = new Set(group.values.map((value) => value.id));
                    originalValueIds
                        .filter((id) => !currentValueIds.has(id))
                        .forEach((id) => removedValuePayload.push({ id }));
                }

                this.addSyncOperation(operations, 'jv-option-template-value-delete', 'jv_option_template_value', 'delete', removedValuePayload);
                this.addSyncOperation(operations, 'jv-option-template-group-delete', 'jv_option_template_group', 'delete', removedGroupIds.map((id) => ({ id })));

                const currentProductStreamIds = new Set((this.templateEntity.productStreams || []).map((stream) => stream.id));
                const productStreamUpserts = [...currentProductStreamIds]
                    .filter((id) => !this.originalProductStreamIds.includes(id))
                    .map((productStreamId) => ({ templateId, productStreamId }));
                const productStreamDeletes = this.originalProductStreamIds
                    .filter((id) => !currentProductStreamIds.has(id))
                    .map((productStreamId) => ({ templateId, productStreamId }));
                this.addSyncOperation(operations, 'jv-option-template-stream-upsert', 'jv_option_template_product_stream', 'upsert', productStreamUpserts);
                this.addSyncOperation(operations, 'jv-option-template-stream-delete', 'jv_option_template_product_stream', 'delete', productStreamDeletes);

                await this.syncService.sync(operations, {}, { 'sw-language-id': apiContext.systemLanguageId });

                if (!this.$route.params.id) {
                    await this.$router.push({
                        name: 'jv.option.template.detail',
                        params: { id: templateId },
                    });
                }

                await this.loadTemplate(templateId);
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    message: this.$t('jv-option-template.detail.saveSuccess'),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('jv-option-template.detail.saveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onCancel() {
            this.$router.push({ name: 'jv.option.template.index' });
        },
    },
};
