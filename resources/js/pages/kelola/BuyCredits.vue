<script setup>
import { computed, onMounted, ref } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import { rupiah } from '@/lib/format';
import { useDeveloperStore } from '@/stores/developer';
import { useToastStore } from '@/stores/toast';

const developer = useDeveloperStore();
const toast = useToastStore();

const mode = ref('slots');
const quantity = ref(1);
const busy = ref(false);
const payment = ref(null);

const prices = computed(() => developer.overview?.prices ?? { upload_slot: 15000, unlimited: 150000 });

const total = computed(() =>
    mode.value === 'unlimited' ? prices.value.unlimited : quantity.value * prices.value.upload_slot,
);

onMounted(() => developer.loadOverview());

async function submit() {
    if (busy.value) {
        return;
    }

    busy.value = true;

    const payload =
        mode.value === 'unlimited'
            ? { type: 'unlimited_upload' }
            : { type: 'upload_slots', quantity: Number(quantity.value) };

    const result = await developer.createOrder(payload);

    busy.value = false;
    toast.show(result.message);

    if (result.ok) {
        payment.value = result.payment;
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <RouterLink class="ib" :to="{ name: 'kelola.dashboard' }" aria-label="Kembali">
                <Icon name="back" />
            </RouterLink>
            <b class="text-[15px]">Beli slot upload</b>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
            <button
                class="rounded-[22px] border p-3.5 text-left"
                :class="mode === 'slots' ? 'border-ol bg-sf2' : 'border-ln bg-sf'"
                type="button"
                :aria-pressed="mode === 'slots'"
                @click="mode = 'slots'"
            >
                <b class="block text-sm">Slot tambahan</b>
                <span class="mt-1 block text-xs text-mu">{{ rupiah(prices.upload_slot) }} / aplikasi</span>
            </button>
            <button
                class="rounded-[22px] border p-3.5 text-left"
                :class="mode === 'unlimited' ? 'border-ol bg-sf2' : 'border-ln bg-sf'"
                type="button"
                :aria-pressed="mode === 'unlimited'"
                @click="mode = 'unlimited'"
            >
                <b class="block text-sm">Unlimited</b>
                <span class="mt-1 block text-xs text-mu">{{ rupiah(prices.unlimited) }} sekali bayar</span>
            </button>
        </div>

        <section v-if="mode === 'slots'" class="mt-3.5 rounded-[22px] border border-ln bg-sf p-3.5">
            <label class="block">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Jumlah slot</span>
                <div class="flex items-center gap-2.5">
                    <button
                        class="ib"
                        type="button"
                        aria-label="Kurangi"
                        @click="quantity = Math.max(1, quantity - 1)"
                    >
                        <Icon name="back" class="rotate-90" />
                    </button>
                    <input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        max="50"
                        class="h-11 w-20 rounded-2xl border border-ln bg-bg px-3 text-center text-tx outline-none"
                    />
                    <button
                        class="ib"
                        type="button"
                        aria-label="Tambah"
                        @click="quantity = Math.min(50, quantity + 1)"
                    >
                        <Icon name="back" class="-rotate-90" />
                    </button>
                    <span class="ml-auto text-xs text-mu">maks 50</span>
                </div>
            </label>
        </section>

        <p v-else class="mt-3.5 rounded-[22px] border border-ln bg-sf p-3.5 text-xs text-mu">
            Sekali bayar {{ rupiah(prices.unlimited) }}, unggah aplikasi sepuasnya tanpa menghitung kredit.
        </p>

        <div class="mt-3.5 flex items-center justify-between rounded-[22px] bg-ink p-4 text-on-ink">
            <span class="text-sm font-bold">Total</span>
            <b class="text-xl font-extrabold">{{ rupiah(total) }}</b>
        </div>

        <button class="btn mt-3.5 w-full py-3! text-sm" type="button" :disabled="busy" @click="submit">
            {{ busy ? 'Memproses…' : 'Lanjutkan pembayaran' }}
        </button>

        <section v-if="payment" class="mt-3.5 rounded-[22px] border border-ln bg-sf p-3.5">
            <b class="block text-sm">Pesanan dibuat</b>
            <p class="mt-1 text-xs text-mu">
                Selesaikan pembayaran untuk mengaktifkan kredit. Kredit masuk otomatis setelah pembayaran
                terkonfirmasi.
            </p>
            <a
                v-if="payment.redirect_url"
                class="btn mt-3 inline-block"
                :href="payment.redirect_url"
                target="_blank"
                rel="noopener noreferrer"
            >
                Buka halaman pembayaran
            </a>
            <p v-else class="mt-2 text-xs text-mu">Token pembayaran: {{ payment.token }}</p>
        </section>
    </div>
</template>
