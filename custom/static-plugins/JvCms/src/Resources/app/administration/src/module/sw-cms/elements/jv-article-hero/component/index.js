import template from './sw-cms-el-jv-article-hero.html.twig';
import './sw-cms-el-jv-article-hero.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        eyebrow() {
            return this.text(this.element?.config?.eyebrow?.value);
        },

        title() {
            return this.text(this.element?.config?.title?.value);
        },

        description() {
            return this.text(this.element?.config?.description?.value);
        },
    },

    created() {
        this.initElementConfig('jv-article-hero');
    },

    methods: {
        text(value) {
            return typeof value === 'string' ? value.trim() : '';
        },
    },
};
