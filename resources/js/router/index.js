import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: () => import('../pages/Home.vue'),
        },
        {
            path: '/masuk',
            name: 'login',
            component: () => import('../pages/auth/Login.vue'),
            meta: { guestOnly: true },
        },
        {
            path: '/daftar',
            name: 'register',
            component: () => import('../pages/auth/Register.vue'),
            meta: { guestOnly: true },
        },
        {
            path: '/u/:username',
            name: 'user.show',
            component: () => import('../pages/user/Profile.vue'),
        },
        {
            path: '/akun/profil',
            name: 'account.profile',
            component: () => import('../pages/account/Profile.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/akun/developer',
            name: 'account.developer',
            component: () => import('../pages/account/Developer.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/akun/notifikasi',
            name: 'account.notifications',
            component: () => import('../pages/account/Notifications.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/akun/pembelian',
            name: 'account.purchases',
            component: () => import('../pages/account/Purchases.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/kelola',
            name: 'kelola.dashboard',
            component: () => import('../pages/kelola/Dashboard.vue'),
            meta: { requiresAuth: true, requiresDeveloper: true },
        },
        {
            path: '/kelola/produk',
            name: 'kelola.products',
            component: () => import('../pages/kelola/Products.vue'),
            meta: { requiresAuth: true, requiresDeveloper: true },
        },
        {
            path: '/kelola/produk/baru',
            name: 'kelola.products.create',
            component: () => import('../pages/kelola/ProductForm.vue'),
            meta: { requiresAuth: true, requiresDeveloper: true },
        },
        {
            path: '/kelola/produk/:slug/edit',
            name: 'kelola.products.edit',
            component: () => import('../pages/kelola/ProductForm.vue'),
            meta: { requiresAuth: true, requiresDeveloper: true },
        },
        {
            path: '/kelola/versi',
            name: 'kelola.versions',
            component: () => import('../pages/kelola/Versions.vue'),
            meta: { requiresAuth: true, requiresDeveloper: true },
        },
        {
            path: '/kelola/beli-kredit',
            name: 'kelola.credits',
            component: () => import('../pages/kelola/BuyCredits.vue'),
            meta: { requiresAuth: true, requiresDeveloper: true },
        },
        {
            path: '/admin',
            name: 'admin.reports',
            component: () => import('../pages/admin/Reports.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/admin/statistik',
            name: 'admin.stats',
            component: () => import('../pages/admin/Stats.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/admin/kategori',
            name: 'admin.categories',
            component: () => import('../pages/admin/Categories.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/admin/koleksi',
            name: 'admin.collections',
            component: () => import('../pages/admin/Collections.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/admin/pengguna',
            name: 'admin.users',
            component: () => import('../pages/admin/Users.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/admin/pesanan',
            name: 'admin.orders',
            component: () => import('../pages/admin/Orders.vue'),
            meta: { requiresAuth: true, requiresAdmin: true },
        },
        {
            path: '/postingan/:id',
            name: 'post.show',
            component: () => import('../pages/post/Show.vue'),
        },
        {
            path: '/produk/:slug',
            name: 'product.show',
            component: () => import('../pages/product/Show.vue'),
        },
        {
            path: '/cari',
            name: 'search',
            component: () => import('../pages/catalog/Search.vue'),
        },
        {
            path: '/kategori/:slug',
            name: 'category.show',
            component: () => import('../pages/catalog/Category.vue'),
        },
        {
            path: '/koleksi/:slug',
            name: 'collection.show',
            component: () => import('../pages/catalog/Collection.vue'),
        },
        {
            path: '/unduhan',
            name: 'unduhan',
            component: () => import('../pages/Placeholder.vue'),
            meta: { title: 'Unduhan', subtitle: 'Aplikasi yang sudah kamu pasang', emoji: '⬇️' },
        },
        {
            path: '/tersimpan',
            name: 'tersimpan',
            component: () => import('../pages/Placeholder.vue'),
            meta: { title: 'Tersimpan', subtitle: 'Postingan yang kamu simpan', emoji: '🔖' },
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: '/',
        },
    ],
    scrollBehavior(to, from, savedPosition) {
        return savedPosition ?? { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    await auth.init();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresDeveloper && !auth.isDeveloper) {
        return { name: 'account.developer', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresAdmin && !auth.isAdmin) {
        return { name: 'home' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }
});

export default router;
