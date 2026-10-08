<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import Icon from '@/components/ui/Icon.vue';
import { initials } from '@/lib/format';
import { useAdminStore } from '@/stores/admin';
import { useToastStore } from '@/stores/toast';

const admin = useAdminStore();
const toast = useToastStore();

const q = ref('');
const role = ref('');

const roles = [
    { value: '', label: 'Semua' },
    { value: 'user', label: 'Pengguna' },
    { value: 'developer', label: 'Developer' },
    { value: 'admin', label: 'Admin' },
];

onMounted(load);
watch(role, load);

function load() {
    const params = {};

    if (q.value.trim()) {
        params.q = q.value.trim();
    }

    if (role.value) {
        params.role = role.value;
    }

    admin.loadUsers(params);
}

async function setRole(user, value) {
    const result = await admin.updateUser(user.id, { role: value });
    toast.show(result.message);

    if (result.ok) {
        load();
    }
}

async function toggleBan(user) {
    const result = await admin.updateUser(user.id, { banned: !user.is_banned });
    toast.show(result.message);

    if (result.ok) {
        load();
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5">
            <b class="block text-[15px]">Pengguna</b>
            <span class="text-xs text-mu">Ubah peran atau blokir akun.</span>
        </header>

        <AdminNav />

        <form class="flex gap-2.5" @submit.prevent="load">
            <input
                v-model="q"
                type="search"
                placeholder="Cari nama, username, atau email"
                aria-label="Cari pengguna"
                class="h-11 min-w-0 flex-1 rounded-2xl border border-ln bg-sf px-3.5 text-tx outline-none transition focus:border-ol placeholder:text-mu"
            />
            <button class="btn" type="submit">Cari</button>
        </form>

        <div class="no-sb mt-3 flex gap-2 overflow-x-auto">
            <button
                v-for="item in roles"
                :key="item.value"
                class="chip"
                type="button"
                :aria-pressed="role === item.value"
                @click="role = item.value"
            >
                {{ item.label }}
            </button>
        </div>

        <div v-if="admin.usersLoading" class="mt-3.5 space-y-3" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="h-20 animate-pulse rounded-2xl border border-ln bg-sf"></div>
        </div>

        <div v-else class="mt-3.5 space-y-2.5">
            <article
                v-for="user in admin.users"
                :key="user.id"
                class="rounded-2xl border border-ln bg-sf p-3.5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-10 w-10 flex-none place-items-center rounded-full bg-ink text-[13px] font-extrabold text-on-ink"
                    >
                        {{ initials(user.name) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <b class="block truncate text-sm">{{ user.name }}</b>
                        <span class="block truncate text-xs text-mu">@{{ user.username }} · {{ user.email }}</span>
                    </div>
                    <span v-if="user.is_banned" class="chip" aria-hidden="true">Diblokir</span>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <select
                        :value="user.role"
                        class="h-10 rounded-2xl border border-ln bg-bg px-3 text-sm text-tx outline-none"
                        :aria-label="`Peran ${user.name}`"
                        @change="setRole(user, $event.target.value)"
                    >
                        <option value="user">Pengguna</option>
                        <option value="developer">Developer</option>
                        <option value="admin">Admin</option>
                    </select>

                    <button class="btn btn-ghost" type="button" @click="toggleBan(user)">
                        <span class="inline-flex items-center gap-1.5">
                            <Icon name="user" />
                            {{ user.is_banned ? 'Buka blokir' : 'Blokir' }}
                        </span>
                    </button>
                </div>
            </article>

            <p
                v-if="admin.users.length === 0"
                class="rounded-2xl border border-ln bg-sf px-4 py-8 text-center text-sm text-mu"
            >
                Tidak ada pengguna yang cocok.
            </p>
        </div>
    </div>
</template>
