import template from './sw-cms-el-config-jv-loyalty-promo.html.twig';
import './sw-cms-el-config-jv-loyalty-promo.scss';

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
            mediaModalOpen: false,
        };
    },

    computed: {
        benefits() {
            return this.ensureBenefits();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        imagePreviewSource() {
            return this.element.config.image?.value || this.element.config.imageMedia.value;
        },

        sizeOptions() {
            return [
                { value: 'small', label: this.$t('cms.elements.jv-loyalty-promo.config.link.size.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-loyalty-promo.config.link.size.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-loyalty-promo.config.link.size.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-loyalty-promo');
        this.ensureBenefits();
        this.ensureLinkShape();
    },

    methods: {
        onUpdate() {
            this.syncBenefitPositions();
            this.$emit('element-update', this.element);
        },

        ensureBenefits() {
            if (!Array.isArray(this.element.config.benefits.value)) {
                this.element.config.benefits.value = [];
            }

            return this.element.config.benefits.value;
        },

        ensureLinkShape() {
            if (!this.element.config.link.value || typeof this.element.config.link.value !== 'object') {
                this.element.config.link.value = { label: '', url: '', size: 'medium' };
            }

            const link = this.element.config.link.value;
            if (typeof link.label !== 'string') {
                link.label = '';
            }
            if (typeof link.url !== 'string') {
                link.url = '';
            }
            if (typeof link.size !== 'string' || !['small', 'medium', 'large'].includes(link.size)) {
                link.size = 'medium';
            }
        },

        syncBenefitPositions() {
            this.benefits.forEach((benefit, index) => {
                benefit.position = index;
            });
        },

        addBenefit() {
            this.benefits.push({
                id: '',
                text: '',
                position: this.benefits.length,
            });
            this.onUpdate();
        },

        removeBenefit(index) {
            this.benefits.splice(index, 1);
            this.onUpdate();
        },

        uploadTag() {
            return `cms-element-jv-loyalty-promo-${this.element.id}`;
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            this.element.config.imageMedia.value = mediaEntity.id;
            this.element.config.image.value = mediaEntity;
            this.onUpdate();
        },

        onImageRemove() {
            this.element.config.imageMedia.value = null;
            this.element.config.image.value = null;
            this.onUpdate();
        },

        onOpenMediaModal() {
            this.mediaModalOpen = true;
        },

        onCloseMediaModal() {
            this.mediaModalOpen = false;
        },

        onMediaSelectionChanges(mediaEntities) {
            const media = mediaEntities[0];
            if (!media) {
                return;
            }

            this.element.config.imageMedia.value = media.id;
            this.element.config.image.value = media;
            this.onUpdate();
            this.onCloseMediaModal();
        },
    },
};
