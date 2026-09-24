import template from './jv-option-template-detail.html.twig';
import './jv-option-template-detail.scss';

const { Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

export default {
    template,

    inject: [
        'repositoryFactory',
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
            templateNameEdited: false,
            editedNameIds: new Set(),
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
                this.templateNameEdited = false;
                this.editedNameIds = new Set();
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
                this.originalValueIdsByGroup = Object.fromEntries(this.templateEntity.groups.map((group) => [
                    group.id,
                    group.values.map((value) => value.id),
                ]));
                this.templateNameEdited = false;
                this.editedNameIds = new Set();
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

            if (entity === this.templateEntity) {
                this.templateNameEdited = true;
                return;
            }

            this.editedNameIds.add(entity.id);
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

            return value.surchargePrice.find((price) => price.currencyId === this.currencyId) || value.surchargePrice[0] || null;
        },

        valueGrossPrice(value) {
            return this.findDefaultCurrencyPrice(value)?.gross ?? 0;
        },

        valueNetPrice(value) {
            return this.findDefaultCurrencyPrice(value)?.net ?? 0;
        },

        setValueGrossPrice(value, gross) {
            value.surchargePrice = [{
                currencyId: this.currencyId,
                gross: Number(gross) || 0,
                net: this.valueNetPrice(value),
                linked: false,
            }];
        },

        setValueNetPrice(value, net) {
            value.surchargePrice = [{
                currencyId: this.currencyId,
                gross: this.valueGrossPrice(value),
                net: Number(net) || 0,
                linked: false,
            }];
        },

        setValueSurchargeType(value, surchargeType) {
            value.surchargeType = surchargeType;

            if (surchargeType === 'percentage') {
                value.surchargePrice = null;
                value.surchargePercentage = Number(value.surchargePercentage) || 0;
            } else {
                value.surchargePercentage = null;
                value.surchargePrice = [{
                    currencyId: this.currencyId,
                    gross: this.valueGrossPrice(value),
                    net: this.valueNetPrice(value),
                    linked: false,
                }];
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

        ensureSystemTranslation(entity, entityRepository, translationEntityName, parentField, name) {
            const systemLanguageId = Shopware.Context.api.systemLanguageId;
            let translations = entity.translations;

            if (!translations) {
                translations = new EntityCollection(
                    `${entityRepository.route}/${entity.id}/translations`,
                    translationEntityName,
                    Shopware.Context.api,
                    new Criteria(1, 25)
                );
                entity.translations = translations;
            }

            const systemTranslation = translations.find((translation) => translation.languageId === systemLanguageId);
            if (systemTranslation) {
                return;
            }

            const translationRepository = this.repositoryFactory.create(translationEntityName);
            const translation = translationRepository.create(Shopware.Context.api);
            translation[parentField] = entity.id;
            translation.languageId = systemLanguageId;
            translation.name = name;
            translations.add(translation);
        },

        async onSave() {
            const isSystemLanguage = Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
            const apiContext = Shopware.Context.api;
            const systemLanguageContext = {
                ...apiContext,
                languageId: apiContext.systemLanguageId,
            };

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
                const isNewTemplate = this.templateEntity.isNew();
                const templateId = this.templateEntity.id;
                const groups = this.templateEntity.groups.slice();
                const templateContext = isNewTemplate || this.templateNameEdited ? apiContext : systemLanguageContext;
                const templateEntity = isNewTemplate
                    ? this.templateRepository.create(templateContext, templateId)
                    : await this.templateRepository.get(templateId, templateContext, new Criteria([templateId]));

                templateEntity.active = !!this.templateEntity.active;
                templateEntity.priority = parseInt(this.templateEntity.priority, 10) || 0;

                if (isNewTemplate || this.templateNameEdited) {
                    templateEntity.name = this.templateEntity.name;
                }

                await this.templateRepository.save(templateEntity, templateContext);

                for (const group of groups) {
                    const isNewGroup = group.isNew();
                    const groupName = group.name;
                    const groupContext = isNewGroup || this.editedNameIds.has(group.id) ? apiContext : systemLanguageContext;
                    const groupEntity = isNewGroup
                        ? this.groupRepository.create(groupContext, group.id)
                        : await this.groupRepository.get(group.id, groupContext);

                    groupEntity.templateId = templateId;
                    groupEntity.position = parseInt(group.position, 10) || 0;
                    groupEntity.paletteMediaId = group.paletteMediaId || null;

                    if (isNewGroup || this.editedNameIds.has(group.id)) {
                        groupEntity.name = groupName;
                    }

                    if (isNewGroup) {
                        this.ensureSystemTranslation(
                            groupEntity,
                            this.groupRepository,
                            'jv_option_template_group_translation',
                            'jvOptionTemplateGroupId',
                            groupName
                        );
                    }

                    await this.groupRepository.save(groupEntity, groupContext);

                    for (const value of group.values) {
                        const isNewValue = value.isNew();
                        const valueName = value.name;
                        const valueContext = isNewValue || this.editedNameIds.has(value.id) ? apiContext : systemLanguageContext;
                        const valueEntity = isNewValue
                            ? this.valueRepository.create(valueContext, value.id)
                            : await this.valueRepository.get(value.id, valueContext);

                        valueEntity.groupId = group.id;
                        valueEntity.position = parseInt(value.position, 10) || 0;
                        valueEntity.colorHex = value.colorHex && value.colorHex.trim() !== '' ? value.colorHex.trim() : null;
                        valueEntity.mediaId = value.mediaId || null;
                        valueEntity.surchargeType = value.surchargeType;
                        valueEntity.surchargePrice = value.surchargeType === 'fixed' ? value.surchargePrice : null;
                        valueEntity.surchargePercentage = value.surchargeType === 'percentage'
                            ? Number(value.surchargePercentage) || 0
                            : null;

                        if (isNewValue || this.editedNameIds.has(value.id)) {
                            valueEntity.name = valueName;
                        }

                        if (isNewValue) {
                            this.ensureSystemTranslation(
                                valueEntity,
                                this.valueRepository,
                                'jv_option_template_value_translation',
                                'jvOptionTemplateValueId',
                                valueName
                            );
                        }

                        await this.valueRepository.save(valueEntity, valueContext);
                    }

                    const persistedGroup = await this.groupRepository.get(group.id, systemLanguageContext);
                    persistedGroup.defaultValueId = group.defaultValueId || null;
                    await this.groupRepository.save(persistedGroup, systemLanguageContext);
                }

                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    message: this.$t('jv-option-template.detail.saveSuccess'),
                });

                if (!this.$route.params.id) {
                    await this.$router.push({
                        name: 'jv.option.template.detail',
                        params: { id: templateId },
                    });
                }

                await this.deleteRemovedAssociations();
                await this.loadTemplate(templateId);
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('jv-option-template.detail.saveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async deleteRemovedAssociations() {
            const groups = this.templateEntity.groups;
            const currentGroupIds = new Set(groups.map((group) => group.id));
            const removedGroupIds = this.originalGroupIds.filter((id) => !currentGroupIds.has(id));

            for (const id of removedGroupIds) {
                await this.groupRepository.delete(id, Shopware.Context.api);
            }

            for (const group of groups) {
                const originalValueIds = this.originalValueIdsByGroup[group.id] || [];
                const currentValueIds = new Set(group.values.map((value) => value.id));

                for (const id of originalValueIds.filter((valueId) => !currentValueIds.has(valueId))) {
                    await this.valueRepository.delete(id, Shopware.Context.api);
                }
            }

            this.originalGroupIds = groups.map((group) => group.id);
            this.originalValueIdsByGroup = Object.fromEntries(groups.map((group) => [
                group.id,
                group.values.map((value) => value.id),
            ]));
        },

        onCancel() {
            this.$router.push({ name: 'jv.option.template.index' });
        },
    },
};
