<script setup>
import { onMounted } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import { rupiah } from '@/lib/format';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

onMounted(() => admin.loadStats());
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5">
            <b class="block text-[15px]">Statistik platform</b>
            <span class="text-xs text-mu">Ringkasan aktivitas AppFeed.</span>
        </header>

        <AdminNav />

        <div v-if="admin.statsLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" aria-hidden="true">
            <div v-for="n in 6" :key="n" class="h-20 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
        </div>

        <template v-else-if="admin.stats">
            <section class="rounded-[22px] bg-ink p-4 text-on-ink">
                <span class="text-xs font-bold text-acc">Pendapatan (pesanan lunas)</span>
                <b class="mt-1 block text-2xl font-extrabold">{{ rupiah(admin.stats.revenue) }}</b>
                <p class="mt-1 text-xs opacity-70">
                    {{ admin.stats.paid_orders }} dari {{ admin.stats.orders }} pesanan berhasil dibayar.
                </p>
            </section>

            <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                    v-for="card in [
                        { label: 'Pengguna', value: admin.stats.users },
                        { label: 'Developer', value: admin.stats.developers },
                        { label: 'Diblokir', value: admin.stats.banned },
                        { label: 'Pengguna baru (7 hari)', value: admin.stats.new_users_7d },
                        { label: 'Postingan', value: admin.stats.posts },
                        { label: 'Komentar', value: admin.stats.comments },
                        { label: 'Produk', value: admin.stats.products },
                        { label: 'Produk terbit', value: admin.stats.published_products },
                        { label: 'Ulasan', value: admin.stats.reviews },
                        { label: 'Unduhan', value: admin.stats.downloads },
                        { label: 'Pesanan', value: admin.stats.orders },
                        { label: 'Laporan terbuka', value: admin.stats.open_reports },
                    ]"
                    :key="card.label"
                    class="rounded-2xl border border-ln bg-sf px-3.5 py-3"
                >
                    <dt class="text-[11px] text-mu">{{ card.label }}</dt>
                    <dd class="text-lg font-extrabold">{{ card.value }}</dd>
                </div>
            </dl>
        </template>
    </div>
</template>
