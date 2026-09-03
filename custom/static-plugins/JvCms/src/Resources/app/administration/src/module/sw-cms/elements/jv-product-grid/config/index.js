/**
 * Product grid config for CMS element `jv-product-grid`.
 */
import template from './sw-cms-el-config-jv-product-grid.html.twig';
import './sw-cms-el-config-jv-product-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        products() {
            return this.ensureProducts();
        },
    },

    created() {
        this.initElementConfig('jv-product-grid');
        this.ensureProducts();
        this.ensureViewAll();
    },

    methods: {
        onUpdate() {
            this.syncProductPositions();
            this.$emit('element-update', this.element);
        },

        syncProductPositions() {
            this.products.forEach((product, index) => {
                product.position = index;
            });
        },

        ensureProducts() {
            if (!Array.isArray(this.element.config.products.value)) {
                this.element.config.products.value = [];
            }

            return this.element.config.products.value;
        },

        ensureViewAll() {
            if (!this.element.config.viewAll.value || typeof this.element.config.viewAll.value !== 'object') {
                this.element.config.viewAll.value = {
                    label: '',
                    url: '',
                };
            }
        },

        addProduct() {
            this.products.push({
                productId: null,
                badge: '',
                position: this.products.length,
            });
            this.onUpdate();
        },

        removeProduct(index) {
            this.products.splice(index, 1);
            this.onUpdate();
        },
    },
};
