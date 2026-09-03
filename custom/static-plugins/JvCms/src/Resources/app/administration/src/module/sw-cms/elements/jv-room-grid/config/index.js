/**
 * Room grid config for CMS element `jv-room-grid`.
 */
import template from './sw-cms-el-config-jv-room-grid.html.twig';
import './sw-cms-el-config-jv-room-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    inject: ['repositoryFactory'],

    data() {
        return {
            mediaModalIndex: null,
        };
    },

    computed: {
        rooms() {
            return this.ensureRooms();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    created() {
        this.initElementConfig('jv-room-grid');
        this.ensureRooms();
    },

    methods: {
        onUpdate() {
            this.syncRoomPositions();
            this.$emit('element-update', this.element);
        },

        syncRoomPositions() {
            this.rooms.forEach((room, index) => {
                room.position = index;
            });
        },

        ensureRooms() {
            if (!Array.isArray(this.element.config.rooms.value)) {
                this.element.config.rooms.value = [];
            }

            return this.element.config.rooms.value;
        },

        addRoom() {
            this.rooms.push({
                id: '',
                label: '',
                title: '',
                url: '',
                featured: false,
                position: this.rooms.length,
                imageMedia: null,
            });
            this.onUpdate();
        },

        removeRoom(index) {
            this.rooms.splice(index, 1);
            this.onUpdate();
        },

        roomUploadTag(index) {
            return `cms-element-jv-room-grid-room-${this.element.id}-${index}`;
        },

        roomPreviewSource(room) {
            if (room?.image?.id) {
                return room.image;
            }

            return room.imageMedia;
        },

        async onRoomUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const room = this.rooms[index];
            if (!room) {
                return;
            }

            room.imageMedia = mediaEntity.id;
            room.image = mediaEntity;
            this.onUpdate();
        },

        onRoomRemove(index) {
            const room = this.rooms[index];
            if (!room) {
                return;
            }

            room.imageMedia = null;
            room.image = null;
            this.onUpdate();
        },

        onOpenRoomMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseRoomMediaModal() {
            this.mediaModalIndex = null;
        },

        onRoomSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const room = this.rooms[index];
            if (!media || !room) {
                return;
            }

            room.imageMedia = media.id;
            room.image = media;
            this.onUpdate();
            this.onCloseRoomMediaModal();
        },
    },
};
