import template from './sw-cms-el-jv-button.html.twig';
import './sw-cms-el-jv-button.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-button');
    },
};