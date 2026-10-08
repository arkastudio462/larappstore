<script setup>
import { computed } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import { initials, relativeTime } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
    comment: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['reply', 'delete']);

const auth = useAuthStore();

const canDelete = computed(() => props.comment.author?.is_self === true || auth.isAdmin);
</script>

<template>
    <div class="flex gap-2.5" :class="compact ? 'ml-9 mt-2.5' : ''">
        <div
            class="grid h-8 w-8 flex-none place-items-center rounded-full bg-sf2 text-[12px] font-extrabold"
        >
            {{ initials(comment.author?.name) }}
        </div>

        <div class="min-w-0 flex-1">
            <div class="rounded-2xl border border-ln bg-bg px-3 py-2">
                <b class="block text-[13px]">{{ comment.author?.name }}</b>
                <p class="whitespace-pre-wrap break-words text-sm">{{ comment.body }}</p>
            </div>

            <div class="mt-1 flex items-center gap-3 text-xs text-mu">
                <span>{{ relativeTime(comment.created_at) }}</span>
                <button
                    v-if="!compact"
                    class="font-semibold"
                    type="button"
                    @click="emit('reply', comment)"
                >
                    Balas
                </button>
                <button
                    v-if="canDelete"
                    class="font-semibold text-ol"
                    type="button"
                    @click="emit('delete', comment)"
                >
                    Hapus
                </button>
            </div>
        </div>
    </div>
</template>
