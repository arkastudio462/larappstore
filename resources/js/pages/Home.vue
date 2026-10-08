<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import PostCard from '@/components/feed/PostCard.vue';
import BannerCarousel from '@/components/catalog/BannerCarousel.vue';
import Icon from '@/components/ui/Icon.vue';
import { initials, mediaUrl, rupiah } from '@/lib/format';
import Landing from '@/pages/Landing.vue';
import { useAuthStore } from '@/stores/auth';
import { useCatalogStore } from '@/stores/catalog';
import { useFeedStore } from '@/stores/feed';
import { useNotificationStore } from '@/stores/notifications';
import { useThemeStore } from '@/stores/theme';
import { useToastStore } from '@/stores/toast';

const auth = useAuthStore();
const feed = useFeedStore();
const catalog = useCatalogStore();
const notifications = useNotificationStore();
const theme = useThemeStore();
const toast = useToastStore();

const scope = ref('all');
const type = ref('all');

const tabs = [
    { value: 'all', label: 'Untuk kamu' },
    { value: 'following', label: 'Mengikuti' },
];

const types = [
    { value: 'all', label: 'Semua' },
    { value: 'post', label: 'Postingan' },
    { value: 'product', label: 'Aplikasi' },
];

const greeting = computed(() => `Halo, ${(auth.user?.name ?? 'kamu').split(' ')[0]}`);

onMounted(() => {
    if (auth.isAuthenticated) {
        feed.load({ scope: scope.value, type: type.value });
        notifications.load();
        catalog.loadCategories();
        catalog.loadProducts({ featured: 1, sort: 'popular', per_page: 8 });
    }
});

watch([scope, type], () => {
    feed.load({ scope: scope.value, type: type.value });
});

async function loadMore() {
    const result = await feed.loadMore();

    if (!result.ok) {
        toast.show(result.message);
    }
}

function emptyHint() {
    if (scope.value === 'following') {
        return 'Kamu belum mengikuti siapa pun. Ikuti beberapa developer untuk melihat aktivitas mereka di sini.';
    }

    return 'Belum ada aktivitas. Jadilah yang pertama membagikan kabar.';
}
</script>

<template>
    <Landing v-if="!auth.isAuthenticated" />

    <div v-else class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8 lg:pr-8">
      <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-8">
      <div class="min-w-0">
        <div class="mb-4 lg:mt-0">
            <BannerCarousel :products="catalog.products" />
        </div>

        <header class="flex items-center gap-2.5 lg:hidden">
            <RouterLink
                class="grid h-10 w-10 flex-none place-items-center overflow-hidden rounded-full bg-ink font-extrabold text-on-ink"
                :to="{ name: 'account.profile' }"
            >
                <img v-if="mediaUrl(auth.user?.avatar_path)" :src="mediaUrl(auth.user?.avatar_path)" alt="" class="h-full w-full object-cover" />
                <template v-else>{{ initials(auth.user?.name) }}</template>
            </RouterLink>
            <div class="min-w-0 flex-1">
                <b class="block truncate text-[15px]">{{ greeting }}</b>
                <span class="text-xs text-mu">Apa yang mau kamu bagikan hari ini?</span>
            </div>
            <div class="flex gap-2 lg:hidden">
                <button class="ib" type="button" aria-label="Ganti tema" @click="theme.toggle()">
                    <Icon :name="theme.isLight ? 'moon' : 'sun'" />
                </button>
                <RouterLink class="ib relative" :to="{ name: 'account.notifications' }" aria-label="Notifikasi">
                    <Icon name="bell" />
                    <span
                        v-if="notifications.unreadCount > 0"
                        class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-ol px-1 text-[10px] font-bold text-bg"
                    >
                        {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
                    </span>
                </RouterLink>
            </div>
        </header>

        <div class="mb-3.5 mt-4 flex gap-1.5" role="group" aria-label="Cakupan feed">
            <button
                v-for="item in tabs"
                :key="item.value"
                class="tab"
                type="button"
                :aria-pressed="scope === item.value"
                @click="scope = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="no-sb mb-3 flex gap-2 overflow-x-auto" role="group" aria-label="Jenis konten">
            <button
                v-for="item in types"
                :key="item.value"
                class="chip"
                type="button"
                :aria-pressed="type === item.value"
                @click="type = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <div v-if="feed.loading" class="space-y-3" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="h-32 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
        </div>

        <template v-else>
            <template v-for="item in feed.items" :key="item.id">
                <PostCard
                    v-if="item.post"
                    :post="item.post"
                    @removed="feed.items = feed.items.filter((row) => row.id !== item.id)"
                />
                <RouterLink
                    v-else-if="item.product"
                    class="mb-3 flex items-center gap-3 rounded-[22px] border border-ln bg-sf p-3.5"
                    :to="`/produk/${item.product.slug}`"
                >
                    <div
                        class="grid h-[52px] w-[52px] flex-none place-items-center rounded-2xl bg-sf2 text-[26px]"
                        aria-hidden="true"
                    >
                        🚀
                    </div>
                    <div class="min-w-0 flex-1">
                        <b class="block truncate">{{ item.product.title }}</b>
                        <span class="block truncate text-xs text-mu">
                            {{ item.product.developer?.name }}
                            · {{ item.product.is_free ? 'Gratis' : rupiah(item.product.price) }}
                            · {{ item.product.downloads_count }} unduhan
                        </span>
                    </div>
                    <span class="rounded-full bg-sf2 px-2.5 py-0.5 text-[11px] font-bold text-acc">Rilis</span>
                </RouterLink>
            </template>

            <p v-if="feed.items.length === 0" class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu">
                {{ emptyHint() }}
            </p>
        </template>

        <button
            v-if="feed.hasMore"
            class="btn btn-ghost mt-1 w-full py-3!"
            type="button"
            :disabled="feed.loadingMore"
            @click="loadMore"
        >
            {{ feed.loadingMore ? 'Memuat…' : 'Muat lebih banyak' }}
        </button>
      </div>

      <aside class="mt-6 hidden lg:mt-0 lg:block">
        <div class="sticky top-4 rounded-2xl border border-ln bg-sf p-4">
            <h2 class="text-sm font-extrabold">Jelajahi kategori</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <RouterLink
                    v-for="category in catalog.categories"
                    :key="category.slug"
                    class="chip"
                    :to="{ name: 'category.show', params: { slug: category.slug } }"
                >
                    {{ category.name }}
                </RouterLink>
            </div>
        </div>
      </aside>
      </div>
    </div>
</template>
