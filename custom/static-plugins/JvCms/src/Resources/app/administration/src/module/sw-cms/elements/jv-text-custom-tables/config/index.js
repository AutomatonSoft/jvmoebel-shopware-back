/** Administration config for CMS element `jv-text-custom-tables`. */
import template from './sw-cms-el-config-jv-text-custom-tables.html.twig';
import './sw-cms-el-config-jv-text-custom-tables.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        sections() {
            return this.ensureSections();
        },
    },

    created() {
        this.initElementConfig('jv-text-custom-tables');
        this.normalizeConfig();
    },

    methods: {
        onUpdate() {
            this.syncPositions();
            this.ensureUniqueSectionIds();
            this.$emit('element-update', this.element);
        },

        normalizeConfig() {
            this.ensureStringConfig('topText');
            this.ensureStringConfig('bottomText');

            const sections = this.ensureSections();
            sections.forEach((section, index) => this.ensureSectionShape(section, index));
            sections.sort((first, second) => first.position - second.position);

            this.syncPositions();
            this.ensureUniqueSectionIds();
        },

        ensureStringConfig(field) {
            if (!this.element.config[field] || typeof this.element.config[field] !== 'object') {
                this.element.config[field] = { source: 'static', value: '' };
            }
            if (typeof this.element.config[field].value !== 'string') {
                this.element.config[field].value = '';
            }
        },

        ensureSections() {
            if (!this.element.config.sections || typeof this.element.config.sections !== 'object') {
                this.element.config.sections = { source: 'static', value: [] };
            }

            const value = this.element.config.sections.value;
            if (!Array.isArray(value)) {
                this.element.config.sections.value = this.sectionValues(value);
            }

            this.element.config.sections.value.forEach((section, index) => {
                if (!section || typeof section !== 'object' || Array.isArray(section)) {
                    this.element.config.sections.value[index] = this.emptySection(index);
                }
            });

            return this.element.config.sections.value;
        },

        sectionValues(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return [];
            }

            return Object.entries(value).map(([key, section]) => {
                if (!section || typeof section !== 'object' || Array.isArray(section)) {
                    return section;
                }

                if (typeof section.id === 'string' && section.id.trim()) {
                    return section;
                }

                return { ...section, id: key };
            });
        },

        ensureSectionShape(section, index) {
            if (typeof section.id !== 'string') {
                section.id = '';
            }
            if (typeof section.title !== 'string') {
                section.title = '';
            }
            if (typeof section.text !== 'string') {
                section.text = '';
            }
            if (typeof section.position !== 'number' || !Number.isFinite(section.position)) {
                section.position = index;
            }

            this.ensureRows(section);
        },

        ensureRows(section) {
            if (!Array.isArray(section.rows)) {
                section.rows = this.objectValues(section.rows);
            }

            section.rows.forEach((row, index) => {
                if (!row || typeof row !== 'object' || Array.isArray(row)) {
                    section.rows[index] = this.emptyRow(index);
                    return;
                }

                if (typeof row.left !== 'string') {
                    row.left = '';
                }
                if (typeof row.right !== 'string') {
                    row.right = '';
                }
                if (typeof row.position !== 'number' || !Number.isFinite(row.position)) {
                    row.position = index;
                }
            });
        },

        objectValues(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return [];
            }

            return Object.values(value);
        },

        ensureUniqueSectionIds() {
            const seenIds = new Set();
            this.sections.forEach((section) => {
                let id = typeof section.id === 'string' ? section.id.trim() : '';
                if (!id || seenIds.has(id)) {
                    id = Utils.createId();
                }

                section.id = id;
                seenIds.add(id);
            });
        },

        emptySection(position) {
            return {
                id: Utils.createId(),
                position,
                title: '',
                rows: [],
                text: '',
            };
        },

        emptyRow(position) {
            return {
                position,
                left: '',
                right: '',
            };
        },

        syncPositions() {
            this.sections.forEach((section, sectionIndex) => {
                section.position = sectionIndex;
                this.ensureRows(section);
                section.rows.forEach((row, rowIndex) => {
                    row.position = rowIndex;
                });
            });
        },

        addSection() {
            this.sections.push(this.emptySection(this.sections.length));
            this.onUpdate();
        },

        removeSection(index) {
            this.sections.splice(index, 1);
            this.onUpdate();
        },

        moveSection(index, offset) {
            this.moveEntry(this.sections, index, offset);
        },

        addRow(section) {
            this.ensureRows(section);
            section.rows.push(this.emptyRow(section.rows.length));
            this.onUpdate();
        },

        removeRow(section, index) {
            section.rows.splice(index, 1);
            this.onUpdate();
        },

        moveRow(section, index, offset) {
            this.moveEntry(section.rows, index, offset);
        },

        moveEntry(collection, index, offset) {
            const targetIndex = index + offset;
            if (targetIndex < 0 || targetIndex >= collection.length) {
                return;
            }

            const [entry] = collection.splice(index, 1);
            collection.splice(targetIndex, 0, entry);
            this.onUpdate();
        },
    },
};
