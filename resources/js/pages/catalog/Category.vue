<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ProductGrid from '@/components/product/ProductGrid.vue';
import Icon from '@/components/ui/Icon.vue';
import { useCatalogStore } from '@/stores/catalog';

const route = useRoute();
const router = useRouter();
const catalog = useCatalogStore();

const type = ref('all');
const sort = ref('newest');
const price = ref('all');

const types = [
    { value: 'all', label: 'Semua jenis' },
    { value: 'aplikasi', label: 'Aplikasi' },
    { value: 'game', label: 'Game' },
    { value: 'software', label: 'Software' },
    { value: 'file', label: 'File' },
];

const sorts = [
    { value: 'newest', label: 'Terbaru' },
    { value: 'popular', label: 'Terpopuler' },
    { value: 'rating', label: 'Rating tertinggi' },
    { value: 'price_low', label: 'Harga terendah' },
];

const prices = [
    { value: 'all', label: 'Semua harga' },
    { value: 'free', label: 'Gratis' },
    { value: 'paid', label: 'Berbayar' },
];

const slug = computed(() => String(route.params.slug));

const categoryName = computed(() => find(catalog.categories, slug.value)?.name ?? prettify(slug.value));

onMounted(() => {
    catalog.loadCategories();
    load();
});

watch([slug, type, sort, price], load);

function load() {
    const params = { category: slug.value, sort: sort.value };

    if (type.value !== 'all') {
        params.type = type.value;
    }

    if (price.value !== 'all') {
        params.price = price.value;
    }

    catalog.loadProducts(params);
}

function find(list, target) {
    for (const item of list ?? []) {
        if (item.slug === target) {
            return item;
        }

        const nested = find(item.children, target);

        if (nested) {
            return nested;
        }
    }

    return null;
}

function prettify(value) {
    return value.replace(/-/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <div class="min-w-0 flex-1">
                <b class="block truncate text-[15px]">{{ categoryName }}</b>
                <span class="text-xs text-mu">Kategori</span>
            </div>
        </header>

        <div class="no-sb mb-2 flex gap-2 overflow-x-auto">
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

        <div class="no-sb mb-3 flex gap-2 overflow-x-auto">
            <button
                v-for="item in sorts"
                :key="item.value"
                class="chip"
                type="button"
                :aria-pressed="sort === item.value"
                @click="sort = item.value"
            >
                {{ item.label }}
            </button>
            <button
                v-for="item in prices"
                :key="item.value"
                class="chip"
                type="button"
                :aria-pressed="price === item.value"
                @click="price = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <ProductGrid
            :products="catalog.products"
            :loading="catalog.productsLoading"
            empty-text="Belum ada aplikasi di kategori ini."
        />
    </div>
</template>
