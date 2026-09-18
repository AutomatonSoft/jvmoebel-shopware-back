import template from './jv-seo-index.html.twig';

export default {
    template,

    inject: ['acl'],

    data() {
        return {
            searchTerm: '',
        };
    },

    methods: {
        onSearch(searchTerm) {
            this.searchTerm = searchTerm;
        },
    },
};
