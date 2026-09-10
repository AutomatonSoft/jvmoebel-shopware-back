/** Canvas preview for CMS element `jv-text-custom-tables`. */
import template from './sw-cms-el-jv-text-custom-tables.html.twig';
import './sw-cms-el-jv-text-custom-tables.scss';

const { Mixin } = Shopware;

export default {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        topText() {
            return this.text(this.element?.config?.topText?.value);
        },

        bottomText() {
            return this.text(this.element?.config?.bottomText?.value);
        },

        sections() {
            return this.collection(this.element?.config?.sections?.value)
                .filter((section) => section && typeof section === 'object' && !Array.isArray(section))
                .map((section, index) => ({
                    id: typeof section.id === 'string' ? section.id : `section-${index}`,
                    position: Number.isFinite(section.position) ? section.position : index,
                    title: this.text(section.title),
                    rows: this.collection(section.rows)
                        .filter((row) => row && typeof row === 'object' && !Array.isArray(row))
                        .map((row, rowIndex) => ({
                            position: Number.isFinite(row.position) ? row.position : rowIndex,
                            left: this.text(row.left),
                            right: this.text(row.right),
                        }))
                        .sort((first, second) => first.position - second.position),
                    text: this.text(section.text),
                }))
                .sort((first, second) => first.position - second.position);
        },
    },

    created() {
        this.initElementConfig('jv-text-custom-tables');
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
