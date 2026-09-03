import template from './sw-cms-el-jv-newsletter.html.twig';
import './sw-cms-el-jv-newsletter.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-newsletter.component.emptyTitle');
        },
    },

    created() {
        this.initElementConfig('jv-newsletter');
    },
};
