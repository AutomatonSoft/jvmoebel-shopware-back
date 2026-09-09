/** Canvas preview for CMS element `jv-faq`. */
import template from './sw-cms-el-jv-faq.html.twig';
import './sw-cms-el-jv-faq.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

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

        items() {
            return this.collection(this.element?.config?.items?.value)
                .map((item, index) => ({
                    id: this.text(item?.id),
                    position: Number.isFinite(item?.position) ? item.position : index,
                    question: this.text(item?.question),
                    answer: this.text(item?.answer),
                    originalIndex: index,
                }))
                .filter((item) => item.question && item.answer)
                .sort((first, second) => {
                    if (first.position !== second.position) {
                        return first.position - second.position;
                    }

                    return first.originalIndex - second.originalIndex;
                });
        },
    },

    created() {
        this.initElementConfig('jv-faq');
    },

    methods: {
        collection(value) {
            if (Array.isArray(value)) {
                return value;
            }
            if (value && typeof value === 'object') {
                return Object.values(value);
            }

            return [];
        },

        text(value) {
            if (typeof value !== 'string') {
                return '';
            }

            return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        },
    },
};
