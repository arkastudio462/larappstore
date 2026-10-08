<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ProductGrid from '@/components/product/ProductGrid.vue';
import Icon from '@/components/ui/Icon.vue';
import { useCatalogStore } from '@/stores/catalog';

const route = useRoute();
const router = useRouter();
const catalog = useCatalogStore();

const q = ref(String(route.query.q ?? ''));

const emptyText = computed(() =>
    q.value.trim() === ''
        ? 'Ketik kata kunci untuk mencari aplikasi, game, atau software.'
        : 'Tidak ada aplikasi yang cocok dengan pencarianmu.',
);

onMounted(run);

watch(
    () => route.query.q,
    (value) => {
        q.value = String(value ?? '');
        run();
    },
);

function run() {
    const query = q.value.trim();

    if (query === '') {
        catalog.searchResults = [];
        catalog.searchMeta = null;

        return;
    }

    catalog.search(query);
}

function submit() {
    const query = q.value.trim();
    router.push(query === '' ? { name: 'search' } : { name: 'search', query: { q: query } });
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <b class="text-[15px]">Cari aplikasi</b>
        </header>

        <form class="flex gap-2.5" @submit.prevent="submit">
            <label class="flex h-11 flex-1 items-center gap-2 rounded-2xl border border-ln bg-sf px-3 text-mu">
                <Icon name="search" />
                <input
                    v-model="q"
                    type="search"
                    placeholder="Nama aplikasi, kata kunci, atau developer"
                    aria-label="Cari aplikasi"
                    class="min-w-0 flex-1 bg-transparent text-tx outline-none placeholder:text-mu"
                />
            </label>
            <button
                class="grid h-11 w-11 flex-none place-items-center rounded-xl bg-ink text-on-ink"
                type="submit"
                aria-label="Cari"
            >
                <Icon name="search" />
            </button>
        </form>

        <p v-if="catalog.searchMeta && catalog.searchMeta.total > 0" class="mb-3 mt-3.5 text-xs text-mu">
            {{ catalog.searchMeta.total }} hasil untuk "{{ catalog.searchMeta.query }}"
        </p>

        <div class="mt-3.5">
            <ProductGrid
                :products="catalog.searchResults"
                :loading="catalog.searchLoading"
                :empty-text="emptyText"
            />
        </div>
    </div>
</template>
