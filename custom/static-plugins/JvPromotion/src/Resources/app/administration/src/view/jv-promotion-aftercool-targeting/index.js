import template from './jv-promotion-aftercool-targeting.html.twig';
import './jv-promotion-aftercool-targeting.scss';

const { Mixin } = Shopware;

export default {
    template,

    inject: ['acl', 'jvPromotionApiService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },
        isCreateMode: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaving: false,
            isSaveSuccessful: false,
            previewLoading: false,
            factories: [],
            collections: [],
            factoryId: null,
            collectionId: null,
            eanInput: '',
            discountPercent: 10,
            targets: [],
            previewItems: [],
            previewTotal: 0,
            previewPage: 1,
            previewLimit: 25,
            warnings: [],
            prefixInput: '',
            factoryPrefixes: [],
            selectedFactory: null,
            selectedCollection: null,
            factorySearchTimer: null,
            collectionSearchTimer: null,
            lastFactoryQuery: null,
            factoryQuery: '',
            collectionQuery: '',
            factoryListOpen: false,
            collectionListOpen: false,
            collectionsLoading: false,
            collectionRequestId: 0,
            factoryListCloseTimer: null,
            collectionListCloseTimer: null,
        };
    },

    computed: {
        promotionId() {
            return this.promotion?.id ?? null;
        },

        factoryOptions() {
            const options = this.factories.map((factory) => ({
                id: factory.id,
                name: factory.productCount > 0
                    ? `${factory.name} (${factory.productCount})`
                    : factory.name,
            }));

            if (this.selectedFactory && !options.some((option) => option.id === this.selectedFactory.id)) {
                return [this.selectedFactory, ...options];
            }

            return options;
        },

        visibleFactoryOptions() {
            return this.filterOptions(this.factoryOptions, this.factoryQuery, this.selectedFactory?.name);
        },

        visibleCollectionOptions() {
            return this.collectionOptions.slice(0, 100);
        },

        collectionOptions() {
            const options = this.collections.map((collection) => ({
                id: String(collection.id),
                name: collection.name,
            }));

            if (this.selectedCollection && !options.some((option) => option.id === this.selectedCollection.id)) {
                return [this.selectedCollection, ...options];
            }

            return options;
        },

        previewColumns() {
            return [
                { property: 'name', label: this.$t('jv-promotion.aftercool.columns.name') },
                { property: 'ean', label: this.$t('jv-promotion.aftercool.columns.ean') },
                { property: 'factoryName', label: this.$t('jv-promotion.aftercool.columns.factory') },
                { property: 'collectionName', label: this.$t('jv-promotion.aftercool.columns.collection') },
                { property: 'basePrice', label: this.$t('jv-promotion.aftercool.columns.basePrice') },
            ];
        },

        canSave() {
            return this.acl.can('promotion.editor')
                && this.targets.length > 0
                && this.discountPercent > 0
                && this.discountPercent <= 100
                && !this.isSaving;
        },
    },

    watch: {
        promotionId: {
            immediate: true,
            handler() {
                if (!this.isCreateMode && this.promotionId) {
                    this.initialize();
                }
            },
        },
        factoryId(factoryId) {
            this.collectionId = null;
            this.selectedCollection = null;
            const match = this.factories.find((factory) => factory.id === factoryId);
            this.selectedFactory = match
                ? {
                    id: match.id,
                    name: match.productCount > 0
                        ? `${match.name} (${match.productCount})`
                        : match.name,
                }
                : (factoryId ? this.selectedFactory : null);
            this.factoryQuery = this.selectedFactory?.name ?? '';
            this.collectionQuery = '';
            this.collections = [];
            this.loadCollections();
        },
        collectionId(collectionId) {
            const match = this.collections.find((collection) => String(collection.id) === String(collectionId));
            this.selectedCollection = match
                ? { id: String(match.id), name: match.name }
                : (collectionId ? this.selectedCollection : null);
            if (!collectionId) {
                this.collectionQuery = '';
            }
        },
        targets: {
            deep: true,
            handler() {
                if (this.targets.length) {
                    this.loadPreview();
                } else {
                    this.previewItems = [];
                    this.previewTotal = 0;
                }
            },
        },
    },

    methods: {
        async initialize() {
            this.isLoading = true;

            try {
                await Promise.all([
                    this.loadFactories(),
                    this.loadFactoryPrefixes(),
                    this.loadExistingTargets(),
                ]);
            } catch (error) {
                this.createNotificationError({ message: this.apiError(error, 'jv-promotion.aftercool.loadError') });
            } finally {
                this.isLoading = false;
            }
        },

        filterOptions(options, query, selectedName) {
            const term = (query || '').trim().toLowerCase();
            const source = !term || (selectedName && term === selectedName.toLowerCase())
                ? options
                : options.filter((option) => option.name.toLowerCase().includes(term));

            return source.slice(0, 100);
        },

        openFactoryList() {
            window.clearTimeout(this.factoryListCloseTimer);
            this.factoryListOpen = true;
        },

        closeFactoryList() {
            window.clearTimeout(this.factoryListCloseTimer);
            this.factoryListCloseTimer = window.setTimeout(() => {
                this.factoryListOpen = false;
            }, 150);
        },

        openCollectionList() {
            window.clearTimeout(this.collectionListCloseTimer);
            this.collectionListOpen = true;
        },

        closeCollectionList() {
            window.clearTimeout(this.collectionListCloseTimer);
            this.collectionListCloseTimer = window.setTimeout(() => {
                this.collectionListOpen = false;
            }, 150);
        },

        onFactoryQueryInput(event) {
            this.factoryQuery = event.target.value;
            this.factoryListOpen = true;
        },

        onCollectionQueryInput(event) {
            this.collectionQuery = event.target.value;
            this.collectionListOpen = true;
            const term = (event.target.value || '').trim();
            if (term !== '') {
                this.collections = [];
                this.collectionsLoading = true;
            }
            this.onCollectionSearch(term);
        },

        selectFactory(option) {
            window.clearTimeout(this.factoryListCloseTimer);
            this.factoryQuery = option.name;
            this.factoryListOpen = false;
            if (this.factoryId !== option.id) {
                this.factoryId = option.id;
            }
        },

        selectCollection(option) {
            window.clearTimeout(this.collectionListCloseTimer);
            this.collectionQuery = option.name;
            this.collectionListOpen = false;
            this.selectedCollection = option;
            this.collectionId = option.id;
        },

        onFactorySearch(term) {
            const value = (term || '').trim();
            if (value === this.lastFactoryQuery) {
                return;
            }

            window.clearTimeout(this.factorySearchTimer);
            this.factorySearchTimer = window.setTimeout(() => {
                this.loadFactories(value);
            }, 300);
        },

        onCollectionSearch(term) {
            window.clearTimeout(this.collectionSearchTimer);
            this.collectionSearchTimer = window.setTimeout(() => {
                this.loadCollections(term);
            }, 300);
        },

        async loadFactories(query = '') {
            const term = (query || '').trim();
            const params = { limit: 1000 };
            if (term) {
                params.q = term;
            }

            const response = await this.jvPromotionApiService.getFactories(params);
            this.lastFactoryQuery = term;
            this.factories = response.data.data ?? [];
        },

        async loadFactoryPrefixes() {
            const response = await this.jvPromotionApiService.getFactoryPrefixes();
            this.factoryPrefixes = response.data.data ?? [];
        },

        async loadCollections(query = '') {
            if (!this.factoryId) {
                this.collections = [];
                this.collectionsLoading = false;
                return;
            }

            const requestId = ++this.collectionRequestId;
            this.collectionsLoading = true;
            const params = {
                factoryId: this.factoryId,
                limit: 50,
                offset: 0,
            };
            const term = (query || '').trim();
            if (term) {
                params.q = term;
            }

            try {
                const response = await this.jvPromotionApiService.getCollections(params);
                if (requestId !== this.collectionRequestId) {
                    return;
                }
                this.collections = response.data.data ?? [];
            } catch (error) {
                if (requestId !== this.collectionRequestId) {
                    return;
                }
                this.collections = [];
                this.createNotificationError({ message: this.apiError(error, 'jv-promotion.aftercool.loadError') });
            } finally {
                if (requestId === this.collectionRequestId) {
                    this.collectionsLoading = false;
                }
            }
        },

        async loadExistingTargets() {
            if (!this.promotionId) {
                return;
            }

            try {
                const response = await this.jvPromotionApiService.getTargets({ promotionId: this.promotionId });
                this.targets = response.data.targets ?? [];
                if (response.data.discountPercent) {
                    this.discountPercent = response.data.discountPercent;
                }
            } catch (error) {
                this.createNotificationError({ message: this.apiError(error, 'jv-promotion.aftercool.loadError') });
            }
        },

        addFactoryTarget() {
            if (!this.factoryId) {
                return;
            }

            if (this.targets.some((target) => target.type === 'factory' && String(target.factoryId) === String(this.factoryId))) {
                this.notifyAlreadyAdded();
                return;
            }

            this.targets.push({ type: 'factory', factoryId: this.factoryId });
        },

        addCollectionTarget() {
            if (!this.factoryId || !this.collectionId) {
                return;
            }

            const exists = this.targets.some((target) => target.type === 'collection'
                && String(target.factoryId) === String(this.factoryId)
                && String(target.stammartikelId) === String(this.collectionId));
            if (exists) {
                this.notifyAlreadyAdded();
                return;
            }

            this.targets.push({
                type: 'collection',
                factoryId: this.factoryId,
                stammartikelId: this.collectionId,
                collectionName: this.selectedCollection?.name || this.collectionQuery,
            });
        },

        addEanTarget() {
            const ean = (this.eanInput || '').trim();
            if (!ean) {
                return;
            }

            const exists = this.targets.some((target) => (target.type === 'product' || target.type === 'ean')
                && String(target.ean || '').trim().toLowerCase() === ean.toLowerCase());
            if (exists) {
                this.notifyAlreadyAdded();
                return;
            }

            this.targets.push({ type: 'product', ean });
            this.eanInput = '';
        },

        addFactoryPrefixTarget() {
            const prefix = (this.prefixInput || '').trim();
            if (!prefix) {
                return;
            }

            const exists = this.targets.some((target) => target.type === 'factory_prefix'
                && String(target.sourceFilePrefix || '').trim().toLowerCase() === prefix.toLowerCase());
            if (exists) {
                this.notifyAlreadyAdded();
                return;
            }

            this.targets.push({ type: 'factory_prefix', sourceFilePrefix: prefix });
            this.prefixInput = '';
        },

        notifyAlreadyAdded() {
            this.createNotificationWarning({ message: this.$t('jv-promotion.aftercool.alreadyAdded') });
        },

        removeTarget(index) {
            this.targets.splice(index, 1);
        },

        targetKey(target, index) {
            return `${target.type}-${target.factoryId || ''}-${target.stammartikelId || ''}-${target.ean || ''}-${index}`;
        },

        formatTarget(target) {
            if (target.type === 'factory') {
                const factory = this.factories.find((item) => item.id === target.factoryId);
                return `factory: ${factory?.name ?? target.factoryId}`;
            }

            if (target.type === 'collection') {
                const collection = this.collections.find((item) => item.id === target.stammartikelId);
                return `collection: ${collection?.name ?? target.stammartikelId}`;
            }

            if (target.type === 'product' || target.type === 'ean') {
                return `product: ${target.ean || target.productId}`;
            }

            if (target.type === 'factory_prefix') {
                return `prefix: ${target.sourceFilePrefix}`;
            }

            return JSON.stringify(target);
        },

        async loadPreview() {
            if (!this.targets.length) {
                return;
            }

            this.previewLoading = true;

            try {
                const response = await this.jvPromotionApiService.preview({
                    targets: this.targets,
                    discountPercent: this.discountPercent,
                    limit: this.previewLimit,
                    offset: (this.previewPage - 1) * this.previewLimit,
                });

                this.previewItems = response.data.items ?? [];
                this.previewTotal = response.data.total ?? 0;
                this.warnings = response.data.warnings ?? [];
            } catch (error) {
                this.createNotificationError({ message: this.apiError(error, 'jv-promotion.aftercool.loadError') });
            } finally {
                this.previewLoading = false;
            }
        },

        onPreviewPageChange({ page, limit }) {
            this.previewPage = page;
            this.previewLimit = limit;
            this.loadPreview();
        },

        async saveTargeting() {
            if (!this.canSave || !this.promotion) {
                return;
            }

            this.isSaving = true;

            try {
                const payload = {
                    promotionId: this.promotion.id,
                    name: this.promotion.name,
                    active: this.promotion.active,
                    validFrom: this.promotion.validFrom,
                    validUntil: this.promotion.validUntil,
                    discountPercent: this.discountPercent,
                    targets: this.targets,
                };

                const response = await this.jvPromotionApiService.sync(payload);
                this.warnings = response.data.warnings ?? [];
                this.isSaveSuccessful = true;
                this.createNotificationSuccess({ message: this.$t('jv-promotion.aftercool.saveSuccess') });
                await this.loadExistingTargets();
                await this.loadPreview();
            } catch (error) {
                this.createNotificationError({ message: this.apiError(error, 'jv-promotion.aftercool.saveError') });
            } finally {
                this.isSaving = false;
            }
        },

        onSaveSuccessful() {
            this.isSaveSuccessful = false;
        },

        formatPrice(value) {
            return new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(value);
        },

        apiError(error, fallbackKey) {
            const detail = error?.response?.data?.errors?.[0]?.detail;
            return detail || this.$t(fallbackKey);
        },
    },
};
