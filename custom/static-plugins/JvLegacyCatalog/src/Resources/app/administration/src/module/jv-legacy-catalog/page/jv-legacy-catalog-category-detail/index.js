import template from './jv-legacy-catalog-category-detail.html.twig';
import './jv-legacy-catalog-category-detail.scss';

const { Mixin } = Shopware;

export default {
    template,

    inject: ['jvLegacyCatalogApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        sourceId: {
            type: String,
            required: true,
        },
        categoryId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            detail: null,
            isLoading: false,
            loadError: null,
        };
    },

    computed: {
        title() {
            if (!this.detail) return this.$t('jv-legacy-catalog.general.categoryDetailPageTitle');

            return this.detail.displayName + ' (#' + this.detail.sourceCategoryId + ')';
        },
    },

    created() {
        this.loadCategory();
    },

    methods: {
        async loadCategory() {
            this.isLoading = true;
            this.loadError = null;
            try {
                if (!/^[0-9a-f]{32}$/i.test(this.sourceId) || !/^[0-9a-f]{32}$/i.test(this.categoryId)) {
                    throw new Error(this.$t('jv-legacy-catalog.errors.invalidCategoryLink'));
                }

                const response = await this.jvLegacyCatalogApiService.category(
                    this.sourceId.toLowerCase(),
                    this.categoryId.toLowerCase(),
                );
                this.detail = response.data.data;
            } catch (error) {
                this.loadError = error?.response?.data?.errors?.[0]?.detail ?? error?.message ?? this.$t('jv-legacy-catalog.errors.request');
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },
    },
};
