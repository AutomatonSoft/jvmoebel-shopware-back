/** Canvas preview for CMS element `jv-home-editorial`. */
import template from './sw-cms-el-jv-home-editorial.html.twig';
import './sw-cms-el-jv-home-editorial.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        appearance() {
            return this.element?.config?.appearance?.value === 'plain' ? 'plain' : 'card';
        },

        statement() {
            return this.text(this.element?.config?.statement?.value);
        },

        title() {
            return this.text(this.element?.config?.title?.value);
        },

        introduction() {
            return this.collection(this.element?.config?.introduction?.value)
                .map((paragraph) => this.text(paragraph))
                .filter(Boolean);
        },

        sections() {
            return this.collection(this.element?.config?.sections?.value)
                .filter((section) => section && typeof section === 'object' && !Array.isArray(section))
                .map((section) => ({
                    ...section,
                    previewParagraphs: this.collection(section.paragraphs)
                        .map((paragraph) => this.text(paragraph))
                        .filter(Boolean),
                }))
                .filter((section) => section.previewParagraphs.length > 0)
                .sort((first, second) => {
                    const firstPosition = Number.isFinite(first.position) ? first.position : 0;
                    const secondPosition = Number.isFinite(second.position) ? second.position : 0;

                    return firstPosition - secondPosition;
                });
        },

        showMoreLabel() {
            return this.text(this.element?.config?.showMoreLabel?.value)
                || this.$t('cms.elements.jv-home-editorial.component.showMorePlaceholder');
        },
    },

    created() {
        this.initElementConfig('jv-home-editorial');
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
