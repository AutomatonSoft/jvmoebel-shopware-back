import template from './sw-cms-el-config-jv-editorial-team-grid.html.twig';
import './sw-cms-el-config-jv-editorial-team-grid.scss';

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
        members() {
            return this.ensureMembers();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    created() {
        this.initElementConfig('jv-editorial-team-grid');
        this.ensureMembers();
    },

    methods: {
        onUpdate() {
            this.syncMemberPositions();
            this.$emit('element-update', this.element);
        },

        syncMemberPositions() {
            this.members.forEach((member, index) => {
                member.position = index;
            });
        },

        ensureMembers() {
            if (!Array.isArray(this.element.config.members.value)) {
                this.element.config.members.value = [];
            }

            return this.element.config.members.value;
        },

        addMember() {
            this.members.push({
                id: '',
                name: '',
                role: '',
                url: '',
                position: this.members.length,
                imageMedia: null,
            });
            this.onUpdate();
        },

        removeMember(index) {
            this.members.splice(index, 1);
            this.onUpdate();
        },

        memberUploadTag(index) {
            return `cms-element-jv-editorial-team-grid-member-${this.element.id}-${index}`;
        },

        memberPreviewSource(member) {
            if (member?.image?.id) {
                return member.image;
            }

            return member.imageMedia;
        },

        async onMemberUpload(index, { targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);
            const member = this.members[index];
            if (!member) {
                return;
            }

            member.imageMedia = mediaEntity.id;
            member.image = mediaEntity;
            this.onUpdate();
        },

        onMemberRemove(index) {
            const member = this.members[index];
            if (!member) {
                return;
            }

            member.imageMedia = null;
            member.image = null;
            this.onUpdate();
        },

        onOpenMemberMediaModal(index) {
            this.mediaModalIndex = index;
        },

        onCloseMemberMediaModal() {
            this.mediaModalIndex = null;
        },

        onMemberSelectionChanges(mediaEntities) {
            const index = this.mediaModalIndex;
            if (index === null) {
                return;
            }

            const media = mediaEntities[0];
            const member = this.members[index];
            if (!media || !member) {
                return;
            }

            member.imageMedia = media.id;
            member.image = media;
            this.onUpdate();
            this.onCloseMemberMediaModal();
        },
    },
};
