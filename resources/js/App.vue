<script setup>
import BottomNav from '@/components/layout/BottomNav.vue';
import Composer from '@/components/feed/Composer.vue';
import Icon from '@/components/ui/Icon.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import { useAuthStore } from '@/stores/auth';
import { useComposerStore } from '@/stores/composer';

const auth = useAuthStore();
const composer = useComposerStore();
</script>

<template>
    <div id="app" class="relative h-[100dvh] w-full overflow-hidden bg-bg">
        <RouterView v-slot="{ Component }">
            <component :is="Component" class="view no-sb view-enter h-full overflow-y-auto" />
        </RouterView>

        <BottomNav v-if="auth.isAuthenticated" />
        <ToastHost />

        <div
            v-if="composer.open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center"
            @click.self="composer.hide()"
        >
            <div class="w-full max-w-xl">
                <div class="mb-2 flex items-center justify-between px-1">
                    <b class="text-[15px] text-white">Buat postingan</b>
                    <button class="ib" type="button" aria-label="Tutup" @click="composer.hide()">
                        <Icon name="back" />
                    </button>
                </div>
                <Composer @created="composer.hide()" />
            </div>
        </div>
    </div>
</template>
