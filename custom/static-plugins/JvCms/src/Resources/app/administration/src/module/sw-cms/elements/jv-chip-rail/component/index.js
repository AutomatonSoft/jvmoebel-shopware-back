import template from './sw-cms-el-jv-chip-rail.html.twig';
import './sw-cms-el-jv-chip-rail.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-chip-rail.component.emptyTitle');
        },

        chipCount() {
            return Array.isArray(this.element?.config?.chips?.value)
                ? this.element.config.chips.value.length
                : 0;
        },
    },

    created() {
        this.initElementConfig('jv-chip-rail');
    },
};
