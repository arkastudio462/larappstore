import { defineStore } from 'pinia';
import { api, errorMessage, validationErrors } from '@/lib/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        ready: false,
        busy: false,
        errors: {},
        notice: '',
    }),

    getters: {
        isAuthenticated: (state) => state.user !== null,
        role: (state) => state.user?.role ?? 'guest',
        isDeveloper: (state) => state.user?.role === 'developer' || state.user?.role === 'admin',
        isAdmin: (state) => state.user?.role === 'admin',
        uploadCredits: (state) => state.user?.developer_profile?.upload_credits ?? 0,
        hasUnlimitedUploads: (state) => state.user?.developer_profile?.unlimited_uploads ?? false,
    },

    actions: {
        /**
         * Muat sesi yang sedang berjalan sekali di awal. Kegagalan berarti tamu,
         * bukan error.
         */
        async init() {
            if (this.ready) {
                return;
            }

            try {
                const { data } = await api.get('/auth/me');
                this.user = data.data;
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },

        async login(payload) {
            return this.request(() => api.post('/auth/login', payload), (data) => {
                this.user = data.data;
            });
        },

        async register(payload) {
            return this.request(() => api.post('/auth/register', payload), (data) => {
                this.user = data.data;
            });
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } finally {
                this.user = null;
            }
        },

        async updateProfile(payload) {
            if (payload instanceof FormData) {
                payload.append('_method', 'PUT');

                return this.request(() => api.post('/auth/me', payload, { headers: { 'Content-Type': 'multipart/form-data' } }), (data) => {
                    this.user = data.data;
                });
            }

            return this.request(() => api.put('/auth/me', payload), (data) => {
                this.user = data.data;
            });
        },

        async upgradeToDeveloper() {
            return this.request(() => api.post('/developer/upgrade'), (data) => {
                this.user = data.data.user;
            });
        },

        /**
         * Menghapus seluruh pesan error validasi, misalnya saat berpindah halaman.
         */
        clearErrors() {
            this.errors = {};
        },

        /**
         * Menimpa pesan error validasi, dipakai hasil validasi di sisi kirim.
         */
        setErrors(errors) {
            this.errors = { ...errors };
        },

        /**
         * Menghapus pesan error satu kolom begitu pengguna mulai mengoreksi isiannya.
         */
        clearError(field) {
            if (!this.errors[field]) {
                return;
            }

            const errors = { ...this.errors };
            delete errors[field];
            this.errors = errors;
        },

        /**
         * Menjalankan satu permintaan, menangkap error validasi 422 ke `errors`,
         * dan mengembalikan hasil seragam agar halaman cukup memeriksa `ok`.
         *
         * @param {() => Promise<{ data: any }>} request
         * @param {(data: any) => void} [apply]
         * @returns {Promise<{ ok: boolean, message: string }>}
         */
        async request(request, apply) {
            this.busy = true;
            this.errors = {};
            this.notice = '';

            try {
                const { data } = await request();
                apply?.(data);
                this.notice = data?.message ?? '';

                return { ok: true, message: this.notice };
            } catch (error) {
                this.errors = validationErrors(error);

                // Ringkasan server memakai sufiks bahasa Inggris bawaan framework
                // ("(and N more errors)"). Bila error per-kolom sudah ada, tampilkan
                // pesan umum saja karena detailnya sudah terlihat di bawah kolom.
                const message = Object.keys(this.errors).length > 0
                    ? 'Periksa kembali isian formulir.'
                    : errorMessage(error);

                return { ok: false, message };
            } finally {
                this.busy = false;
            }
        },
    },
});
