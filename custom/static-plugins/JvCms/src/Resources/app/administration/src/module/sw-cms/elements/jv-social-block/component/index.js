/** Canvas preview for CMS element `jv-social-block`. */
import template from './sw-cms-el-jv-social-block.html.twig';
import './sw-cms-el-jv-social-block.scss';

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
            itemImageUrls: {},
            itemImageLoadToken: 0,
        };
    },

    computed: {
        title() {
            const value = this.element?.config?.title?.value;

            return typeof value === 'string' ? value.trim() : '';
        },

        items() {
            return this.collection(this.element?.config?.items?.value).map((item, index) => ({
                id: typeof item?.id === 'string' ? item.id : '',
                name: typeof item?.name === 'string' ? item.name.trim() : '',
                image: item?.image,
                imageMedia: item?.imageMedia,
                originalIndex: index,
            }));
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    watch: {
        items: {
            deep: true,
            immediate: true,
            handler() {
                this.loadItemImages();
            },
        },
    },

    created() {
        this.initElementConfig('jv-social-block');
    },

    methods: {
        collection(value) {
            if (Array.isArray(value)) {
                return value;
            }
            if (value && typeof value === 'object') {
                return Object.values(value);
            }

            return [];
        },

        itemKey(item, index) {
            return item.id || `item-${index}`;
        },

        itemImageUrl(item, index) {
            return this.extractMediaUrl(item.image)
                || this.extractMediaUrl(item.imageMedia)
                || this.itemImageUrls[this.itemKey(item, index)]
                || null;
        },

        extractMediaUrl(value) {
            if (!value || typeof value === 'string') {
                return null;
            }

            if (typeof value.url === 'string' && value.url !== '') {
                return value.url;
            }

            if (Array.isArray(value.thumbnails)) {
                return value.thumbnails.find((entry) => typeof entry?.url === 'string' && entry.url !== '')?.url || null;
            }

            return null;
        },

        normalizeUuid(value) {
            if (typeof value === 'string') {
                return value;
            }
            if (value && typeof value === 'object' && typeof value.id === 'string') {
                return value.id;
            }

            return null;
        },

        async loadItemImages() {
            const token = this.itemImageLoadToken + 1;
            this.itemImageLoadToken = token;
            const urls = {};

            await Promise.all(this.items.map(async (item, index) => {
                const key = this.itemKey(item, index);
                const inlineUrl = this.extractMediaUrl(item.image) || this.extractMediaUrl(item.imageMedia);
                if (inlineUrl) {
                    urls[key] = inlineUrl;
                    return;
                }

                const mediaId = this.normalizeUuid(item.imageMedia);
                if (!mediaId) {
                    return;
                }

                try {
                    const criteria = new Criteria(1, 1);
                    criteria.addAssociation('thumbnails');
                    const media = await this.mediaRepository.get(mediaId, Shopware.Context.api, criteria);
                    if (token !== this.itemImageLoadToken) {
                        return;
                    }

                    const mediaUrl = this.extractMediaUrl(media);
                    if (mediaUrl) {
                        urls[key] = mediaUrl;
                    }
                } catch {
                    // Canvas keeps the placeholder when media cannot be loaded.
                }
            }));

            if (token === this.itemImageLoadToken) {
                this.itemImageUrls = urls;
            }
        },
    },
};
