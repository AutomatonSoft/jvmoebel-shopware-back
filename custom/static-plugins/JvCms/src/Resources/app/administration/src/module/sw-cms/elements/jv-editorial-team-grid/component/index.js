import template from './sw-cms-el-jv-editorial-team-grid.html.twig';
import './sw-cms-el-jv-editorial-team-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    created() {
        this.initElementConfig('jv-editorial-team-grid');
    },
};
