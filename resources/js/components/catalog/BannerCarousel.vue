<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { mediaUrl } from '@/lib/format';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
});

const slides = computed(() => props.products.slice(0, 5));
const index = ref(0);
let timer = null;

function go(i) {
    if (!slides.value.length) {
        return;
    }

    index.value = (i + slides.value.length) % slides.value.length;
}

function start() {
    stop();
    timer = setInterval(() => go(index.value + 1), 4000);
}

function stop() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

onMounted(start);
onBeforeUnmount(stop);
</script>

<template>
    <div v-if="slides.length" class="relative" @mouseenter="stop" @mouseleave="start">
        <RouterLink
            v-for="(product, i) in slides"
            v-show="i === index"
            :key="product.id"
            :to="`/produk/${product.slug}`"
            class="relative flex h-44 items-center gap-4 overflow-hidden rounded-3xl border border-ln bg-sf p-5 sm:h-56"
        >
            <div class="grid h-20 w-20 flex-none place-items-center overflow-hidden rounded-2xl bg-sf2 text-4xl sm:h-28 sm:w-28" aria-hidden="true">
                <img v-if="product.icon_path" :src="mediaUrl(product.icon_path)" alt="" class="h-full w-full object-cover" />
                <span v-else>📦</span>
            </div>
            <div class="min-w-0 flex-1">
                <span class="chip">Unggulan</span>
                <b class="mt-2 block truncate text-lg sm:text-xl">{{ product.title }}</b>
                <span class="block truncate text-xs text-mu">{{ product.developer?.name ?? 'Developer' }}</span>
                <span class="mt-2 inline-block text-xs font-bold underline underline-offset-2">Lihat aplikasi →</span>
            </div>
        </RouterLink>

        <div v-if="slides.length > 1" class="mt-3 flex items-center justify-center gap-1.5">
            <button
                v-for="(_, i) in slides"
                :key="i"
                type="button"
                class="h-1.5 rounded-full transition-all"
                :class="i === index ? 'w-6 bg-ink' : 'w-1.5 bg-ln'"
                :aria-label="`Banner ${i + 1}`"
                @click="go(i)"
            ></button>
        </div>
    </div>
</template>
