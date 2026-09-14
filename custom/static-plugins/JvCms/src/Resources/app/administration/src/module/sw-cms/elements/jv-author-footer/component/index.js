import template from './sw-cms-el-jv-author-footer.html.twig';
import './sw-cms-el-jv-author-footer.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-author-footer');
    },
};
