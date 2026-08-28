/**
 * Canvas preview for CMS element `jv-room-grid`.
 */
import template from './sw-cms-el-jv-room-grid.html.twig';
import './sw-cms-el-jv-room-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        title() {
            return (this.element?.config?.title?.value || '').trim();
        },

        eyebrow() {
            return (this.element?.config?.eyebrow?.value || '').trim();
        },

        description() {
            return (this.element?.config?.description?.value || '').trim();
        },

        rooms() {
            const rooms = this.element?.config?.rooms?.value;

            return Array.isArray(rooms) ? rooms : [];
        },
    },

    created() {
        this.initElementConfig('jv-room-grid');
    },
};
