import template from './sw-cms-el-jv-expert-quote.html.twig';
import './sw-cms-el-jv-expert-quote.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-expert-quote');
    },
};
