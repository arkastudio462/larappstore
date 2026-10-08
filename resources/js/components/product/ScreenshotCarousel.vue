<script setup>
import { mediaUrl } from '@/lib/format';

defineProps({
    screenshots: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div v-if="screenshots.length" class="no-sb -mx-4 flex gap-3 overflow-x-auto px-4 pb-1">
        <img
            v-for="shot in screenshots"
            :key="shot.id ?? shot.path"
            :src="mediaUrl(shot.path)"
            :alt="`Tangkapan layar ${title}`"
            class="h-[210px] w-[120px] flex-none rounded-2xl border border-ln object-cover"
            loading="lazy"
        />
    </div>
</template>
