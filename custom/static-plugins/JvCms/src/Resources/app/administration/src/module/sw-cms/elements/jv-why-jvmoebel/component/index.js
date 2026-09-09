/**
 * Canvas preview for CMS element `jv-why-jvmoebel`.
 */
import template from './sw-cms-el-jv-why-jvmoebel.html.twig';
import './sw-cms-el-jv-why-jvmoebel.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        mark() {
            return (this.element?.config?.mark?.value || '').trim();
        },

        tagline() {
            return (this.element?.config?.tagline?.value || '').trim();
        },

        title() {
            return (this.element?.config?.title?.value || '').trim();
        },

        eyebrow() {
            return (this.element?.config?.eyebrow?.value || '').trim();
        },

        description() {
            return (this.element?.config?.description?.value || '').trim();
        },

        benefits() {
            const benefits = this.element?.config?.benefits?.value;

            return Array.isArray(benefits) ? benefits.filter((benefit) => {
                if (!benefit || typeof benefit !== 'object') {
                    return false;
                }

                const title = typeof benefit.title === 'string' ? benefit.title.trim() : '';

                return title !== '' || benefit.icon || benefit.url;
            }) : [];
        },

        viewAllLabel() {
            return (this.element?.config?.viewAll?.value?.label || '').trim();
        },
    },

    created() {
        this.initElementConfig('jv-why-jvmoebel');
    },
};
