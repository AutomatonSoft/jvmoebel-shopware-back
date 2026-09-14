import template from './sw-cms-el-config-jv-subcategory-links.html.twig';
import './sw-cms-el-config-jv-subcategory-links.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        links() {
            return this.ensureLinks();
        },
    },

    created() {
        this.initElementConfig('jv-subcategory-links');
        this.ensureLinks();
    },

    methods: {
        onUpdate() {
            this.syncLinkPositions();
            this.$emit('element-update', this.element);
        },

        ensureLinks() {
            if (!Array.isArray(this.element.config.links.value)) {
                this.element.config.links.value = [];
            }

            return this.element.config.links.value;
        },

        syncLinkPositions() {
            this.links.forEach((link, index) => {
                link.position = index;
            });
        },

        addLink() {
            this.links.push({
                id: '',
                label: '',
                url: '',
                position: this.links.length,
            });
            this.onUpdate();
        },

        removeLink(index) {
            this.links.splice(index, 1);
            this.onUpdate();
        },
    },
};
