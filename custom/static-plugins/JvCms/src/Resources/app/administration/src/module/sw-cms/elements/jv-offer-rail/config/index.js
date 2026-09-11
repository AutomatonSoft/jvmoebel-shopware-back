/**
 * Config panel for CMS element `jv-offer-rail`.
 */
import template from './sw-cms-el-config-jv-offer-rail.html.twig';
import './sw-cms-el-config-jv-offer-rail.scss';

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
        offers() {
            return this.ensureOffers();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    created() {
        this.initElementConfig('jv-offer-rail');
        this.ensureOffers();
    },

    methods: {
        onUpdate() {
            this.syncOfferPositions();
            this.$emit('element-update', this.element);
        },

        syncOfferPositions() {
            this.offers.forEach((offer, index) => {
                offer.position = index;
            });
        },

        ensureOffers() {
            if (!Array.isArray(this.element.config.offers.value)) {
                this.element.config.offers.value = [];
            }

            return this.element.config.offers.value;
        },

        addOffer() {
            this.offers.push({
                id: '',
                title: '',
                subtitle: '',
                ctaLabel: '',
                url: '',
                endsAt: '',
                legalText: '',
                position: this.offers.length,
                imageMedia: null,
            });
            this.onUpdate();
        },

        removeOffer(index) {
            this.offers.splice(index, 1);
            this.onUpdate();
        },

        offerUploadTag(index) {
            return `cms-element-jv-offer-rail-offer-${this.element.id}-${index}`;
        },

        offerPreviewSource(offer) {
            if (offer?.image?.id) {
                return offer.image;
            }

            return offer.imageMedia;
        },

        async onOfferUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const offer = this.offers[index];
            if (!offer) {
                return;
            }

            offer.imageMedia = mediaEntity.id;
            offer.image = mediaEntity;
            this.onUpdate();
        },

        onOfferRemove(index) {
            const offer = this.offers[index];
            if (!offer) {
                return;
            }

            offer.imageMedia = null;
            offer.image = null;
            this.onUpdate();
        },

        onOpenOfferMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseOfferMediaModal() {
            this.mediaModalIndex = null;
        },

        onOfferSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const offer = this.offers[index];
            if (!media || !offer) {
                return;
            }

            offer.imageMedia = media.id;
            offer.image = media;
            this.onUpdate();
            this.onCloseOfferMediaModal();
        },
    },
};
