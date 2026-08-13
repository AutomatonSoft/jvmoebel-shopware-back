/**
 * Recursive editor row for one manual-links item (label, url, iconMediaId, children).
 * Nested via the same component up to depth 5 (matches maxDepth clamp).
 */
import template from './sw-cms-el-config-jv-side-navigation-manual-item.html.twig';
import './sw-cms-el-config-jv-side-navigation-manual-item.scss';

const { Utils } = Shopware;

export default {
    template,

    props: {
        item: {
            type: Object,
            required: true,
        },
        depth: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    emits: ['update', 'remove'],

    computed: {
        // Align with category-tree maxDepth (1…5) — no deeper nesting in config.
        canAddChild() {
            return this.depth < 5;
        },
    },

    created() {
        if (!Array.isArray(this.item.children)) {
            this.item.children = [];
        }
    },

    methods: {
        onUpdate() {
            this.$emit('update');
        },

        onIconChange(mediaId) {
            this.item.iconMediaId = mediaId || null;
            this.onUpdate();
        },

        addChild() {
            if (!this.canAddChild) {
                return;
            }

            if (!Array.isArray(this.item.children)) {
                this.item.children = [];
            }

            this.item.children.push({
                id: Utils.createId(),
                label: '',
                url: '',
                openInNewTab: false,
                iconMediaId: null,
                children: [],
            });
            this.onUpdate();
        },

        removeChild(index) {
            this.item.children.splice(index, 1);
            this.onUpdate();
        },
    },
};
