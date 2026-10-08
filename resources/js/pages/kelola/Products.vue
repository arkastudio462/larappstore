<script setup>
import { onMounted } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import { rupiah } from '@/lib/format';
import { useDeveloperStore } from '@/stores/developer';
import { useToastStore } from '@/stores/toast';

const developer = useDeveloperStore();
const toast = useToastStore();

const statusLabels = {
    draft: 'Draft',
    published: 'Terbit',
    archived: 'Diarsipkan',
};

onMounted(() => developer.loadProducts());

async function toggle(product) {
    const result =
        product.status === 'published'
            ? await developer.archiveProduct(product.slug)
            : await developer.publishProduct(product.slug);

    toast.show(result.message);

    if (result.ok) {
        developer.loadProducts();
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <RouterLink class="ib" :to="{ name: 'kelola.dashboard' }" aria-label="Kembali">
                <Icon name="back" />
            </RouterLink>
            <b class="min-w-0 flex-1 text-[15px]">Produk saya</b>
            <RouterLink class="btn" :to="{ name: 'kelola.products.create' }">Produk baru</RouterLink>
        </header>

        <div v-if="developer.productsLoading" class="space-y-3" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="h-20 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
        </div>

        <template v-else>
            <article
                v-for="product in developer.products"
                :key="product.id"
                class="mb-3 rounded-[22px] border border-ln bg-sf p-3.5"
            >
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <b class="block truncate">{{ product.title }}</b>
                        <span class="block text-xs text-mu">
                            {{ product.category?.name ?? 'Tanpa kategori' }}
                            · {{ product.is_free ? 'Gratis' : rupiah(product.price) }}
                        </span>
                    </div>
                    <span class="chip" aria-hidden="true">{{ statusLabels[product.status] ?? product.status }}</span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <RouterLink
                        class="btn btn-ghost"
                        :to="{ name: 'kelola.products.edit', params: { slug: product.slug } }"
                    >
                        Ubah
                    </RouterLink>
                    <RouterLink
                        class="btn btn-ghost"
                        :to="{ name: 'kelola.versions', query: { produk: product.slug } }"
                    >
                        Versi
                    </RouterLink>
                    <button class="btn" type="button" @click="toggle(product)">
                        {{ product.status === 'published' ? 'Arsipkan' : 'Terbitkan' }}
                    </button>
                </div>
            </article>

            <p
                v-if="developer.products.length === 0"
                class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
            >
                Belum ada produk. Buat produk pertamamu.
            </p>
        </template>
    </div>
</template>
