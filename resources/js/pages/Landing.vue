<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import ProductCard from '@/components/product/ProductCard.vue';
import BannerCarousel from '@/components/catalog/BannerCarousel.vue';
import Icon from '@/components/ui/Icon.vue';
import { useCatalogStore } from '@/stores/catalog';
import { useThemeStore } from '@/stores/theme';
import { useToastStore } from '@/stores/toast';

const router = useRouter();
const theme = useThemeStore();
const toast = useToastStore();
const catalog = useCatalogStore();

const searchQuery = ref('');

onMounted(() => {
    catalog.loadProducts({ featured: 1, sort: 'popular', per_page: 8 });
    catalog.loadCategories();
});

const stats = [
    { value: '1.200+', label: 'Aplikasi' },
    { value: '480', label: 'Developer' },
    { value: '96 rb', label: 'Unduhan' },
];

const reasons = [
    {
        icon: 'download',
        title: 'Unduh tanpa ribet',
        text: 'Semua aplikasi gratis diunduh langsung, berbayar dibayar sekali lewat Midtrans.',
    },
    {
        icon: 'chat',
        title: 'Feed komunitas',
        text: 'Ikuti developer favoritmu, beri rating, dan diskusikan aplikasi di satu linimasa.',
    },
    {
        icon: 'plus',
        title: 'Publikasikan karya',
        text: 'Upgrade jadi developer secara gratis dan unggah aplikasi pertamamu hari ini.',
    },
];

function search() {
    const query = searchQuery.value.trim();

    router.push(query === '' ? { name: 'search' } : { name: 'search', query: { q: query } });
}
</script>

<template>
    <div class="container mx-auto px-4 pt-4">
        <header class="flex items-center gap-2.5">
            <RouterLink to="/" class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-ink text-lg font-extrabold text-on-ink">
                A
            </RouterLink>
            <b class="min-w-0 flex-1 text-[15px]">AppFeed</b>
            <button class="ib" type="button" aria-label="Ganti tema" @click="theme.toggle()">
                <Icon :name="theme.isLight ? 'moon' : 'sun'" />
            </button>
            <RouterLink class="btn btn-ghost" :to="{ name: 'login' }">Masuk</RouterLink>
            <RouterLink class="btn" :to="{ name: 'register' }">Daftar</RouterLink>
        </header>

        <section class="mt-9 grid items-center gap-6 lg:grid-cols-2">
            <div>
                <span class="chip" aria-hidden="true">Toko aplikasi + feed komunitas</span>
                <h1 class="mb-2 mt-4 text-[34px] font-extrabold leading-[1.05] tracking-tight sm:text-5xl">
                    Unduh aplikasi.<br />Ikuti kreatornya.
                </h1>
                <p class="max-w-[420px] text-sm text-mu">
                    Temukan aplikasi buatan developer lokal, baca ulasan nyata, dan ikuti kabar terbaru
                    dari orang-orang yang membangunnya.
                </p>
                <form class="mt-5 flex gap-2.5" @submit.prevent="search">
                    <label class="flex h-11 flex-1 items-center gap-2 rounded-2xl border border-ln bg-sf px-3 text-mu">
                        <Icon name="search" />
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari aplikasi atau developer"
                            aria-label="Cari aplikasi atau developer"
                            class="min-w-0 flex-1 bg-transparent text-tx outline-none placeholder:text-mu"
                        />
                    </label>
                    <button class="grid h-11 w-11 place-items-center rounded-xl bg-ink text-on-ink" type="submit" aria-label="Cari">
                        <Icon name="search" />
                    </button>
                </form>
                <div class="mt-3 flex gap-2.5">
                    <RouterLink class="btn flex-1 text-center sm:flex-none sm:px-8" :to="{ name: 'register' }">Daftar gratis</RouterLink>
                    <RouterLink class="btn btn-ghost flex-1 text-center sm:flex-none sm:px-8" :to="{ name: 'login' }">Sudah punya akun</RouterLink>
                </div>
            </div>
            <BannerCarousel :products="catalog.products" />
        </section>

        <dl class="mt-7 grid grid-cols-3 gap-3 overflow-hidden rounded-2xl border border-ln bg-sf">
            <div v-for="s in stats" :key="s.label" class="border-r border-ln px-3 py-3.5 text-center last:border-r-0">
                <dt class="text-[17px] font-extrabold">{{ s.value }}</dt>
                <dd class="text-[11px] text-mu">{{ s.label }}</dd>
            </div>
        </dl>

        <section class="mt-7">
            <div class="mb-2.5 flex items-baseline justify-between">
                <h2 class="text-[17px] font-extrabold">Populer minggu ini</h2>
                <RouterLink class="text-xs text-mu" :to="{ name: 'search' }">Lihat semua</RouterLink>
            </div>
            <div v-if="catalog.products.length" class="no-sb flex gap-3 overflow-x-auto pb-1 lg:grid lg:grid-cols-4 lg:overflow-visible">
                <div v-for="product in catalog.products" :key="product.id" class="w-[150px] flex-none lg:w-auto">
                    <ProductCard :product="product" />
                </div>
            </div>
            <p v-else class="rounded-2xl border border-ln bg-sf px-3.5 py-6 text-center text-xs text-mu">
                Belum ada aplikasi terbit. Jadilah developer pertama!
            </p>
        </section>

        <section class="mt-7">
            <h2 class="mb-2.5 text-[17px] font-extrabold">Kenapa AppFeed</h2>
            <ul class="overflow-hidden rounded-2xl border border-ln bg-sf">
                <li
                    v-for="r in reasons"
                    :key="r.title"
                    class="flex gap-3 border-b border-ln px-3.5 py-3.5 last:border-b-0"
                >
                    <span class="ib h-9 w-9 rounded-xl" aria-hidden="true">
                        <Icon :name="r.icon" />
                    </span>
                    <span class="min-w-0">
                        <b class="block text-sm">{{ r.title }}</b>
                        <span class="block text-xs text-mu">{{ r.text }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <section class="mt-7">
            <h2 class="mb-2.5 text-[17px] font-extrabold">Kategori</h2>
            <div class="no-sb flex flex-wrap gap-2">
                <RouterLink
                    v-for="category in catalog.categories"
                    :key="category.slug"
                    class="chip"
                    :to="{ name: 'category.show', params: { slug: category.slug } }"
                >
                    {{ category.name }}
                </RouterLink>
                <RouterLink
                    v-if="catalog.categoriesLoaded && catalog.categories.length === 0"
                    class="chip"
                    :to="{ name: 'search' }"
                >
                    Lihat semua aplikasi
                </RouterLink>
            </div>
        </section>

        <section class="mt-7 rounded-3xl bg-ink p-[18px] text-on-ink">
            <h2 class="max-w-[240px] text-2xl font-extrabold leading-tight">Punya aplikasi? Publikasikan sekarang.</h2>
            <p class="mb-3 mt-1 max-w-[260px] text-[12.5px] opacity-70">
                Satu unduhan pertama gratis. Setelah itu tinggal beli slot upload sesuai kebutuhan.
            </p>
            <RouterLink class="btn bg-on-ink text-ink" :to="{ name: 'register' }">Mulai jadi developer</RouterLink>
        </section>

        <footer class="mt-7 border-t border-ln pt-4 text-xs text-mu">
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                <a href="#" @click.prevent="toast.show('Tentang AppFeed')">Tentang</a>
                <a href="#" @click.prevent="toast.show('Panduan developer')">Panduan developer</a>
                <a href="#" @click.prevent="toast.show('Kebijakan privasi')">Privasi</a>
                <a href="#" @click.prevent="toast.show('Hubungi kami')">Kontak</a>
            </div>
            <p class="mt-3">© 2026 AppFeed. Dibuat di Indonesia.</p>
        </footer>
    </div>
</template>
