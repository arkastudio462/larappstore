import { defineStore } from 'pinia';
import { api, errorMessage } from '@/lib/api';

export const useNotificationStore = defineStore('notifications', {
    state: () => ({
        items: [],
        unreadCount: 0,
        loading: false,
        loaded: false,
    }),

    actions: {
        async load() {
            this.loading = true;

            try {
                const { data } = await api.get('/notifications');

                this.items = data.data;
                this.unreadCount = data.meta.unread_count;
                this.loaded = true;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.loading = false;
            }
        },

        async markAllRead() {
            try {
                const { data } = await api.post('/notifications/read-all');

                this.items = this.items.map((item) => ({ ...item, read: true }));
                this.unreadCount = 0;

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        reset() {
            this.items = [];
            this.unreadCount = 0;
            this.loaded = false;
        },
    },
});
