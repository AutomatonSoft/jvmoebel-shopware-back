import template from './sw-cms-el-config-jv-countdown-promo.html.twig';
import './sw-cms-el-config-jv-countdown-promo.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        sizeOptions() {
            return [
                { value: 'small', label: this.$t('cms.elements.jv-countdown-promo.config.link.size.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-countdown-promo.config.link.size.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-countdown-promo.config.link.size.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-countdown-promo');
        this.ensureLinkShape();
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        ensureLinkShape() {
            if (!this.element.config.link.value || typeof this.element.config.link.value !== 'object') {
                this.element.config.link.value = {
                    label: '',
                    url: '',
                    size: 'medium',
                };
            }

            const link = this.element.config.link.value;
            if (typeof link.label !== 'string') {
                link.label = '';
            }
            if (typeof link.url !== 'string') {
                link.url = '';
            }
            if (typeof link.size !== 'string' || !['small', 'medium', 'large'].includes(link.size)) {
                link.size = 'medium';
            }
        },
    },
};
