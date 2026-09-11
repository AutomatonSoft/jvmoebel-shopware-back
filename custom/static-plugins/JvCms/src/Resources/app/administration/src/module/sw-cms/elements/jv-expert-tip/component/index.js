import template from './sw-cms-el-jv-expert-tip.html.twig';
import './sw-cms-el-jv-expert-tip.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-expert-tip');
    },
};
