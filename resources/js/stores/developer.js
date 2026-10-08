import { defineStore } from 'pinia';
import { api, errorMessage, validationErrors } from '@/lib/api';

/**
 * Portal developer: kredit upload, produk, versi, unggahan presigned, dan pesanan.
 */
export const useDeveloperStore = defineStore('developer', {
    state: () => ({
        overview: null,
        overviewLoading: false,
        products: [],
        productsMeta: null,
        productsLoading: false,
        product: null,
        versions: [],
        versionsLoading: false,
        errors: {},
        orders: [],
        ordersLoading: false,
    }),

    actions: {
        async loadOverview() {
            this.overviewLoading = true;

            try {
                const { data } = await api.get('/developer/overview');
                this.overview = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.overviewLoading = false;
            }
        },

        async loadProducts(page = 1) {
            this.productsLoading = true;

            try {
                const { data } = await api.get('/developer/products', { params: { page } });
                this.products = data.data;
                this.productsMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.productsLoading = false;
            }
        },

        async loadProduct(slug) {
            try {
                const { data } = await api.get(`/products/${slug}`);
                this.product = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async createProduct(payload) {
            this.errors = {};

            try {
                const { data } = await api.post('/developer/products', payload);

                return { ok: true, message: data.message, product: data.data };
            } catch (error) {
                this.errors = validationErrors(error);

                return { ok: false, message: this.failureMessage(error) };
            }
        },

        async updateProduct(slug, payload) {
            this.errors = {};

            try {
                const { data } = await api.put(`/developer/products/${slug}`, payload);

                return { ok: true, message: data.message, product: data.data };
            } catch (error) {
                this.errors = validationErrors(error);

                return { ok: false, message: this.failureMessage(error) };
            }
        },

        async publishProduct(slug) {
            return this.productAction(`/developer/products/${slug}/publish`);
        },

        async archiveProduct(slug) {
            return this.productAction(`/developer/products/${slug}/archive`);
        },

        async loadVersions(slug) {
            this.versionsLoading = true;

            try {
                const { data } = await api.get(`/products/${slug}/versions`);
                this.versions = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.versionsLoading = false;
            }
        },

        async createVersion(slug, payload) {
            this.errors = {};

            try {
                const { data } = await api.post(`/developer/products/${slug}/versions`, payload);

                return { ok: true, message: data.message, version: data.data };
            } catch (error) {
                this.errors = validationErrors(error);

                return { ok: false, message: this.failureMessage(error) };
            }
        },

        async publishVersion(slug, versionId) {
            try {
                const { data } = await api.post(`/developer/products/${slug}/versions/${versionId}/publish`);

                return { ok: true, message: data.message, version: data.data };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        /**
         * Unggah berkas ke penyimpanan lewat endpoint server, lalu kembalikan
         * key yang bisa dipakai saat membuat versi produk.
         *
         * @param {(percent: number) => void} [onProgress]
         */
        async uploadFile(kind, file, onProgress) {
            try {
                const form = new FormData();
                form.append('kind', kind);
                form.append('file', file);

                const res = await api.post('/developer/uploads', form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (event) => {
                        if (onProgress && event.total) {
                            onProgress(Math.round((event.loaded / event.total) * 100));
                        }
                    },
                });

                const { key, size } = res.data.data;

                return { ok: true, key, size };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async createOrder(payload) {
            try {
                const { data } = await api.post('/orders', payload);

                return { ok: true, message: data.message, order: data.data, payment: data.payment };
            } catch (error) {
                this.errors = validationErrors(error);

                return { ok: false, message: this.failureMessage(error) };
            }
        },

        async loadOrders(page = 1) {
            this.ordersLoading = true;

            try {
                const { data } = await api.get('/developer/orders', { params: { page } });
                this.orders = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.ordersLoading = false;
            }
        },

        async productAction(url) {
            try {
                const { data } = await api.post(url);

                return { ok: true, message: data.message, product: data.data };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        failureMessage(error) {
            return Object.keys(this.errors).length > 0 ? 'Periksa kembali isian.' : errorMessage(error);
        },
    },
});
