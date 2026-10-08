<script setup>
import RatingBar from '@/components/product/RatingBar.vue';
import { mediaUrl, rupiah } from '@/lib/format';

defineProps({
    product: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <RouterLink
        class="flex flex-col rounded-[20px] border border-ln bg-sf p-2.5 transition active:scale-[0.98]"
        :to="`/produk/${product.slug}`"
    >
        <div
            class="mb-2 grid aspect-square place-items-center overflow-hidden rounded-2xl bg-sf2 text-[40px]"
            aria-hidden="true"
        >
            <img
                v-if="product.icon_path"
                :src="mediaUrl(product.icon_path)"
                alt=""
                class="h-full w-full object-cover"
                loading="lazy"
            />
            <span v-else>📦</span>
        </div>

        <b class="block truncate text-sm">{{ product.title }}</b>
        <span class="block truncate text-xs text-mu">{{ product.developer?.name ?? 'Developer' }}</span>

        <div class="mt-1.5 flex items-center justify-between gap-2">
            <RatingBar :avg="product.rating_avg ?? 0" :count="product.rating_count ?? 0" compact />
            <span class="flex-none text-xs font-bold">
                {{ product.is_free ? 'Gratis' : rupiah(product.price) }}
            </span>
        </div>
    </RouterLink>
</template>
