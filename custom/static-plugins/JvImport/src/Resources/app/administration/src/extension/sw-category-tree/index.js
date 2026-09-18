import template from './sw-category-tree.html.twig';
import './sw-category-tree.scss';

const MAX_STICKY_ROWS = 6;
const FALLBACK_ROW_HEIGHT = 42;
const MIN_ROW_INDENT = 4;

Shopware.Component.override('sw-category-tree', {
    template,

    data() {
        return {
            stickyAncestors: [],
            stickyOverlayStyle: null,
        };
    },

    watch: {
        isLoadingInitialData(isLoading) {
            if (!isLoading) {
                this.$nextTick(this.scheduleStickyUpdate);
            }
        },

        categories() {
            this.$nextTick(this.scheduleStickyUpdate);
        },
    },

    mounted() {
        window.addEventListener('scroll', this.scheduleStickyUpdate, { capture: true, passive: true });
        window.addEventListener('resize', this.scheduleStickyUpdate, { passive: true });

        this.$nextTick(this.scheduleStickyUpdate);
    },

    beforeUnmount() {
        window.removeEventListener('scroll', this.scheduleStickyUpdate, { capture: true });
        window.removeEventListener('resize', this.scheduleStickyUpdate);

        this.jvTreeObserver?.disconnect();
        this.jvTreeObserver = null;

        if (this.jvStickyFrame) {
            window.cancelAnimationFrame(this.jvStickyFrame);
            this.jvStickyFrame = null;
        }
    },

    methods: {
        scheduleStickyUpdate() {
            if (this.jvStickyFrame) {
                return;
            }

            this.jvStickyFrame = window.requestAnimationFrame(() => {
                this.jvStickyFrame = null;
                this.updateStickyAncestors();
            });
        },

        /**
         * The overlay is authored in this component but has to live inside the scrolling
         * element of sw-tree, so that it is positioned and clipped by the visible tree.
         */
        resolveStickyOverlayHost() {
            const overlay = this.$refs.stickyScroll;
            const content = this.$el?.querySelector?.('.sw-tree__content') ?? null;

            if (!overlay || !content) {
                return null;
            }

            if (content.firstElementChild !== overlay) {
                content.prepend(overlay);
            }

            this.observeTreeItems(content);

            return content;
        },

        observeTreeItems(content) {
            const treeItems = content.querySelector('.tree-items');

            if (!treeItems || this.jvObservedTreeItems === treeItems) {
                return;
            }

            this.jvTreeObserver?.disconnect();
            this.jvObservedTreeItems = treeItems;
            this.jvTreeObserver = new MutationObserver(this.scheduleStickyUpdate);
            this.jvTreeObserver.observe(treeItems, { childList: true, subtree: true });
        },

        /**
         * The category module resets the height limits of `.sw-tree__content`, so the tree
         * container grows to its full height and never scrolls vertically itself — the
         * scrolling is done by an ancestor panel. Only the element that really scrolls can
         * tell us where the visible top edge of the tree is.
         */
        resolveVerticalScroller(content) {
            const scrolls = (element) => element.scrollHeight > element.clientHeight + 1;
            const cached = this.jvVerticalScroller;

            if (cached?.isConnected && scrolls(cached) && (cached === content || cached.contains(content))) {
                return cached;
            }

            this.jvVerticalScroller = null;

            if (scrolls(content)) {
                this.jvVerticalScroller = content;

                return content;
            }

            for (let node = content.parentElement; node && node !== document.body; node = node.parentElement) {
                const { overflowY } = window.getComputedStyle(node);

                if (/^(auto|scroll|overlay)$/.test(overflowY) && scrolls(node)) {
                    this.jvVerticalScroller = node;

                    return node;
                }
            }

            return null;
        },

        updateStickyAncestors() {
            const content = this.resolveStickyOverlayHost();

            if (!content) {
                this.applyStickyState([], null);
                return;
            }

            const contentRect = content.getBoundingClientRect();
            const scroller = this.resolveVerticalScroller(content);
            const scrollerRect = scroller ? scroller.getBoundingClientRect() : contentRect;

            // Top edge of the tree, as far as it is actually visible inside the scroller.
            const lineTop = Math.max(contentRect.top, scrollerRect.top);
            const visibleBottom = Math.min(contentRect.bottom, scrollerRect.bottom);

            // The stack must never grow tall enough to hide the tree behind it.
            const maxRows = Math.min(
                MAX_STICKY_ROWS,
                Math.floor((visibleBottom - lineTop) / FALLBACK_ROW_HEIGHT) - 1,
            );

            if (contentRect.width === 0 || maxRows < 1) {
                this.applyStickyState([], null);
                return;
            }

            const pinned = [];

            content.querySelectorAll('.sw-tree-item').forEach((group) => {
                if (pinned.length >= maxRows) {
                    return;
                }

                const row = group.querySelector(':scope > .sw-tree-item__element');
                const children = group.querySelector(':scope > .sw-tree-item__children');

                if (!row || !children) {
                    return;
                }

                const rowRect = row.getBoundingClientRect();
                const rowHeight = rowRect.height || FALLBACK_ROW_HEIGHT;
                const stackBottom = Math.round(lineTop + pinned.length * rowHeight);

                const rowIsAboveStack = rowRect.top <= stackBottom - 1;
                const groupStillInView = children.getBoundingClientRect().bottom >= stackBottom + rowHeight;

                if (!rowIsAboveStack || !groupStillInView) {
                    return;
                }

                const label = row.querySelector('.sw-tree-item__label');
                const categoryId = group.dataset.itemId;

                pinned.push({
                    id: categoryId,
                    name: label?.textContent?.trim() || this.stickyAncestorFallbackName(categoryId),
                    indent: Math.max(
                        MIN_ROW_INDENT,
                        Math.round((label ?? row).getBoundingClientRect().left - contentRect.left),
                    ),
                });
            });

            // The overlay sits inside the scrolled content, so it has to be pushed back by
            // the current scroll offset to stay parked at the top edge of the visible tree.
            this.applyStickyState(pinned, {
                top: `${content.scrollTop + lineTop - contentRect.top - content.clientTop}px`,
                left: `${content.scrollLeft}px`,
                width: `${content.clientWidth}px`,
            });
        },

        applyStickyState(pinned, overlayStyle) {
            const nextKey = JSON.stringify([pinned, overlayStyle]);

            if (nextKey === this.jvStickyKey) {
                return;
            }

            this.jvStickyKey = nextKey;
            this.stickyAncestors = pinned;
            this.stickyOverlayStyle = overlayStyle;
        },

        stickyAncestorFallbackName(categoryId) {
            const category = this.loadedCategories[categoryId];

            return category?.translated?.name || category?.name || '';
        },

        onStickyAncestorClick(categoryId) {
            const category = this.loadedCategories[categoryId];

            if (category) {
                this.changeCategory(category);
                return;
            }

            this.$router.push({
                name: 'sw.category.detail',
                params: { id: categoryId },
            });
        },
    },
});
