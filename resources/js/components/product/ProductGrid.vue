<script setup>
import ProductCard from '@/components/product/ProductCard.vue';

defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    emptyText: {
        type: String,
        default: 'Belum ada produk untuk ditampilkan.',
    },
});

const skeletons = [1, 2, 3, 4];
</script>

<template>
    <div v-if="loading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" aria-hidden="true">
        <div v-for="n in skeletons" :key="n" class="h-44 animate-pulse rounded-[20px] border border-ln bg-sf"></div>
    </div>

    <div v-else-if="products.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <ProductCard v-for="product in products" :key="product.id" :product="product" />
    </div>

    <p
        v-else
        class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
    >
        {{ emptyText }}
    </p>
</template>
