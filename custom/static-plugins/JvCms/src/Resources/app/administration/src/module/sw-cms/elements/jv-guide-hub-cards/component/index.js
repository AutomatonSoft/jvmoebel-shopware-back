import template from './sw-cms-el-jv-guide-hub-cards.html.twig';
import './sw-cms-el-jv-guide-hub-cards.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-guide-hub-cards');
    },
};
