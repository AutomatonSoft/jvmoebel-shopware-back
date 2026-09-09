/**
 * Category rail config for CMS element `jv-category-rail`.
 */
import template from './sw-cms-el-config-jv-category-rail.html.twig';
import './sw-cms-el-config-jv-category-rail.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            mediaModalIndex: null,
        };
    },

    computed: {
        categories() {
            return this.ensureCategories();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        layoutOptions() {
            return [
                { value: 'rail', label: this.$t('cms.elements.jv-category-rail.config.layout.rail') },
                { value: 'grid', label: this.$t('cms.elements.jv-category-rail.config.layout.grid') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-category-rail');
        this.ensureCategories();
        this.ensureUniqueCategoryIds();
        this.ensureViewAll();
    },

    methods: {
        onUpdate() {
            this.syncCategoryPositions();
            this.ensureUniqueCategoryIds();
            this.$emit('element-update', this.element);
        },

        syncCategoryPositions() {
            this.categories.forEach((category, index) => {
                category.position = index;
            });
        },

        ensureCategories() {
            if (!Array.isArray(this.element.config.categories.value)) {
                this.element.config.categories.value = [];
            }

            return this.element.config.categories.value;
        },

        ensureViewAll() {
            if (!this.element.config.viewAll.value || typeof this.element.config.viewAll.value !== 'object') {
                this.element.config.viewAll.value = {
                    label: '',
                    url: '',
                };
            }
        },

        ensureUniqueCategoryIds() {
            const seenIds = new Set();

            this.categories.forEach((category, index) => {
                let id = typeof category.id === 'string' ? category.id.trim() : '';

                if (!id || seenIds.has(id)) {
                    id = category.categoryId || Utils.createId();
                    if (seenIds.has(id)) {
                        id = `${id}-${index}`;
                    }
                    category.id = id;
                }

                seenIds.add(id);
            });
        },

        addCategory() {
            this.categories.push({
                id: Utils.createId(),
                categoryId: null,
                label: '',
                url: '',
                position: this.categories.length,
                imageMedia: null,
            });
            this.onUpdate();
        },

        removeCategory(index) {
            this.categories.splice(index, 1);
            this.onUpdate();
        },

        categoryUploadTag(index) {
            return `cms-element-jv-category-rail-category-${this.element.id}-${index}`;
        },

        categoryPreviewSource(category) {
            if (category?.image?.id) {
                return category.image;
            }

            return category.imageMedia;
        },

        async onCategoryUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const category = this.categories[index];
            if (!category) {
                return;
            }

            category.imageMedia = mediaEntity.id;
            category.image = mediaEntity;
            this.onUpdate();
        },

        onCategoryRemove(index) {
            const category = this.categories[index];
            if (!category) {
                return;
            }

            category.imageMedia = null;
            category.image = null;
            this.onUpdate();
        },

        onOpenCategoryMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseCategoryMediaModal() {
            this.mediaModalIndex = null;
        },

        onCategorySelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const category = this.categories[index];
            if (!media || !category) {
                return;
            }

            category.imageMedia = media.id;
            category.image = media;
            this.onUpdate();
            this.onCloseCategoryMediaModal();
        },
    },
};
