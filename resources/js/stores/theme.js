import { defineStore } from 'pinia';

const KEY = 'appfeed-theme';

function apply(theme) {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem(KEY, theme);
}

export const useThemeStore = defineStore('theme', {
    state: () => ({
        theme: localStorage.getItem(KEY) === 'light' ? 'light' : 'dark',
    }),
    getters: {
        isLight: (state) => state.theme === 'light',
    },
    actions: {
        toggle() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            apply(this.theme);
        },
    },
});
