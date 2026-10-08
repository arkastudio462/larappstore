<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PostCard from '@/components/feed/PostCard.vue';
import ProductGrid from '@/components/product/ProductGrid.vue';
import { api, errorMessage } from '@/lib/api';
import { initials, mediaUrl, relativeTime, roleLabel } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const profile = ref(null);
const tab = ref('posts');
const items = ref([]);
const loading = ref(false);
const busy = ref(false);

const tabs = [
    { key: 'posts', label: 'Postingan' },
    { key: 'products', label: 'Aplikasi' },
    { key: 'followers', label: 'Pengikut' },
];

const isSelf = computed(() => profile.value?.is_self === true);

const cardPosts = computed(() =>
    items.value.map((post) => ({
        id: post.id,
        who: post.author?.name ?? profile.value?.name ?? 'Pengguna',
        ini: initials(post.author?.name ?? profile.value?.name),
        role: roleLabel(post.author?.role),
        time: relativeTime(post.published_at ?? post.created_at),
        likes: post.likes_count,
        comments: post.comments_count,
        text: post.body,
    })),
);

async function loadProfile() {
    try {
        const { data } = await api.get(`/users/${encodeURIComponent(route.params.username)}`);
        profile.value = data.data;
    } catch (error) {
        profile.value = null;
        toast.show(errorMessage(error, 'Pengguna tidak ditemukan.'));
        await router.replace('/');
    }
}

async function loadItems() {
    loading.value = true;
    items.value = [];

    try {
        const { data } = await api.get(
            `/users/${encodeURIComponent(route.params.username)}/${tab.value}`,
        );
        items.value = data.data ?? [];
    } catch (error) {
        toast.show(errorMessage(error));
    } finally {
        loading.value = false;
    }
}

async function toggleFollow() {
    if (!auth.isAuthenticated) {
        await router.push({ name: 'login', query: { redirect: route.fullPath } });

        return;
    }

    busy.value = true;

    try {
        const { data } = await api.post(`/users/${encodeURIComponent(route.params.username)}/follow`);

        profile.value = {
            ...profile.value,
            is_following: data.data.is_following,
            followers_count: data.data.followers_count,
        };
        toast.show(data.message);
    } catch (error) {
        toast.show(errorMessage(error));
    } finally {
        busy.value = false;
    }
}

function selectTab(key) {
    tab.value = key;
    loadItems();
}

watch(
    () => route.params.username,
    async () => {
        if (route.name !== 'user.show') {
            return;
        }

        tab.value = 'posts';
        profile.value = null;
        await loadProfile();
        await loadItems();
    },
    { immediate: true },
);
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <div v-if="profile" class="rounded-[22px] border border-ln bg-sf p-4">
            <div class="flex items-start gap-3">
                <div
                    class="grid h-14 w-14 flex-none place-items-center overflow-hidden rounded-full bg-ink text-lg font-extrabold text-on-ink"
                >
                    <img v-if="mediaUrl(profile.avatar_path)" :src="mediaUrl(profile.avatar_path)" alt="" class="h-full w-full object-cover" />
                    <template v-else>{{ initials(profile.name) }}</template>
                </div>
                <div class="min-w-0 flex-1">
                    <b class="block truncate text-[15px]">{{ profile.name }}</b>
                    <span class="text-xs text-mu">@{{ profile.username }}</span>
                    <span
                        v-if="profile.developer_profile"
                        class="ml-1.5 rounded-full bg-sf2 px-2 py-0.5 text-[10.5px] font-bold text-acc"
                    >
                        Developer
                    </span>
                </div>

                <button
                    v-if="!isSelf"
                    class="btn"
                    type="button"
                    :class="{ 'btn-ghost': profile.is_following }"
                    :disabled="busy"
                    @click="toggleFollow"
                >
                    {{ profile.is_following ? 'Mengikuti' : 'Ikuti' }}
                </button>
            </div>

            <p v-if="profile.bio" class="mt-3 text-[13px] text-mu2">{{ profile.bio }}</p>

            <div class="mt-3.5 grid grid-cols-2 divide-x divide-ln rounded-2xl border border-ln bg-bg py-3 text-center">
                <div>
                    <b class="block text-base">{{ profile.posts_count }}</b>
                    <span class="text-[11.5px] text-mu">Postingan</span>
                </div>
                <div>
                    <b class="block text-base">{{ profile.followers_count }}</b>
                    <span class="text-[11.5px] text-mu">Pengikut</span>
                </div>
                <div>
                    <b class="block text-base">{{ profile.following_count }}</b>
                    <span class="text-[11.5px] text-mu">Diikuti</span>
                </div>
            </div>
        </div>

        <div v-else class="loading h-[190px]"></div>

        <div class="mb-3.5 mt-4 flex gap-1.5" role="group" aria-label="Bagian profil">
            <button
                v-for="t in tabs"
                :key="t.key"
                class="tab"
                type="button"
                :aria-pressed="tab === t.key"
                @click="selectTab(t.key)"
            >
                {{ t.label }}
            </button>
        </div>

        <div v-if="loading" class="loading h-40"></div>

        <template v-else>
            <div v-if="tab === 'posts'">
                <PostCard v-for="post in cardPosts" :key="post.id" :post="post" />
                <p v-if="!cardPosts.length" class="py-6 text-center text-mu">Belum ada postingan.</p>
            </div>

            <div v-else-if="tab === 'products'">
                <ProductGrid :products="items" empty-text="Belum ada aplikasi yang diterbitkan." />
            </div>

            <div v-else class="flex flex-col gap-2">
                <RouterLink
                    v-for="person in items"
                    :key="person.id"
                    class="flex items-center gap-3 rounded-2xl border border-ln bg-sf p-3"
                    :to="{ name: 'user.show', params: { username: person.username } }"
                >
                    <div
                        class="grid h-10 w-10 flex-none place-items-center overflow-hidden rounded-full bg-ink text-[13px] font-extrabold text-on-ink"
                    >
                        <img v-if="mediaUrl(person.avatar_path)" :src="mediaUrl(person.avatar_path)" alt="" class="h-full w-full object-cover" />
                        <template v-else>{{ initials(person.name) }}</template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <b class="block truncate text-sm">{{ person.name }}</b>
                        <span class="text-xs text-mu">@{{ person.username }}</span>
                    </div>
                    <span class="text-xs text-mu">{{ person.followers_count }} pengikut</span>
                </RouterLink>
                <p v-if="!items.length" class="py-6 text-center text-mu">Belum ada pengikut.</p>
            </div>
        </template>
    </div>
</template>
