/** Administration config for CMS element `jv-chip-rail`. */
import template from './sw-cms-el-config-jv-chip-rail.html.twig';
import './sw-cms-el-config-jv-chip-rail.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        chips() {
            return this.ensureChips();
        },
    },

    created() {
        this.initElementConfig('jv-chip-rail');
        this.normalizeConfig();
    },

    methods: {
        onUpdate() {
            this.syncChipPositions();
            this.$emit('element-update', this.element);
        },

        normalizeConfig() {
            ['title', 'eyebrow'].forEach((field) => {
                if (typeof this.element.config[field].value !== 'string') {
                    this.element.config[field].value = '';
                }
            });

            const chips = this.ensureChips();
            chips.forEach((chip, index) => {
                this.ensureChipShape(chip, index);
            });
            chips.sort((first, second) => first.position - second.position);

            this.syncChipPositions();
        },

        ensureChips() {
            const value = this.element.config.chips.value;
            if (!Array.isArray(value)) {
                this.element.config.chips.value = this.objectValues(value);
            }

            this.element.config.chips.value.forEach((chip, index) => {
                if (!chip || typeof chip !== 'object' || Array.isArray(chip)) {
                    this.element.config.chips.value[index] = this.emptyChip(index);
                }
            });

            return this.element.config.chips.value;
        },

        ensureChipShape(chip, index) {
            if (typeof chip.id !== 'string') {
                chip.id = '';
            }
            if (typeof chip.label !== 'string') {
                chip.label = '';
            }
            if (typeof chip.url !== 'string') {
                chip.url = '';
            }
            if (typeof chip.position !== 'number' || !Number.isFinite(chip.position)) {
                chip.position = index;
            }
        },

        objectValues(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return [];
            }

            return Object.values(value);
        },

        emptyChip(position) {
            return {
                id: '',
                position,
                label: '',
                url: '',
            };
        },

        syncChipPositions() {
            this.chips.forEach((chip, index) => {
                chip.position = index;
            });
        },

        addChip() {
            this.chips.push(this.emptyChip(this.chips.length));
            this.onUpdate();
        },

        removeChip(index) {
            this.chips.splice(index, 1);
            this.onUpdate();
        },

        moveChip(index, offset) {
            const targetIndex = index + offset;
            if (targetIndex < 0 || targetIndex >= this.chips.length) {
                return;
            }

            const [chip] = this.chips.splice(index, 1);
            this.chips.splice(targetIndex, 0, chip);
            this.onUpdate();
        },
    },
};
