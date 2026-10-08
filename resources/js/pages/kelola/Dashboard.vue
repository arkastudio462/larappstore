<script setup>
import { onMounted } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import { relativeTime, rupiah } from '@/lib/format';
import { useDeveloperStore } from '@/stores/developer';

const developer = useDeveloperStore();

const links = [
    { name: 'kelola.products', emoji: '📦', label: 'Produk saya', hint: 'Ubah, terbitkan, arsipkan' },
    { name: 'kelola.versions', emoji: '🚀', label: 'Kelola versi', hint: 'Rilis versi baru produkmu' },
    { name: 'kelola.credits', emoji: '💳', label: 'Beli slot upload', hint: 'Rp15.000 / slot atau unlimited' },
];

onMounted(() => developer.loadOverview());
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4">
            <b class="block text-[15px]">Dashboard developer</b>
            <span class="text-xs text-mu">Kelola kredit, produk, dan rilis kamu.</span>
        </header>

        <div v-if="developer.overviewLoading" class="space-y-3" aria-hidden="true">
            <div class="h-24 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
            <div class="h-32 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
        </div>

        <template v-else-if="developer.overview">
            <section class="rounded-[22px] bg-ink p-4 text-on-ink">
                <span class="text-xs font-bold text-acc">Slot upload tersisa</span>
                <b class="mt-1 block text-3xl font-extrabold">
                    {{ developer.overview.unlimited_uploads ? '∞' : developer.overview.credits }}
                </b>
                <p class="mt-1 text-xs opacity-70">
                    {{
                        developer.overview.unlimited_uploads
                            ? 'Paket tanpa batas aktif — unggah sepuasnya.'
                            : `Harga slot tambahan ${rupiah(developer.overview.prices.upload_slot)} / aplikasi.`
                    }}
                </p>
                <RouterLink
                    v-if="!developer.overview.unlimited_uploads"
                    class="btn mt-3.5 bg-on-ink text-ink"
                    :to="{ name: 'kelola.credits' }"
                >
                    Beli slot
                </RouterLink>
            </section>

            <section class="mt-3 grid grid-cols-1 lg:grid-cols-3 overflow-hidden rounded-2xl border border-ln bg-sf">
                <div class="border-r border-ln px-3 py-3.5 text-center">
                    <b class="block text-[17px] font-extrabold">{{ developer.overview.products_count }}</b>
                    <span class="text-[11px] text-mu">Produk</span>
                </div>
                <div class="border-r border-ln px-3 py-3.5 text-center">
                    <b class="block text-[17px] font-extrabold">{{ developer.overview.published_count }}</b>
                    <span class="text-[11px] text-mu">Terbit</span>
                </div>
                <div class="px-3 py-3.5 text-center">
                    <b class="block text-[17px] font-extrabold">{{ developer.overview.downloads_count }}</b>
                    <span class="text-[11px] text-mu">Unduhan</span>
                </div>
            </section>

            <nav class="mt-4 space-y-2.5">
                <RouterLink
                    v-for="link in links"
                    :key="link.name"
                    class="flex items-center gap-3 rounded-[22px] border border-ln bg-sf p-3.5"
                    :to="{ name: link.name }"
                >
                    <span class="grid h-10 w-10 flex-none place-items-center rounded-2xl bg-sf2 text-[20px]" aria-hidden="true">
                        {{ link.emoji }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <b class="block text-sm">{{ link.label }}</b>
                        <span class="block text-xs text-mu">{{ link.hint }}</span>
                    </span>
                    <Icon name="back" class="rotate-180" />
                </RouterLink>
            </nav>

            <section class="mt-5">
                <h2 class="mb-2.5 text-[17px] font-extrabold">Riwayat kredit</h2>
                <div class="overflow-hidden rounded-2xl border border-ln bg-sf">
                    <div
                        v-for="entry in developer.overview.ledger"
                        :key="entry.id"
                        class="flex items-center gap-3 border-b border-ln px-3.5 py-3 last:border-b-0"
                    >
                        <span
                            class="w-10 flex-none text-sm font-extrabold"
                            :class="entry.delta < 0 ? 'text-ol' : 'text-acc'"
                        >
                            {{ entry.delta > 0 ? `+${entry.delta}` : entry.delta }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <b class="block truncate text-[13px]">{{ entry.description }}</b>
                            <span class="text-[11px] text-mu">
                                {{ relativeTime(entry.created_at) }} · saldo {{ entry.balance_after }}
                            </span>
                        </span>
                    </div>
                    <p
                        v-if="developer.overview.ledger.length === 0"
                        class="px-3.5 py-8 text-center text-xs text-mu"
                    >
                        Belum ada mutasi kredit.
                    </p>
                </div>
            </section>
        </template>
    </div>
</template>
