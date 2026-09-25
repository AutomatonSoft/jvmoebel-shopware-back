import template from './jv-option-template-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            templates: null,
            sortBy: 'priority',
            sortDirection: 'DESC',
            isLoading: false,
            total: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        templateRepository() {
            return this.repositoryFactory.create('jv_option_template');
        },

        templateCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('groups');
            criteria.addAssociation('productStreams');

            return criteria;
        },

        columns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'jv-option-template.list.columnName',
                    routerLink: 'jv.option.template.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    dataIndex: 'active',
                    label: 'jv-option-template.list.columnActive',
                    inlineEdit: 'boolean',
                    align: 'center',
                    allowResize: true,
                    width: '100px',
                },
                {
                    property: 'priority',
                    dataIndex: 'priority',
                    label: 'jv-option-template.list.columnPriority',
                    inlineEdit: 'number',
                    align: 'right',
                    allowResize: true,
                    width: '120px',
                },
                {
                    property: 'groups',
                    dataIndex: 'groups',
                    label: 'jv-option-template.list.columnGroupsCount',
                    sortable: false,
                    allowResize: true,
                    width: '120px',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'jv-option-template.list.columnCreatedAt',
                    allowResize: true,
                    width: '180px',
                },
            ];
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            try {
                const result = await this.templateRepository.search(this.templateCriteria, Shopware.Context.api);
                this.templates = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({
                    message: error.message || this.$t('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
};
