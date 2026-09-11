import template from './sw-cms-el-jv-promo-banner.html.twig';
import './sw-cms-el-jv-promo-banner.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-promo-banner.component.emptyTitle');
        },
    },

    created() {
        this.initElementConfig('jv-promo-banner');
    },
};
