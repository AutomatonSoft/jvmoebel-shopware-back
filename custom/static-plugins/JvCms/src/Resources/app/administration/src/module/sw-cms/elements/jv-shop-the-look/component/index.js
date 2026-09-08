/** Canvas preview for CMS element `jv-shop-the-look`. */
import template from './sw-cms-el-jv-shop-the-look.html.twig';
import './sw-cms-el-jv-shop-the-look.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        title() {
            return (this.element?.config?.title?.value || '').trim();
        },

        eyebrow() {
            return (this.element?.config?.eyebrow?.value || '').trim();
        },

        items() {
            const items = this.element?.config?.items?.value;

            return Array.isArray(items) ? items : [];
        },

        imageUrl() {
            return this.element?.data?.image?.url || null;
        },
    },

    created() {
        this.initElementConfig('jv-shop-the-look');
    },

    methods: {
        itemName(item, index) {
            return item.name || this.$t('cms.elements.jv-shop-the-look.component.itemPlaceholder', { index: index + 1 });
        },

        hotspotStyle(item) {
            const x = Number(item?.hotspot?.x);
            const y = Number(item?.hotspot?.y);

            return {
                left: `${Number.isFinite(x) && x >= 0 && x <= 100 ? x : 50}%`,
                top: `${Number.isFinite(y) && y >= 0 && y <= 100 ? y : 50}%`,
            };
        },
    },
};
