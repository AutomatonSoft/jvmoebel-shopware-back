import template from './sw-cms-el-jv-global-search.html.twig';
import './sw-cms-el-jv-global-search.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        placeholder() {
            const value = this.element?.config?.searchPlaceholder?.value;
            if (typeof value === 'string' && value.trim() !== '') {
                return value.trim();
            }

            return this.$t('cms.elements.jv-global-search.component.defaultPlaceholder');
        },
    },

    created() {
        this.initElementConfig('jv-global-search');
        if (!this.element.config?.searchPlaceholder) {
            this.element.config = this.element.config || {};
            this.element.config.searchPlaceholder = { source: 'static', value: '' };
        }
    },
};
