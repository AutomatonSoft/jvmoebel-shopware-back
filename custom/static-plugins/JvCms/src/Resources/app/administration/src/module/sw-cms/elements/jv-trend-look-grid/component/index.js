import template from './sw-cms-el-jv-trend-look-grid.html.twig';
import './sw-cms-el-jv-trend-look-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-trend-look-grid.component.emptyTitle');
        },

        cardCount() {
            return Array.isArray(this.element?.config?.cards?.value)
                ? this.element.config.cards.value.length
                : 0;
        },
    },

    created() {
        this.initElementConfig('jv-trend-look-grid');
    },
};
