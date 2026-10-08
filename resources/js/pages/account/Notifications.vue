<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '@/components/ui/Icon.vue';
import { initials, relativeTime } from '@/lib/format';
import { useNotificationStore } from '@/stores/notifications';
import { useToastStore } from '@/stores/toast';

const notifications = useNotificationStore();
const toast = useToastStore();
const router = useRouter();

onMounted(() => {
    notifications.load();
});

async function markAllRead() {
    const result = await notifications.markAllRead();
    toast.show(result.message);
}

function open(item) {
    if (item.url) {
        router.push(item.url);
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <div class="min-w-0 flex-1">
                <b class="block text-[15px]">Notifikasi</b>
                <span class="text-xs text-mu">
                    {{ notifications.unreadCount }} belum dibaca
                </span>
            </div>
            <button
                v-if="notifications.unreadCount > 0"
                class="btn btn-ghost"
                type="button"
                @click="markAllRead"
            >
                Tandai semua
            </button>
        </header>

        <div v-if="notifications.loading" class="space-y-3" aria-hidden="true">
            <div v-for="n in 4" :key="n" class="h-16 animate-pulse rounded-2xl border border-ln bg-sf"></div>
        </div>

        <template v-else>
            <button
                v-for="item in notifications.items"
                :key="item.id"
                class="mb-2.5 flex w-full items-center gap-3 rounded-[22px] border border-ln p-3.5 text-left"
                :class="item.read ? 'bg-sf' : 'bg-sf2'"
                type="button"
                @click="open(item)"
            >
                <div
                    class="grid h-10 w-10 flex-none place-items-center rounded-full bg-ink text-[13px] font-extrabold text-on-ink"
                >
                    {{ initials(item.actor?.name ?? 'A') }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="break-words text-sm">{{ item.message }}</p>
                    <span class="text-xs text-mu">{{ relativeTime(item.created_at) }}</span>
                </div>
                <span v-if="!item.read" class="h-2 w-2 flex-none rounded-full bg-ol" aria-label="Belum dibaca"></span>
            </button>

            <p
                v-if="notifications.items.length === 0"
                class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
            >
                Belum ada notifikasi.
            </p>
        </template>
    </div>
</template>
