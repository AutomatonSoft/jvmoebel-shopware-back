/**
 * Cart element config for CMS element `jv-cart`.
 */
import template from './sw-cms-el-config-jv-cart.html.twig';
import './sw-cms-el-config-jv-cart.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        serviceOptions() {
            return this.ensureServiceOptions();
        },

        trustItems() {
            return this.ensureTrustItems();
        },
    },

    created() {
        this.initElementConfig('jv-cart');
        this.ensureNestedDefaults();
    },

    methods: {
        onUpdate() {
            this.$emit('element-update', this.element);
        },

        ensureNestedDefaults() {
            if (!this.element.config.headerTrigger.value || typeof this.element.config.headerTrigger.value !== 'object') {
                this.element.config.headerTrigger.value = { label: '', url: '' };
            }

            if (!this.element.config.loginHint.value || typeof this.element.config.loginHint.value !== 'object') {
                this.element.config.loginHint.value = { message: '', loginLabel: '', loginUrl: '' };
            }

            if (!this.element.config.services.value || typeof this.element.config.services.value !== 'object') {
                this.element.config.services.value = {
                    title: '',
                    postalCode: { label: '', placeholder: '', submitLabel: '' },
                    options: [],
                };
            }

            if (!this.element.config.summaryLabels.value || typeof this.element.config.summaryLabels.value !== 'object') {
                this.element.config.summaryLabels.value = {
                    titleSingular: '',
                    titlePlural: '',
                    subtotalLabel: '',
                    shippingLabel: '',
                    shippingUrl: '',
                    totalLabel: '',
                    savingsLabel: '',
                    checkoutLabel: '',
                    checkoutUrl: '',
                };
            }
        },

        ensureServiceOptions() {
            if (!Array.isArray(this.element.config.services.value.options)) {
                this.element.config.services.value.options = [];
            }

            return this.element.config.services.value.options;
        },

        ensureTrustItems() {
            if (!Array.isArray(this.element.config.trust.value)) {
                this.element.config.trust.value = [];
            }

            return this.element.config.trust.value;
        },

        addServiceOption() {
            this.serviceOptions.push({
                id: '',
                label: '',
                price: 0,
            });
            this.onUpdate();
        },

        removeServiceOption(index) {
            this.serviceOptions.splice(index, 1);
            this.onUpdate();
        },

        addTrustItem() {
            this.trustItems.push({ label: '' });
            this.onUpdate();
        },

        removeTrustItem(index) {
            this.trustItems.splice(index, 1);
            this.onUpdate();
        },
    },
};
