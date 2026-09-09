/**
 * Why JVMöbel config for CMS element `jv-why-jvmoebel`.
 */
import template from './sw-cms-el-config-jv-why-jvmoebel.html.twig';
import './sw-cms-el-config-jv-why-jvmoebel.scss';

const { Mixin, Utils } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        benefits() {
            return this.ensureBenefits();
        },

        iconOptions() {
            return [
                { value: 'advice', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.icon.advice') },
                { value: 'design', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.icon.design') },
                { value: 'payment', label: this.$t('cms.elements.jv-why-jvmoebel.config.benefits.icon.payment') },
            ];
        },
    },

    created() {
        this.initElementConfig('jv-why-jvmoebel');
        this.ensureBenefits();
        this.ensureUniqueBenefitIds();
        this.ensureViewAll();
    },

    methods: {
        onUpdate() {
            this.syncBenefitPositions();
            this.ensureUniqueBenefitIds();
            this.$emit('element-update', this.element);
        },

        syncBenefitPositions() {
            this.benefits.forEach((benefit, index) => {
                benefit.position = index;
            });
        },

        ensureBenefits() {
            if (!Array.isArray(this.element.config.benefits.value)) {
                this.element.config.benefits.value = [];
            }

            return this.element.config.benefits.value;
        },

        ensureViewAll() {
            if (!this.element.config.viewAll.value || typeof this.element.config.viewAll.value !== 'object') {
                this.element.config.viewAll.value = {
                    label: '',
                    url: '',
                };
            }
        },

        ensureUniqueBenefitIds() {
            const seenIds = new Set();

            this.benefits.forEach((benefit, index) => {
                let id = typeof benefit.id === 'string' ? benefit.id.trim() : '';

                if (!id || seenIds.has(id)) {
                    const title = typeof benefit.title === 'string' ? benefit.title.trim() : '';
                    id = title ? `${title}-${index}` : Utils.createId();
                    if (seenIds.has(id)) {
                        id = `${id}-${index}`;
                    }
                    benefit.id = id;
                }

                seenIds.add(id);
            });
        },

        addBenefit() {
            this.benefits.push({
                id: Utils.createId(),
                position: this.benefits.length,
                icon: 'design',
                title: '',
                description: '',
                url: '',
            });
            this.onUpdate();
        },

        removeBenefit(index) {
            this.benefits.splice(index, 1);
            this.onUpdate();
        },
    },
};
