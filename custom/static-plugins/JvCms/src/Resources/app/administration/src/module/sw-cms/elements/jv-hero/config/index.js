/**
 * Hero carousel config for CMS element `jv-hero`.
 */
import template from './sw-cms-el-config-jv-hero.html.twig';
import './sw-cms-el-config-jv-hero.scss';

const { Mixin } = Shopware;

const EMPTY_LINK = {
    label: '',
    url: '',
    size: 'medium',
};

const EMPTY_PROMOTION = {
    label: '',
    value: '',
};

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
        slides() {
            return this.ensureSlides();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        layoutOptions() {
            return [
                { value: 'featured', label: this.$t('cms.elements.jv-hero.config.slides.layout.featured') },
                { value: 'caption', label: this.$t('cms.elements.jv-hero.config.slides.layout.caption') },
            ];
        },

        sizeOptions() {
            return [
                { value: 'small', label: this.$t('cms.elements.jv-hero.config.link.size.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-hero.config.link.size.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-hero.config.link.size.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-hero');
        this.migrateLegacyConfig();
        this.normalizeSlides();
    },

    methods: {
        onUpdate() {
            this.syncSlidePositions();
            this.$emit('element-update', this.element);
        },

        syncSlidePositions() {
            this.slides.forEach((slide, index) => {
                slide.position = index;
            });
        },

        ensureSlides() {
            if (!Array.isArray(this.element.config.slides?.value)) {
                this.element.config.slides.value = [];
            }

            return this.element.config.slides.value;
        },

        normalizeSlides() {
            this.ensureSlides().forEach((slide) => {
                this.ensureSlideShape(slide);
            });
        },

        ensureSlideShape(slide) {
            if (typeof slide.id !== 'string') {
                slide.id = '';
            }
            if (typeof slide.position !== 'number') {
                slide.position = 0;
            }
            if (typeof slide.layout !== 'string' || !['featured', 'caption'].includes(slide.layout)) {
                slide.layout = 'featured';
            }
            if (typeof slide.title !== 'string') {
                slide.title = '';
            }
            if (typeof slide.url !== 'string') {
                slide.url = '';
            }
            if (typeof slide.eyebrow !== 'string') {
                slide.eyebrow = '';
            }
            if (typeof slide.description !== 'string') {
                slide.description = '';
            }
            if (!Object.prototype.hasOwnProperty.call(slide, 'imageMedia')) {
                slide.imageMedia = null;
            }

            if (!slide.promotion || typeof slide.promotion !== 'object' || Array.isArray(slide.promotion)) {
                slide.promotion = { ...EMPTY_PROMOTION };
            } else {
                if (typeof slide.promotion.label !== 'string') {
                    slide.promotion.label = '';
                }
                if (typeof slide.promotion.value !== 'string') {
                    slide.promotion.value = '';
                }
            }

            this.ensureLinkObject(slide, 'primaryLink');
            this.ensureLinkObject(slide, 'secondaryLink');
        },

        ensureLinkObject(slide, field) {
            if (!slide[field] || typeof slide[field] !== 'object' || Array.isArray(slide[field])) {
                slide[field] = { ...EMPTY_LINK };
                return;
            }

            if (typeof slide[field].label !== 'string') {
                slide[field].label = '';
            }
            if (typeof slide[field].url !== 'string') {
                slide[field].url = '';
            }
            if (!['small', 'medium', 'large'].includes(slide[field].size)) {
                slide[field].size = 'medium';
            }
        },

        /** Map legacy flat root fields into one slide so editors can migrate in the UI. */
        migrateLegacyConfig() {
            this.ensureSlides();

            const slides = this.element.config.slides.value;
            if (slides.length > 0) {
                return;
            }

            const legacyTitle = this.element.config.title?.value;
            const legacyImage = this.element.config.imageMedia?.value;
            const legacyEyebrow = this.element.config.eyebrow?.value;
            const legacyDescription = this.element.config.description?.value;
            const legacyPrimary = this.element.config.primaryLink?.value;
            const legacySecondary = this.element.config.secondaryLink?.value;

            const hasLegacy = Boolean(
                legacyTitle
                || legacyImage
                || legacyEyebrow
                || legacyDescription
                || legacyPrimary?.label
                || legacyPrimary?.url
                || legacySecondary?.label
                || legacySecondary?.url,
            );

            if (!hasLegacy) {
                return;
            }

            this.element.config.slides.value = [{
                id: '',
                position: 0,
                layout: 'featured',
                title: legacyTitle || '',
                url: '',
                eyebrow: legacyEyebrow || '',
                description: legacyDescription || '',
                imageMedia: legacyImage || null,
                promotion: { ...EMPTY_PROMOTION },
                primaryLink: legacyPrimary ? { ...EMPTY_LINK, ...legacyPrimary } : { ...EMPTY_LINK },
                secondaryLink: legacySecondary ? { ...EMPTY_LINK, ...legacySecondary } : { ...EMPTY_LINK },
            }];
        },

        addSlide() {
            this.ensureSlides().push({
                id: '',
                position: this.slides.length,
                layout: 'featured',
                title: '',
                url: '',
                eyebrow: '',
                description: '',
                imageMedia: null,
                promotion: { ...EMPTY_PROMOTION },
                primaryLink: { ...EMPTY_LINK },
                secondaryLink: { ...EMPTY_LINK },
            });
            this.onUpdate();
        },

        removeSlide(index) {
            this.ensureSlides().splice(index, 1);
            this.onUpdate();
        },

        slideUploadTag(index) {
            return `cms-element-jv-hero-slide-${this.element.id}-${index}`;
        },

        slidePreviewSource(slide) {
            if (slide?.image?.id) {
                return slide.image;
            }

            return slide.imageMedia;
        },

        async onSlideUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const slide = this.slides[index];
            if (!slide) {
                return;
            }

            slide.imageMedia = mediaEntity.id;
            slide.image = mediaEntity;
            this.onUpdate();
        },

        onSlideRemove(index) {
            const slide = this.slides[index];
            if (!slide) {
                return;
            }

            slide.imageMedia = null;
            slide.image = null;
            this.onUpdate();
        },

        onOpenSlideMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseSlideMediaModal() {
            this.mediaModalIndex = null;
        },

        onSlideSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const slide = this.slides[index];
            if (!media || !slide) {
                return;
            }

            slide.imageMedia = media.id;
            slide.image = media;
            this.onUpdate();
            this.onCloseSlideMediaModal();
        },
    },
};
