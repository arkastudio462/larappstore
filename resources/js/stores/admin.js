import { defineStore } from 'pinia';
import { api, errorMessage, validationErrors } from '@/lib/api';

/**
 * Panel admin: laporan konten, kategori, koleksi, pengguna, pesanan, statistik.
 */
export const useAdminStore = defineStore('admin', {
    state: () => ({
        stats: null,
        statsLoading: false,
        reports: [],
        reportsMeta: null,
        reportsLoading: false,
        categories: [],
        categoriesLoading: false,
        collections: [],
        collectionsLoading: false,
        users: [],
        usersMeta: null,
        usersLoading: false,
        orders: [],
        ordersMeta: null,
        ordersLoading: false,
        errors: {},
    }),

    actions: {
        async loadStats() {
            this.statsLoading = true;

            try {
                const { data } = await api.get('/admin/stats');
                this.stats = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.statsLoading = false;
            }
        },

        async loadReports(status = 'open') {
            this.reportsLoading = true;

            try {
                const { data } = await api.get('/admin/reports', { params: { status } });
                this.reports = data.data;
                this.reportsMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.reportsLoading = false;
            }
        },

        async resolveReport(id, status) {
            try {
                const { data } = await api.post(`/admin/reports/${id}/resolve`, { status });

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        /**
         * Hapus konten yang dilaporkan. `reportable_type` dari resource berupa
         * nama kelas pendek (Post, Comment, Product, Review).
         */
        async deleteContent(report) {
            const segment = `${report.reportable_type.toLowerCase()}s`;

            try {
                const { data } = await api.delete(`/admin/${segment}/${report.subject.id}`);

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadCategories() {
            this.categoriesLoading = true;

            try {
                const { data } = await api.get('/admin/categories');
                this.categories = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.categoriesLoading = false;
            }
        },

        async saveCategory(payload, id = null) {
            this.errors = {};

            try {
                const { data } = id
                    ? await api.put(`/admin/categories/${id}`, payload)
                    : await api.post('/admin/categories', payload);

                return { ok: true, message: data.message };
            } catch (error) {
                this.errors = validationErrors(error);

                return { ok: false, message: this.failureMessage(error) };
            }
        },

        async deleteCategory(id) {
            try {
                const { data } = await api.delete(`/admin/categories/${id}`);

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadCollections() {
            this.collectionsLoading = true;

            try {
                const { data } = await api.get('/admin/collections');
                this.collections = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.collectionsLoading = false;
            }
        },

        async saveCollection(payload, id = null) {
            this.errors = {};

            try {
                const { data } = id
                    ? await api.put(`/admin/collections/${id}`, payload)
                    : await api.post('/admin/collections', payload);

                return { ok: true, message: data.message };
            } catch (error) {
                this.errors = validationErrors(error);

                return { ok: false, message: this.failureMessage(error) };
            }
        },

        async deleteCollection(id) {
            try {
                const { data } = await api.delete(`/admin/collections/${id}`);

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadUsers(params = {}) {
            this.usersLoading = true;

            try {
                const { data } = await api.get('/admin/users', { params });
                this.users = data.data;
                this.usersMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.usersLoading = false;
            }
        },

        async updateUser(id, payload) {
            try {
                const { data } = await api.patch(`/admin/users/${id}`, payload);

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadOrders(status = '') {
            this.ordersLoading = true;

            try {
                const { data } = await api.get('/admin/orders', { params: status ? { status } : {} });
                this.orders = data.data;
                this.ordersMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.ordersLoading = false;
            }
        },

        failureMessage(error) {
            return Object.keys(this.errors).length > 0 ? 'Periksa kembali isian.' : errorMessage(error);
        },
    },
});
