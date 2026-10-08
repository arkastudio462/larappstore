<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();
</script>

<template>
    <div class="w-full px-4 pt-4">
        <header class="mb-4 flex items-center gap-2.5">
            <div class="min-w-0 flex-1">
                <b class="block text-[15px]">{{ route.meta.title }}</b>
                <span class="text-xs text-mu">{{ route.meta.subtitle }}</span>
            </div>
        </header>

        <div class="grid place-items-center rounded-[22px] border border-ln bg-sf px-4 py-14 text-center">
            <div class="mb-3 grid h-14 w-14 place-items-center rounded-full bg-sf2 text-[26px]" aria-hidden="true">
                {{ route.meta.emoji }}
            </div>
            <b class="text-[15px]">{{ route.meta.title }} belum tersedia</b>
            <p class="mt-1 max-w-xs text-xs text-mu">
                Halaman ini akan diisi pada milestone berikutnya.
            </p>
        </div>
    </div>
</template>
