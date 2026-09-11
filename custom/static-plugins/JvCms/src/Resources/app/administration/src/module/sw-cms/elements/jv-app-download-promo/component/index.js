import template from './sw-cms-el-jv-app-download-promo.html.twig';
import './sw-cms-el-jv-app-download-promo.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.element?.config?.handle?.value
                || this.element?.config?.name?.value
                || this.element?.config?.summary?.value
                || this.$t('cms.elements.jv-app-download-promo.component.emptyTitle');
        },
    },

    created() {
        this.initElementConfig('jv-app-download-promo');
    },
};
