/**
 * Trend look grid config for CMS element `jv-trend-look-grid`.
 */
import template from './sw-cms-el-config-jv-trend-look-grid.html.twig';
import './sw-cms-el-config-jv-trend-look-grid.scss';

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
        cards() {
            return this.ensureCards();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    created() {
        this.initElementConfig('jv-trend-look-grid');
        this.ensureCards();
    },

    methods: {
        onUpdate() {
            this.syncCardPositions();
            this.$emit('element-update', this.element);
        },

        syncCardPositions() {
            this.cards.forEach((card, index) => {
                card.position = index;
            });
        },

        ensureCards() {
            if (!Array.isArray(this.element.config.cards.value)) {
                this.element.config.cards.value = [];
            }

            this.element.config.cards.value.forEach((card, index) => {
                this.ensureCardShape(card, index);
            });

            return this.element.config.cards.value;
        },

        ensureCardShape(card, index) {
            if (typeof card.id !== 'string') {
                card.id = '';
            }
            if (typeof card.title !== 'string') {
                card.title = '';
            }
            if (typeof card.description !== 'string') {
                card.description = '';
            }
            if (typeof card.url !== 'string') {
                card.url = '';
            }
            if (typeof card.position !== 'number' || !Number.isFinite(card.position)) {
                card.position = index;
            }
        },

        addCard() {
            this.cards.push({
                id: '',
                title: '',
                description: '',
                url: '',
                position: this.cards.length,
                imageMedia: null,
            });
            this.onUpdate();
        },

        removeCard(index) {
            this.cards.splice(index, 1);
            this.onUpdate();
        },

        cardUploadTag(index) {
            return `cms-element-jv-trend-look-grid-card-${this.element.id}-${index}`;
        },

        cardPreviewSource(card) {
            if (card?.image?.id) {
                return card.image;
            }

            return card.imageMedia;
        },

        async onCardUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const card = this.cards[index];
            if (!card) {
                return;
            }

            card.imageMedia = mediaEntity.id;
            card.image = mediaEntity;
            this.onUpdate();
        },

        onCardRemove(index) {
            const card = this.cards[index];
            if (!card) {
                return;
            }

            card.imageMedia = null;
            card.image = null;
            this.onUpdate();
        },

        onOpenCardMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseCardMediaModal() {
            this.mediaModalIndex = null;
        },

        onCardSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const card = this.cards[index];
            if (!media || !card) {
                return;
            }

            card.imageMedia = media.id;
            card.image = media;
            this.onUpdate();
            this.onCloseCardMediaModal();
        },
    },
};
