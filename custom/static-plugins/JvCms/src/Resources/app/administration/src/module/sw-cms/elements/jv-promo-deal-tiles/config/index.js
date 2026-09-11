import template from './sw-cms-el-config-jv-promo-deal-tiles.html.twig';
import './sw-cms-el-config-jv-promo-deal-tiles.scss';

const { Mixin } = Shopware;

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
        tiles() {
            return this.ensureTiles();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        sizeOptions() {
            return [
                { value: 'small', label: this.$t('cms.elements.jv-promo-deal-tiles.config.tiles.link.size.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-promo-deal-tiles.config.tiles.link.size.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-promo-deal-tiles.config.tiles.link.size.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-promo-deal-tiles');
        this.ensureTiles();
    },

    methods: {
        onUpdate() {
            this.syncTilePositions();
            this.$emit('element-update', this.element);
        },

        syncTilePositions() {
            this.tiles.forEach((tile, index) => {
                tile.position = index;
            });
        },

        ensureTiles() {
            if (!Array.isArray(this.element.config.tiles.value)) {
                this.element.config.tiles.value = [];
            }

            this.element.config.tiles.value.forEach((tile, index) => {
                this.ensureTileShape(tile, index);
            });

            return this.element.config.tiles.value;
        },

        ensureTileShape(tile, index) {
            if (typeof tile.id !== 'string') {
                tile.id = '';
            }
            if (typeof tile.label !== 'string') {
                tile.label = '';
            }
            if (typeof tile.description !== 'string') {
                tile.description = '';
            }
            if (typeof tile.discountLabel !== 'string') {
                tile.discountLabel = '';
            }
            if (typeof tile.endsAt !== 'string') {
                tile.endsAt = '';
            }
            if (typeof tile.position !== 'number' || !Number.isFinite(tile.position)) {
                tile.position = index;
            }
            if (!tile.link || typeof tile.link !== 'object') {
                tile.link = {
                    label: '',
                    url: '',
                    size: 'medium',
                };
            }
            if (typeof tile.link.label !== 'string') {
                tile.link.label = '';
            }
            if (typeof tile.link.url !== 'string') {
                tile.link.url = '';
            }
            if (typeof tile.link.size !== 'string' || !['small', 'medium', 'large'].includes(tile.link.size)) {
                tile.link.size = 'medium';
            }
        },

        addTile() {
            this.tiles.push({
                id: '',
                label: '',
                description: '',
                discountLabel: '',
                endsAt: '',
                position: this.tiles.length,
                imageMedia: null,
                link: {
                    label: '',
                    url: '',
                    size: 'medium',
                },
            });
            this.onUpdate();
        },

        removeTile(index) {
            this.tiles.splice(index, 1);
            this.onUpdate();
        },

        tileUploadTag(index) {
            return `cms-element-jv-promo-deal-tiles-tile-${this.element.id}-${index}`;
        },

        tilePreviewSource(tile) {
            if (tile?.image?.id) {
                return tile.image;
            }

            return tile.imageMedia;
        },

        async onTileUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const tile = this.tiles[index];
            if (!tile) {
                return;
            }

            tile.imageMedia = mediaEntity.id;
            tile.image = mediaEntity;
            this.onUpdate();
        },

        onTileRemove(index) {
            const tile = this.tiles[index];
            if (!tile) {
                return;
            }

            tile.imageMedia = null;
            tile.image = null;
            this.onUpdate();
        },

        onOpenTileMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseTileMediaModal() {
            this.mediaModalIndex = null;
        },

        onTileSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const tile = this.tiles[index];
            if (!media || !tile) {
                return;
            }

            tile.imageMedia = media.id;
            tile.image = media;
            this.onUpdate();
            this.onCloseTileMediaModal();
        },
    },
};
