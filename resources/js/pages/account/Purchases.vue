<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import ProductGrid from '@/components/product/ProductGrid.vue';
import Icon from '@/components/ui/Icon.vue';
import { useCatalogStore } from '@/stores/catalog';

const catalog = useCatalogStore();
const router = useRouter();

onMounted(() => catalog.loadPurchases());
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <div class="min-w-0 flex-1">
                <b class="block text-[15px]">Pembelian saya</b>
                <span class="text-xs text-mu">Produk berbayar yang sudah kamu miliki.</span>
            </div>
        </header>

        <ProductGrid
            :products="catalog.purchases.map((purchase) => purchase.product).filter(Boolean)"
            :loading="catalog.purchasesLoading"
            empty-text="Belum ada pembelian. Produk berbayar yang kamu beli akan muncul di sini."
        />
    </div>
</template>
