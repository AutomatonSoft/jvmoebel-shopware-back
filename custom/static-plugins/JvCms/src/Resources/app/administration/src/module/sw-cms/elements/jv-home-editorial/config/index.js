/** Administration config for CMS element `jv-home-editorial`. */
import template from './sw-cms-el-config-jv-home-editorial.html.twig';
import './sw-cms-el-config-jv-home-editorial.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        appearanceOptions() {
            return [
                { value: 'card', label: this.$t('cms.elements.jv-home-editorial.config.appearance.card') },
                { value: 'plain', label: this.$t('cms.elements.jv-home-editorial.config.appearance.plain') },
            ];
        },

        introduction() {
            return this.ensureIntroduction();
        },

        sections() {
            return this.ensureSections();
        },
    },

    created() {
        this.initElementConfig('jv-home-editorial');
        this.normalizeConfig();
    },

    methods: {
        onUpdate() {
            this.syncSectionPositions();
            this.$emit('element-update', this.element);
        },

        normalizeConfig() {
            if (!['card', 'plain'].includes(this.element.config.appearance.value)) {
                this.element.config.appearance.value = 'card';
            }

            const introduction = this.ensureIntroduction();
            introduction.forEach((paragraph, index) => {
                if (typeof paragraph !== 'string') {
                    introduction[index] = '';
                }
            });

            const sections = this.ensureSections();
            sections.forEach((section, index) => {
                this.ensureSectionShape(section, index);
            });
            sections.sort((first, second) => first.position - second.position);

            this.syncSectionPositions();
        },

        ensureIntroduction() {
            const value = this.element.config.introduction.value;
            if (!Array.isArray(value)) {
                this.element.config.introduction.value = this.objectValues(value);
            }

            return this.element.config.introduction.value;
        },

        ensureSections() {
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
            if (typeof section.position !== 'number' || !Number.isFinite(section.position)) {
                section.position = index;
            }
            if (!Array.isArray(section.paragraphs)) {
                section.paragraphs = this.objectValues(section.paragraphs);
            }
            section.paragraphs.forEach((paragraph, paragraphIndex) => {
                if (typeof paragraph !== 'string') {
                    section.paragraphs[paragraphIndex] = '';
                }
            });
        },

        objectValues(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return [];
            }

            return Object.values(value);
        },

        emptySection(position) {
            return {
                id: Utils.createId(),
                title: '',
                position,
                paragraphs: [],
            };
        },

        sectionHasValidParagraph(section) {
            return Array.isArray(section?.paragraphs)
                && section.paragraphs.some((paragraph) => typeof paragraph === 'string' && paragraph.trim());
        },

        syncSectionPositions() {
            this.sections.forEach((section, index) => {
                section.position = index;
            });
        },

        addIntroductionParagraph() {
            this.introduction.push('');
            this.onUpdate();
        },

        removeIntroductionParagraph(index) {
            this.introduction.splice(index, 1);
            this.onUpdate();
        },

        moveIntroductionParagraph(index, offset) {
            this.moveEntry(this.introduction, index, offset);
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

        addSectionParagraph(section) {
            section.paragraphs.push('');
            this.onUpdate();
        },

        removeSectionParagraph(section, index) {
            section.paragraphs.splice(index, 1);
            this.onUpdate();
        },

        moveSectionParagraph(section, index, offset) {
            this.moveEntry(section.paragraphs, index, offset);
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
