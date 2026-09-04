import template from './sw-cms-el-jv-hero.html.twig';
import './sw-cms-el-jv-hero.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewSlide() {
            const slides = this.element?.config?.slides?.value;
            if (Array.isArray(slides) && slides.length > 0) {
                return slides[0];
            }

            if (this.element?.config?.title?.value) {
                return {
                    title: this.element.config.title.value,
                    eyebrow: this.element.config.eyebrow?.value || '',
                };
            }

            return null;
        },

        previewTitle() {
            return this.previewSlide?.title || this.$t('cms.elements.jv-hero.component.emptyTitle');
        },

        previewEyebrow() {
            return this.previewSlide?.eyebrow || '';
        },

        hasImage() {
            const slide = this.previewSlide;
            if (slide?.imageMedia || slide?.image) {
                return true;
            }

            return Boolean(this.element?.config?.imageMedia?.value || this.element?.data?.imageMedia);
        },
    },

    created() {
        this.initElementConfig('jv-hero');
    },
};
