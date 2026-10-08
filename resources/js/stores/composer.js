import { defineStore } from 'pinia';

export const useComposerStore = defineStore('composer', {
    state: () => ({ open: false }),
    actions: {
        show() {
            this.open = true;
        },
        hide() {
            this.open = false;
        },
    },
});
