import template from './sw-cms-el-jv-countdown-promo.html.twig';
import './sw-cms-el-jv-countdown-promo.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-countdown-promo.component.emptyTitle');
        },
    },

    created() {
        this.initElementConfig('jv-countdown-promo');
    },
};
