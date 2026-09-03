import template from './sw-cms-el-config-jv-newsletter.html.twig';
import './sw-cms-el-config-jv-newsletter.scss';

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
                { value: 'small', label: this.$t('cms.elements.jv-newsletter.config.buttonSize.small') },
                { value: 'medium', label: this.$t('cms.elements.jv-newsletter.config.buttonSize.medium') },
                { value: 'large', label: this.$t('cms.elements.jv-newsletter.config.buttonSize.large') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-newsletter');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },
    },
};
