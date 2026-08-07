import template from './sw-cms-el-config-jv-button.html.twig';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        variantOptions() {
            return [
                { value: 'primary', label: this.$t('cms.elements.jv-button.config.variant.primary') },
                { value: 'secondary', label: this.$t('cms.elements.jv-button.config.variant.secondary') },
                { value: 'link', label: this.$t('cms.elements.jv-button.config.variant.link') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-button');
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },
    },
};