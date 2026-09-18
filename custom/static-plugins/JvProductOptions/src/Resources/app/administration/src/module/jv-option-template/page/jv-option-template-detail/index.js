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
                    this.templateRepository.route,
                    'product_stream',
                    Shopware.Context.api
                );
                this.groups = [];
            }

            this.isLoading = false;
        },

        async loadTemplate(id) {
            const criteria = new Criteria([id]);
            criteria.addAssociation('productStreams');
            criteria.addAssociation('groups.values.media');
            criteria.addSorting(Criteria.sort('groups.position', 'ASC'));
            criteria.addSorting(Criteria.sort('groups.values.position', 'ASC'));

            try {
                this.templateEntity = await this.templateRepository.get(id, Shopware.Context.api, criteria);

                const rawGroups = this.templateEntity.groups ? this.templateEntity.groups.slice() : [];
                rawGroups.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

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
            const newGroup = {
                id: Shopware.Utils.createId(),
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

        addValue(group) {
            const newValue = {
                id: Shopware.Utils.createId(),
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

                const templatePayload = {
                    id: this.templateEntity.id,
                    name: this.templateEntity.name,
                    active: !!this.templateEntity.active,
                    priority: parseInt(this.templateEntity.priority, 10) || 0,
                };

                if (this.templateEntity.productStreams) {
                    templatePayload.productStreams = this.templateEntity.productStreams.map((s) => ({ id: s.id }));
                }

                const groupsPayload = this.groups.map((g) => {
                    const valuesPayload = g.values.map((v) => {
                        const valItem = {
                            id: v.id,
                            groupId: g.id,
                            name: v.name,
                            position: parseInt(v.position, 10) || 0,
                            colorHex: v.colorHex && v.colorHex.trim() !== '' ? v.colorHex.trim() : null,
                            mediaId: v.mediaId || null,
                            surchargeType: v.surchargeType,
                        };

                        if (v.surchargeType === 'fixed') {
                            valItem.surchargePrice = [
                                {
                                    currencyId: this.currencyId,
                                    gross: Number(v.grossPrice) || 0,
                                    net: Number(v.netPrice) || 0,
                                    linked: false,
                                },
                            ];
                            valItem.surchargePercentage = null;
                        } else {
                            valItem.surchargePercentage = Number(v.surchargePercentage) || 0;
                            valItem.surchargePrice = null;
                        }

                        return valItem;
                    });

                    return {
                        id: g.id,
                        templateId: this.templateEntity.id,
                        name: g.name,
                        position: parseInt(g.position, 10) || 0,
                        defaultValueId: g.defaultValueId || null,
                        values: valuesPayload,
                    };
                });

                templatePayload.groups = groupsPayload;

                await this.templateRepository.save(templatePayload, Shopware.Context.api);

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
