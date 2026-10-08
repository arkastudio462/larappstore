<script setup>
const links = [
    { name: 'admin.reports', label: 'Laporan' },
    { name: 'admin.stats', label: 'Statistik' },
    { name: 'admin.categories', label: 'Kategori' },
    { name: 'admin.collections', label: 'Koleksi' },
    { name: 'admin.users', label: 'Pengguna' },
    { name: 'admin.orders', label: 'Pesanan' },
];
</script>

<template>
    <nav class="no-sb mb-4 flex gap-2 overflow-x-auto" aria-label="Navigasi admin">
        <RouterLink
            v-for="link in links"
            :key="link.name"
            class="chip"
            active-class="bg-ink! text-on-ink!"
            :to="{ name: link.name }"
        >
            {{ link.label }}
        </RouterLink>
    </nav>
</template>
