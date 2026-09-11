import template from './sw-cms-el-jv-offer-rail.html.twig';
import './sw-cms-el-jv-offer-rail.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        previewTitle() {
            return this.element?.config?.title?.value
                || this.$t('cms.elements.jv-offer-rail.component.emptyTitle');
        },

        offers() {
            const offers = this.element?.config?.offers?.value;

            return Array.isArray(offers) ? offers : [];
        },
    },

    created() {
        this.initElementConfig('jv-offer-rail');
    },
};
