/** Canvas preview for CMS element `jv-shop-the-look`. */
import template from './sw-cms-el-jv-shop-the-look.html.twig';
import './sw-cms-el-jv-shop-the-look.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            hotspotDrag: null,
        };
    },

    computed: {
        title() {
            return (this.element?.config?.title?.value || '').trim();
        },

        eyebrow() {
            return (this.element?.config?.eyebrow?.value || '').trim();
        },

        items() {
            const items = this.element?.config?.items?.value;

            return Array.isArray(items) ? items : [];
        },

        imageUrl() {
            return this.element?.data?.imageMedia?.url || null;
        },
    },

    created() {
        this.initElementConfig('jv-shop-the-look');
    },

    beforeUnmount() {
        this.releaseHotspotPointerCapture();
    },

    methods: {
        itemName(item, index) {
            return item.name || this.$t('cms.elements.jv-shop-the-look.component.itemPlaceholder', { index: index + 1 });
        },

        hotspotCoordinate(value) {
            const coordinate = Number(value);

            return Number.isFinite(coordinate) && coordinate >= 0 && coordinate <= 100 ? coordinate : 50;
        },

        hotspotStyle(item) {
            return {
                left: `${this.hotspotCoordinate(item?.hotspot?.x)}%`,
                top: `${this.hotspotCoordinate(item?.hotspot?.y)}%`,
            };
        },

        isHotspotDragging(item) {
            return this.hotspotDrag?.item === item;
        },

        onHotspotPointerDown(event, item) {
            if (this.disabled || this.hotspotDrag || event.button !== 0) {
                return;
            }

            const imageElement = this.$refs.image;
            const hotspotElement = event.currentTarget;
            const imageBounds = imageElement?.getBoundingClientRect();

            if (!imageBounds?.width || !imageBounds.height || !hotspotElement?.setPointerCapture) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const x = this.hotspotCoordinate(item?.hotspot?.x);
            const y = this.hotspotCoordinate(item?.hotspot?.y);

            if (!item.hotspot || typeof item.hotspot !== 'object' || Array.isArray(item.hotspot)) {
                item.hotspot = { x, y };
            }

            this.hotspotDrag = {
                pointerId: event.pointerId,
                item,
                hotspotElement,
                startX: x,
                startY: y,
                offsetX: event.clientX - imageBounds.left - (imageBounds.width * x) / 100,
                offsetY: event.clientY - imageBounds.top - (imageBounds.height * y) / 100,
            };
            hotspotElement.setPointerCapture(event.pointerId);
        },

        onHotspotPointerMove(event) {
            if (!this.isActiveHotspotPointer(event)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.updateHotspotFromPointer(event);
        },

        onHotspotPointerUp(event) {
            if (!this.isActiveHotspotPointer(event)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.updateHotspotFromPointer(event);

            const drag = this.hotspotDrag;
            const changed = drag.item.hotspot.x !== drag.startX || drag.item.hotspot.y !== drag.startY;

            this.releaseHotspotPointerCapture();

            if (changed) {
                this.$emit('element-update', this.element);
            }
        },

        onHotspotPointerCancel(event) {
            if (!this.isActiveHotspotPointer(event)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            this.hotspotDrag.item.hotspot.x = this.hotspotDrag.startX;
            this.hotspotDrag.item.hotspot.y = this.hotspotDrag.startY;
            this.releaseHotspotPointerCapture();
        },

        onHotspotPointerCaptureLost(event) {
            if (!this.isActiveHotspotPointer(event)) {
                return;
            }

            const drag = this.hotspotDrag;
            const changed = drag.item.hotspot.x !== drag.startX || drag.item.hotspot.y !== drag.startY;

            this.hotspotDrag = null;

            if (changed) {
                this.$emit('element-update', this.element);
            }
        },

        isActiveHotspotPointer(event) {
            return this.hotspotDrag?.pointerId === event.pointerId;
        },

        updateHotspotFromPointer(event) {
            const imageBounds = this.$refs.image?.getBoundingClientRect();
            if (!imageBounds?.width || !imageBounds.height) {
                return;
            }

            const x = (
                (event.clientX - imageBounds.left - this.hotspotDrag.offsetX) /
                imageBounds.width
            ) * 100;
            const y = (
                (event.clientY - imageBounds.top - this.hotspotDrag.offsetY) /
                imageBounds.height
            ) * 100;

            this.hotspotDrag.item.hotspot.x = this.normalizeHotspotCoordinate(x);
            this.hotspotDrag.item.hotspot.y = this.normalizeHotspotCoordinate(y);
        },

        normalizeHotspotCoordinate(value) {
            const clampedValue = Math.min(100, Math.max(0, value));

            return Math.round(clampedValue * 10) / 10;
        },

        releaseHotspotPointerCapture() {
            if (!this.hotspotDrag) {
                return;
            }

            const { hotspotElement, pointerId } = this.hotspotDrag;
            this.hotspotDrag = null;

            if (hotspotElement.hasPointerCapture?.(pointerId)) {
                hotspotElement.releasePointerCapture(pointerId);
            }
        },
    },
};
