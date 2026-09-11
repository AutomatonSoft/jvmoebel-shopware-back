import template from './sw-cms-el-jv-expert-profile.html.twig';
import './sw-cms-el-jv-expert-profile.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-expert-profile');
    },
};
