/**
 * Why JVMöbel config for CMS element `jv-why-jvmoebel`.
 */
import template from './sw-cms-el-config-jv-why-jvmoebel.html.twig';
import './sw-cms-el-config-jv-why-jvmoebel.scss';

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
        benefits() {
            return this.ensureBenefits();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        iconModeOptions() {
            return [
                { value: 'preset', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.iconMode.preset') },
                { value: 'media', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.iconMode.media') },
            ];
        },

        iconOptions() {
            return [
                { value: 'advice', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.icon.advice') },
                { value: 'design', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.icon.design') },
                { value: 'payment', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.icon.payment') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-why-jvmoebel');
        this.ensureBenefits();
        this.ensureUniqueBenefitIds();
        this.ensureViewAll();
    },

    methods: {
        onUpdate() {
            this.syncBenefitPositions();
            this.ensureUniqueBenefitIds();
            this.$emit('element-update', this.element);
        },

        syncBenefitPositions() {
            this.benefits.forEach((benefit, index) => {
                benefit.position = index;
            });
        },

        ensureBenefits() {
            if (!Array.isArray(this.element.config.benefits.value)) {
                this.element.config.benefits.value = [];
            }

            this.element.config.benefits.value.forEach((benefit) => {
                if (!benefit || typeof benefit !== 'object') {
                    return;
                }

                if (benefit.iconMode !== 'preset' && benefit.iconMode !== 'media') {
                    benefit.iconMode = 'preset';
                }

                if (!benefit.icon) {
                    benefit.icon = 'design';
                }
            });

            return this.element.config.benefits.value;
        },

        ensureViewAll() {
            if (!this.element.config.viewAll.value || typeof this.element.config.viewAll.value !== 'object') {
                this.element.config.viewAll.value = {
                    label: '',
                    url: '',
                };
            }
        },

        ensureUniqueBenefitIds() {
            const seenIds = new Set();

            this.benefits.forEach((benefit, index) => {
                let id = typeof benefit.id === 'string' ? benefit.id.trim() : '';

                if (!id || seenIds.has(id)) {
                    const title = typeof benefit.title === 'string' ? benefit.title.trim() : '';
                    id = title ? `${title}-${index}` : Utils.createId();
                    if (seenIds.has(id)) {
                        id = `${id}-${index}`;
                    }
                    benefit.id = id;
                }

                seenIds.add(id);
            });
        },

        addBenefit() {
            this.benefits.push({
                id: Utils.createId(),
                position: this.benefits.length,
                iconMode: 'preset',
                icon: 'design',
                iconMedia: null,
                title: '',
                description: '',
                url: '',
            });
            this.onUpdate();
        },

        removeBenefit(index) {
            this.benefits.splice(index, 1);
            this.onUpdate();
        },

        onIconModeChange(benefit) {
            if (benefit.iconMode === 'preset') {
                benefit.iconMedia = null;
                benefit.iconEntity = null;
                if (!benefit.icon) {
                    benefit.icon = 'design';
                }
            }

            this.onUpdate();
        },

        benefitUploadTag(index) {
            return `cms-element-jv-why-jvmoebel-benefit-${this.element.id}-${index}`;
        },

        benefitIconPreviewSource(benefit) {
            if (benefit?.iconEntity?.id) {
                return benefit.iconEntity;
            }

            return benefit.iconMedia;
        },

        async onBenefitIconUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const benefit = this.benefits[index];
            if (!benefit) {
                return;
            }

            benefit.iconMedia = mediaEntity.id;
            benefit.iconEntity = mediaEntity;
            this.onUpdate();
        },

        onBenefitIconRemove(index) {
            const benefit = this.benefits[index];
            if (!benefit) {
                return;
            }

            benefit.iconMedia = null;
            benefit.iconEntity = null;
            this.onUpdate();
        },

        onOpenBenefitIconMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseBenefitIconMediaModal() {
            this.mediaModalIndex = null;
        },

        onBenefitIconSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const benefit = this.benefits[index];
            if (!media || !benefit) {
                return;
            }

            benefit.iconMedia = media.id;
            benefit.iconEntity = media;
            this.onUpdate();
            this.onCloseBenefitIconMediaModal();
        },
    },
};
