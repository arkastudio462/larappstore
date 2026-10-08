<script setup>
import Icon from '@/components/ui/Icon.vue';

defineProps({
    avg: {
        type: Number,
        default: 0,
    },
    count: {
        type: Number,
        default: 0,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <span class="inline-flex items-center gap-1 text-xs font-bold text-acc">
        <Icon name="star" class="h-3.5! w-3.5!" />
        <template v-if="count > 0">
            <span>{{ avg.toFixed(1) }}</span>
            <span v-if="!compact" class="font-normal text-mu">({{ count }})</span>
        </template>
        <span v-else class="font-normal text-mu">Belum ada ulasan</span>
    </span>
</template>
