import template from './sw-cms-el-jv-promo-deal-tiles.html.twig';
import './sw-cms-el-jv-promo-deal-tiles.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-promo-deal-tiles.component.emptyTitle');
        },

        tileCount() {
            return Array.isArray(this.element?.config?.tiles?.value)
                ? this.element.config.tiles.value.length
                : 0;
        },
    },

    created() {
        this.initElementConfig('jv-promo-deal-tiles');
    },
};
