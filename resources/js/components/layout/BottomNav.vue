<script setup>
import Icon from '@/components/ui/Icon.vue';
import { initials, mediaUrl } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useComposerStore } from '@/stores/composer';
import { useThemeStore } from '@/stores/theme';
import { useRoute } from 'vue-router';

const theme = useThemeStore();
const auth = useAuthStore();
const composer = useComposerStore();
const route = useRoute();

function isActive(path) {
    return route.path === path;
}

function isActiveName(name) {
    return route.name === name;
}
</script>

<template>
    <nav
        class="absolute inset-x-0 bottom-0 z-10 border-t border-ln bg-nav lg:inset-x-auto lg:inset-y-0 lg:flex lg:w-64 lg:flex-col lg:border-r lg:border-t-0"
        aria-label="Navigasi utama"
    >
        <RouterLink to="/" class="hidden items-center gap-2.5 px-5 pb-5 pt-6 lg:flex">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-ink text-sm font-extrabold text-on-ink">A</span>
            <b class="text-[15px]">AppFeed</b>
        </RouterLink>

        <div
            class="mx-auto flex h-[84px] w-full max-w-5xl items-start justify-around px-4 pt-2.5 lg:mx-0 lg:h-auto lg:max-w-none lg:flex-1 lg:flex-col lg:justify-start lg:gap-1 lg:px-3 lg:pt-0"
        >
            <RouterLink class="nav-i" :class="{ 'nav-i--active': isActive('/') }" to="/">
                <Icon name="home" />
                Beranda
            </RouterLink>

            <RouterLink class="nav-i" :class="{ 'nav-i--active': isActive('/unduhan') }" to="/unduhan">
                <Icon name="download" />
                Unduhan
            </RouterLink>

            <button
                class="-mt-6 grid h-[58px] w-[58px] flex-none place-items-center rounded-full bg-ink text-on-ink shadow-[0_0_0_6px_var(--bg)] lg:order-first lg:mb-3 lg:mt-0 lg:h-11 lg:w-full lg:rounded-xl lg:shadow-none"
                type="button"
                aria-label="Buat postingan"
                @click="composer.show()"
            >
                <Icon name="plus" />
            </button>

            <RouterLink class="nav-i" :class="{ 'nav-i--active': isActive('/tersimpan') }" to="/tersimpan">
                <Icon name="bookmark" />
                Tersimpan
            </RouterLink>

            <div class="hidden lg:contents">
                <RouterLink class="nav-i" :class="{ 'nav-i--active': isActiveName('account.notifications') }" :to="{ name: 'account.notifications' }">
                    <Icon name="bell" />
                    Notifikasi
                </RouterLink>
            </div>

            <RouterLink class="nav-i" :class="{ 'nav-i--active': isActive('/akun/profil') }" to="/akun/profil">
                <Icon name="user" />
                Profil
            </RouterLink>
        </div>

        <div class="hidden items-center gap-3 border-t border-ln px-4 pb-4 pt-3 lg:flex">
            <RouterLink
                class="grid h-10 w-10 flex-none place-items-center overflow-hidden rounded-full bg-ink font-extrabold text-on-ink"
                :to="{ name: 'account.profile' }"
            >
                <img v-if="mediaUrl(auth.user?.avatar_path)" :src="mediaUrl(auth.user?.avatar_path)" alt="" class="h-full w-full object-cover" />
                <template v-else>{{ initials(auth.user?.name) }}</template>
            </RouterLink>
            <div class="min-w-0 flex-1">
                <b class="block truncate text-sm">{{ auth.user?.name ?? 'Kamu' }}</b>
                <span class="block truncate text-[11px] text-mu">{{ auth.user?.email ?? '' }}</span>
            </div>
            <button class="ib" type="button" aria-label="Ganti tema" @click="theme.toggle()">
                <Icon :name="theme.isLight ? 'moon' : 'sun'" />
            </button>
        </div>
    </nav>
</template>
