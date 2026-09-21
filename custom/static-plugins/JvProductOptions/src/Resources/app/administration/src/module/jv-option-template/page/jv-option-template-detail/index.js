import template from './jv-option-template-detail.html.twig';

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
            groups: [],
            currencyId: Shopware.Context.app.systemCurrencyId,
            originalGroups: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.templateEntity?.name || this.$t('jv-option-template.detail.titleNew');
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

            const templateId = this.$route.params.id;

            if (templateId) {
                await this.loadTemplate(templateId);
            } else {
                this.templateEntity = this.templateRepository.create(Shopware.Context.api);
                this.templateEntity.active = true;
                this.templateEntity.priority = 0;
                this.templateEntity.name = '';
                this.templateEntity.productStreams = new EntityCollection(
                    `${this.templateRepository.route}/${this.templateEntity.id}/product-streams`,
                    'product_stream',
                    Shopware.Context.api,
                    new Criteria(1, 25)
                );
                this.groups = [];
                this.originalGroups = [];
            }

            this.isLoading = false;
        },

        async loadTemplate(id) {
            const criteria = new Criteria([id]);
            criteria.addAssociation('productStreams');
            criteria.addAssociation('translations');
            criteria.addAssociation('groups.translations');
            criteria.addAssociation('groups.values.media');
            criteria.addAssociation('groups.values.translations');
            criteria.addSorting(Criteria.sort('groups.position', 'ASC'));
            criteria.addSorting(Criteria.sort('groups.values.position', 'ASC'));

            try {
                this.templateEntity = await this.templateRepository.get(id, Shopware.Context.api, criteria);

                const rawGroups = this.templateEntity.groups ? this.templateEntity.groups.slice() : [];
                rawGroups.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
                this.originalGroups = rawGroups.map((group) => ({
                    id: group.id,
                    valueIds: group.values ? group.values.map((value) => value.id) : [],
                    defaultValueId: group.defaultValueId || null,
                }));

                this.groups = rawGroups.map((group) => {
                    const rawValues = group.values ? group.values.slice() : [];
                    rawValues.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

                    const values = rawValues.map((val) => {
                        let grossPrice = 0;
                        let netPrice = 0;

                        if (Array.isArray(val.surchargePrice)) {
                            const defaultCurr = val.surchargePrice.find((p) => p.currencyId === this.currencyId) || val.surchargePrice[0];
                            if (defaultCurr) {
                                grossPrice = defaultCurr.gross ?? 0;
                                netPrice = defaultCurr.net ?? 0;
                            }
                        }

                        return {
                            entity: val,
                            id: val.id,
                            name: val.name || '',
                            position: val.position ?? 0,
                            colorHex: val.colorHex || '',
                            mediaId: val.mediaId || null,
                            surchargeType: val.surchargeType || 'fixed',
                            grossPrice,
                            netPrice,
                            surchargePercentage: val.surchargePercentage ?? 0,
                            isNew: false,
                        };
                    });

                    return {
                        entity: group,
                        id: group.id,
                        name: group.name || '',
                        position: group.position ?? 0,
                        defaultValueId: group.defaultValueId || null,
                        values,
                        isNew: false,
                    };
                });
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('jv-option-template.detail.saveError'),
                });
            }
        },

        addGroup() {
            const entity = this.groupRepository.create(Shopware.Context.api);
            const newGroup = {
                entity,
                id: entity.id,
                name: '',
                position: this.groups.length + 1,
                defaultValueId: null,
                values: [],
                isNew: true,
            };

            this.groups.push(newGroup);
        },

        removeGroup(groupIndex) {
            this.groups.splice(groupIndex, 1);
        },

        setSystemTranslations(entity, entityRepository, translationEntityName, parentField, name) {
            const systemLanguageId = Shopware.Context.api.systemLanguageId;
            const languageIds = [
                systemLanguageId,
                Shopware.Context.api.languageId,
            ].filter((languageId, index, ids) => languageId && ids.indexOf(languageId) === index);

            if (!entity.translations) {
                entity.translations = new EntityCollection(
                    `${entityRepository.route}/${entity.id}/translations`,
                    translationEntityName,
                    Shopware.Context.api,
                    new Criteria(1, 25)
                );
            }

            languageIds.forEach((languageId) => {
                let translation = entity.translations.find((item) => item.languageId === languageId);
                if (!translation) {
                    const translationRepository = this.repositoryFactory.create(translationEntityName);
                    translation = translationRepository.create(Shopware.Context.api);
                    entity.translations.add(translation);
                }

                translation[parentField] = entity.id;
                translation.languageId = languageId;
                translation.name = name;
            });
        },

        addValue(group) {
            const entity = this.valueRepository.create(Shopware.Context.api);
            const newValue = {
                entity,
                id: entity.id,
                name: '',
                position: group.values.length + 1,
                colorHex: '',
                mediaId: null,
                surchargeType: 'fixed',
                grossPrice: 0,
                netPrice: 0,
                surchargePercentage: 0,
                isNew: true,
            };

            group.values.push(newValue);

            if (!group.defaultValueId) {
                group.defaultValueId = newValue.id;
            }
        },

        removeValue(group, valueIndex) {
            const removed = group.values.splice(valueIndex, 1)[0];
            if (group.defaultValueId === removed.id) {
                group.defaultValueId = group.values.length > 0 ? group.values[0].id : null;
            }
        },

        onGrossPriceChange(val) {
            if (val.grossPrice !== null && val.grossPrice !== undefined) {
                val.netPrice = Math.round((Number(val.grossPrice) / 1.19) * 100) / 100;
            }
        },

        async onSave() {
            this.isLoading = true;

            try {
                if (!this.templateEntity.name || this.templateEntity.name.trim() === '') {
                    this.createNotificationError({
                        message: this.$t('jv-option-template.detail.placeholderName'),
                    });
                    this.isLoading = false;
                    return;
                }

                this.setSystemTranslations(
                    this.templateEntity,
                    this.templateRepository,
                    'jv_option_template_translation',
                    'jvOptionTemplateId',
                    this.templateEntity.name
                );
                await this.templateRepository.save(this.templateEntity, Shopware.Context.api);

                for (const group of this.groups) {
                    let groupEntity = group.entity || this.groupRepository.create(Shopware.Context.api, group.id);
                    const originalGroup = this.originalGroups.find((item) => item.id === group.id);
                    const currentValueIds = new Set(group.values.map((value) => value.id));
                    const preservedDefaultValueId = originalGroup?.defaultValueId && currentValueIds.has(originalGroup.defaultValueId)
                        ? originalGroup.defaultValueId
                        : null;
                    const defaultValueId = group.defaultValueId || preservedDefaultValueId || group.values[0]?.id || null;

                    if (originalGroup) {
                        for (const originalValueId of originalGroup.valueIds) {
                            if (!currentValueIds.has(originalValueId)) {
                                await this.valueRepository.delete(originalValueId, Shopware.Context.api);
                            }
                        }
                    }

                    groupEntity.templateId = this.templateEntity.id;
                    groupEntity.name = group.name;
                    groupEntity.position = parseInt(group.position, 10) || 0;
                    groupEntity.defaultValueId = null;
                    this.setSystemTranslations(
                        groupEntity,
                        this.groupRepository,
                        'jv_option_template_group_translation',
                        'jvOptionTemplateGroupId',
                        group.name
                    );

                    const values = new EntityCollection(
                        `${this.groupRepository.route}/${groupEntity.id}/values`,
                        'jv_option_template_value',
                        Shopware.Context.api,
                        new Criteria(1, 25)
                    );

                    group.values.forEach((value) => {
                        const valueEntity = value.entity || this.valueRepository.create(Shopware.Context.api, value.id);
                        valueEntity.groupId = groupEntity.id;
                        valueEntity.name = value.name;
                        valueEntity.position = parseInt(value.position, 10) || 0;
                        valueEntity.colorHex = value.colorHex && value.colorHex.trim() !== '' ? value.colorHex.trim() : null;
                        valueEntity.mediaId = value.mediaId || null;
                        valueEntity.surchargeType = value.surchargeType;
                        valueEntity.surchargePrice = value.surchargeType === 'fixed' ? [{
                            currencyId: this.currencyId,
                            gross: Number(value.grossPrice) || 0,
                            net: Number(value.netPrice) || 0,
                            linked: false,
                        }] : null;
                        valueEntity.surchargePercentage = value.surchargeType === 'percentage'
                            ? Number(value.surchargePercentage) || 0
                            : null;
                        this.setSystemTranslations(
                            valueEntity,
                            this.valueRepository,
                            'jv_option_template_value_translation',
                            'jvOptionTemplateValueId',
                            value.name
                        );
                        values.add(valueEntity);
                    });

                    groupEntity.values = values;
                    await this.groupRepository.save(groupEntity, Shopware.Context.api);

                    for (const valueEntity of values) {
                        await this.valueRepository.save(valueEntity, Shopware.Context.api);
                    }

                    groupEntity = await this.groupRepository.get(groupEntity.id, Shopware.Context.api);
                    group.entity = groupEntity;
                    groupEntity.defaultValueId = defaultValueId;
                    await this.groupRepository.save(groupEntity, Shopware.Context.api);
                }

                const currentGroupIds = new Set(this.groups.map((group) => group.id));
                for (const originalGroup of this.originalGroups) {
                    if (!currentGroupIds.has(originalGroup.id)) {
                        await this.groupRepository.delete(originalGroup.id, Shopware.Context.api);
                    }
                }

                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    message: this.$t('jv-option-template.detail.saveSuccess'),
                });

                if (!this.$route.params.id) {
                    this.$router.push({
                        name: 'jv.option.template.detail',
                        params: { id: this.templateEntity.id },
                    });
                } else {
                    await this.loadTemplate(this.templateEntity.id);
                }
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
