<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import { relativeTime, rupiah } from '@/lib/format';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

const status = ref('');

const statuses = [
    { value: '', label: 'Semua' },
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Lunas' },
    { value: 'failed', label: 'Gagal' },
    { value: 'expired', label: 'Kedaluwarsa' },
    { value: 'cancelled', label: 'Batal' },
];

const statusLabels = {
    pending: 'Pending',
    paid: 'Lunas',
    failed: 'Gagal',
    expired: 'Kedaluwarsa',
    cancelled: 'Batal',
};

onMounted(() => admin.loadOrders(status.value));
watch(status, (value) => admin.loadOrders(value));
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5">
            <b class="block text-[15px]">Pesanan</b>
            <span class="text-xs text-mu">Riwayat transaksi seluruh pengguna.</span>
        </header>

        <AdminNav />

        <div class="no-sb mb-3 flex gap-2 overflow-x-auto">
            <button
                v-for="item in statuses"
                :key="item.value"
                class="chip"
                type="button"
                :aria-pressed="status === item.value"
                @click="status = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <div v-if="admin.ordersLoading" class="space-y-3" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="h-20 animate-pulse rounded-2xl border border-ln bg-sf"></div>
        </div>

        <div v-else class="space-y-2.5">
            <article
                v-for="order in admin.orders"
                :key="order.order_no"
                class="rounded-2xl border border-ln bg-sf p-3.5"
            >
                <div class="flex items-center gap-2">
                    <b class="min-w-0 flex-1 truncate text-sm">{{ order.order_no }}</b>
                    <span class="chip" aria-hidden="true">{{ statusLabels[order.status] ?? order.status }}</span>
                </div>

                <span class="mt-1 block text-xs text-mu">
                    {{ order.user?.name ?? 'Pengguna' }} · {{ order.type }}
                </span>

                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs text-mu">
                        {{ order.items.length }} item · {{ relativeTime(order.created_at) }}
                    </span>
                    <b class="text-sm font-extrabold">{{ rupiah(order.gross_amount) }}</b>
                </div>
            </article>

            <p
                v-if="admin.orders.length === 0"
                class="rounded-2xl border border-ln bg-sf px-4 py-8 text-center text-sm text-mu"
            >
                Belum ada pesanan pada status ini.
            </p>
        </div>
    </div>
</template>
