/**
 * Canvas preview for CMS element `jv-category-rail`.
 */
import template from './sw-cms-el-jv-category-rail.html.twig';
import './sw-cms-el-jv-category-rail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            categoryImageUrls: {},
            categoryImageLoadToken: 0,
        };
    },

    computed: {
        title() {
            return (this.element?.config?.title?.value || '').trim();
        },

        eyebrow() {
            return (this.element?.config?.eyebrow?.value || '').trim();
        },

        description() {
            return (this.element?.config?.description?.value || '').trim();
        },

        layout() {
            const value = (this.element?.config?.layout?.value || 'rail').trim();

            return value === 'grid' ? 'grid' : 'rail';
        },

        categories() {
            const categories = this.element?.config?.categories?.value;

            return Array.isArray(categories) ? categories.filter((category) => {
                if (!category || typeof category !== 'object') {
                    return false;
                }

                const label = typeof category.label === 'string' ? category.label.trim() : '';

                return label !== '' || category.categoryId || category.imageMedia || category.image;
            }) : [];
        },

        viewAllLabel() {
            return (this.element?.config?.viewAll?.value?.label || '').trim();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },
    },

    watch: {
        categories: {
            deep: true,
            immediate: true,
            handler() {
                this.loadCategoryImages();
            },
        },
    },

    created() {
        this.initElementConfig('jv-category-rail');
    },

    methods: {
        categoryKey(index) {
            return `category-${index}`;
        },

        categoryImageUrl(category, index) {
            return this.resolveCategoryImageUrl(category, index);
        },

        resolveCategoryImageUrl(category, index) {
            const inlineUrl = this.extractMediaUrl(category?.image)
                || this.extractMediaUrl(category?.imageMedia);

            if (inlineUrl) {
                return inlineUrl;
            }

            return this.categoryImageUrls[this.categoryKey(index)] || null;
        },

        extractMediaUrl(value) {
            if (!value) {
                return null;
            }

            if (typeof value === 'string') {
                return null;
            }

            if (typeof value === 'object' && typeof value.url === 'string' && value.url !== '') {
                return value.url;
            }

            if (typeof value === 'object' && Array.isArray(value.thumbnails) && value.thumbnails.length > 0) {
                const thumbnail = value.thumbnails.find((entry) => typeof entry?.url === 'string' && entry.url !== '');

                return thumbnail?.url || null;
            }

            return null;
        },

        normalizeUuid(value) {
            if (!value) {
                return null;
            }

            if (typeof value === 'string') {
                return value;
            }

            if (typeof value === 'object' && typeof value.id === 'string') {
                return value.id;
            }

            return null;
        },

        async loadCategoryImages() {
            const token = this.categoryImageLoadToken + 1;
            this.categoryImageLoadToken = token;
            const urls = {};

            await Promise.all(this.categories.map(async (category, index) => {
                const key = this.categoryKey(index);

                if (this.resolveCategoryImageUrl(category, index)) {
                    urls[key] = this.resolveCategoryImageUrl(category, index);

                    return;
                }

                const mediaId = this.normalizeUuid(category?.imageMedia)
                    || (typeof category?.image === 'object' ? this.normalizeUuid(category.image.id) : null);

                if (mediaId) {
                    try {
                        const criteria = new Criteria(1, 1);
                        criteria.addAssociation('thumbnails');
                        const media = await this.mediaRepository.get(mediaId, Shopware.Context.api, criteria);
                        if (token !== this.categoryImageLoadToken) {
                            return;
                        }

                        const mediaUrl = this.extractMediaUrl(media);
                        if (mediaUrl) {
                            urls[key] = mediaUrl;
                        }
                    } catch {
                        // Preview keeps placeholder when media cannot be loaded.
                    }

                    return;
                }

                const categoryId = this.normalizeUuid(category?.categoryId);
                if (!categoryId) {
                    return;
                }

                try {
                    const criteria = new Criteria(1, 1);
                    criteria.addAssociation('media');
                    criteria.getAssociation('media').addAssociation('thumbnails');
                    const entity = await this.categoryRepository.get(categoryId, Shopware.Context.api, criteria);
                    if (token !== this.categoryImageLoadToken) {
                        return;
                    }

                    const mediaUrl = this.extractMediaUrl(entity?.media);
                    if (mediaUrl) {
                        urls[key] = mediaUrl;
                    }
                } catch {
                    // Preview keeps placeholder when category media cannot be loaded.
                }
            }));

            if (token === this.categoryImageLoadToken) {
                this.categoryImageUrls = urls;
            }
        },
    },
};
