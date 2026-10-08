<script setup>
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
</script>

<template>
    <div
        role="alert"
        class="pointer-events-none absolute bottom-28 left-1/2 z-20 -translate-x-1/2 translate-y-5 whitespace-nowrap rounded-full bg-ink px-4 py-2 font-bold text-on-ink opacity-0 transition motion-reduce:transition-none"
        :class="{ 'translate-y-0 opacity-100': toast.visible }"
    >
        {{ toast.message }}
    </div>
</template>
