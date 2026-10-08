import { defineStore } from 'pinia';
import { api, errorMessage } from '@/lib/api';

/**
 * State katalog: kategori, daftar produk, detail produk, pencarian, koleksi.
 *
 * Setiap aksi mengembalikan `{ ok, message }` agar halaman cukup memeriksa `ok`.
 */
export const useCatalogStore = defineStore('catalog', {
    state: () => ({
        categories: [],
        categoriesLoaded: false,
        products: [],
        productsMeta: null,
        productsLoading: false,
        product: null,
        productLoading: false,
        versions: [],
        reviews: [],
        reviewsMeta: null,
        searchResults: [],
        searchMeta: null,
        searchLoading: false,
        collection: null,
        collectionLoading: false,
        purchases: [],
        purchasesMeta: null,
        purchasesLoading: false,
    }),

    actions: {
        async loadCategories() {
            try {
                const { data } = await api.get('/categories');
                this.categories = data.data;
                this.categoriesLoaded = true;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadProducts(params = {}) {
            this.productsLoading = true;

            try {
                const { data } = await api.get('/products', { params });
                this.products = data.data;
                this.productsMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                this.products = [];
                this.productsMeta = null;

                return { ok: false, message: errorMessage(error) };
            } finally {
                this.productsLoading = false;
            }
        },

        async loadProduct(slug) {
            this.productLoading = true;
            this.product = null;
            this.versions = [];
            this.reviews = [];
            this.reviewsMeta = null;

            try {
                const { data } = await api.get(`/products/${slug}`);
                this.product = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.productLoading = false;
            }
        },

        async loadVersions(slug) {
            try {
                const { data } = await api.get(`/products/${slug}/versions`);
                this.versions = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadReviews(slug, page = 1) {
            try {
                const { data } = await api.get(`/products/${slug}/reviews`, { params: { page } });
                this.reviews = data.data;
                this.reviewsMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async search(query) {
            this.searchLoading = true;

            try {
                const { data } = await api.get('/search', { params: { q: query } });
                this.searchResults = data.data;
                this.searchMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.searchLoading = false;
            }
        },

        async loadCollection(slug) {
            this.collectionLoading = true;
            this.collection = null;

            try {
                const { data } = await api.get(`/collections/${slug}`);
                this.collection = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.collectionLoading = false;
            }
        },

        async submitReview(slug, { rating, body }) {
            try {
                const { data } = await api.post(`/products/${slug}/reviews`, { rating, body });

                if (this.product) {
                    this.product.my_review_id = data.data.id;
                }

                return { ok: true, message: data.message, review: data.data };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async download(slug) {
            try {
                const { data } = await api.post(`/products/${slug}/download`);

                return { ok: true, message: data.message, url: data.data.url };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        /**
         * Beli produk berbayar: buat pesanan `app_purchase` lalu minta token
         * pembayaran. Produk aktif setelah webhook mengonfirmasi (PRD §5).
         */
        async purchaseProduct(product) {
            try {
                const { data } = await api.post('/orders', {
                    type: 'app_purchase',
                    product_id: product.id,
                });

                return { ok: true, message: data.message, payment: data.payment, order: data.data };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadPurchases(page = 1) {
            this.purchasesLoading = true;

            try {
                const { data } = await api.get('/me/purchases', { params: { page } });
                this.purchases = data.data;
                this.purchasesMeta = data.meta ?? null;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.purchasesLoading = false;
            }
        },
    },
});
