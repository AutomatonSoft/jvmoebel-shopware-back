import template from './sw-cms-el-jv-hero.html.twig';
import './sw-cms-el-jv-hero.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value || this.$t('cms.elements.jv-hero.component.emptyTitle');
        },

        previewEyebrow() {
            return this.element?.config?.eyebrow?.value || '';
        },

        hasImage() {
            return Boolean(this.element?.config?.imageMedia?.value || this.element?.data?.imageMedia);
        },
    },

    created() {
        this.initElementConfig('jv-hero');
    },
};
