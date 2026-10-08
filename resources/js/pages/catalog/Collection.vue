<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ProductGrid from '@/components/product/ProductGrid.vue';
import Icon from '@/components/ui/Icon.vue';
import { useCatalogStore } from '@/stores/catalog';

const route = useRoute();
const router = useRouter();
const catalog = useCatalogStore();

const error = ref('');

const slug = computed(() => String(route.params.slug));

onMounted(load);
watch(slug, load);

async function load() {
    error.value = '';
    const result = await catalog.loadCollection(slug.value);

    if (!result.ok) {
        error.value = result.message;
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <b class="min-w-0 flex-1 truncate text-[15px]">Koleksi</b>
        </header>

        <div
            v-if="error"
            class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
        >
            {{ error }}
        </div>

        <div v-else-if="catalog.collectionLoading" class="space-y-3" aria-hidden="true">
            <div class="h-20 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
            <div class="h-44 animate-pulse rounded-[20px] border border-ln bg-sf"></div>
        </div>

        <template v-else-if="catalog.collection">
            <h1 class="text-xl font-extrabold">{{ catalog.collection.name }}</h1>
            <p v-if="catalog.collection.description" class="mt-1.5 text-sm text-mu">
                {{ catalog.collection.description }}
            </p>

            <div class="mt-4">
                <ProductGrid
                    :products="catalog.collection.products ?? []"
                    empty-text="Koleksi ini masih kosong."
                />
            </div>
        </template>
    </div>
</template>
