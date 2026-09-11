import template from './sw-cms-el-jv-related-look-cards.html.twig';
import './sw-cms-el-jv-related-look-cards.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-related-look-cards.component.emptyTitle');
        },

        cardCount() {
            return Array.isArray(this.element?.config?.cards?.value)
                ? this.element.config.cards.value.length
                : 0;
        },
    },

    created() {
        this.initElementConfig('jv-related-look-cards');
    },
};
