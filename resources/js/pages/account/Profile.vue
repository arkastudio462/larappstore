<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PostCard from '@/components/feed/PostCard.vue';
import Field from '@/components/ui/Field.vue';
import Icon from '@/components/ui/Icon.vue';
import { api, errorMessage } from '@/lib/api';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { initials, mediaUrl } from '@/lib/format';
import { isEmail, isUsername, required } from '@/lib/validate';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();

const form = ref({
    name: '',
    username: '',
    email: '',
    bio: '',
    password: '',
    passwordConfirmation: '',
});

const saving = ref(false);
const showEdit = ref(false);
const loggingOut = ref(false);
const avatarFile = ref(null);
const avatarPreview = ref('');

const posts = ref([]);
const postsLoading = ref(false);

async function loadPosts() {
    if (!auth.user?.username) {
        return;
    }

    postsLoading.value = true;

    try {
        const { data } = await api.get(`/users/${encodeURIComponent(auth.user.username)}/posts`);
        posts.value = data.data ?? [];
    } catch (error) {
        toast.show(errorMessage(error));
    } finally {
        postsLoading.value = false;
    }
}

const avatarUrl = computed(() => avatarPreview.value || mediaUrl(auth.user?.avatar_path));

function pickAvatar(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
}

const counts = computed(() => [
    { label: 'Pengikut', value: auth.user?.followers_count ?? 0 },
    { label: 'Diikuti', value: auth.user?.following_count ?? 0 },
    { label: 'Postingan', value: auth.user?.posts_count ?? 0 },
]);

function syncForm() {
    auth.clearErrors();

    if (!auth.user) {
        return;
    }

    form.value = {
        name: auth.user.name ?? '',
        username: auth.user.username ?? '',
        email: auth.user.email ?? '',
        bio: auth.user.bio ?? '',
        password: '',
        passwordConfirmation: '',
    };
}

function validate() {
    const errors = {};

    if (!required(form.value.name)) {
        errors.name = 'Nama lengkap wajib diisi.';
    } else if (form.value.name.trim().length > 80) {
        errors.name = 'Nama lengkap maksimal 80 karakter.';
    }

    if (!required(form.value.username)) {
        errors.username = 'Username wajib diisi.';
    } else if (form.value.username.trim().length < 3) {
        errors.username = 'Username minimal 3 karakter.';
    } else if (form.value.username.trim().length > 30) {
        errors.username = 'Username maksimal 30 karakter.';
    } else if (!isUsername(form.value.username.trim())) {
        errors.username = 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.';
    }

    if (!required(form.value.email)) {
        errors.email = 'Email wajib diisi.';
    } else if (!isEmail(form.value.email.trim())) {
        errors.email = 'Format email tidak valid.';
    }

    if (form.value.bio.length > 280) {
        errors.bio = 'Bio maksimal 280 karakter.';
    }

    if (required(form.value.password)) {
        if (form.value.password.length < 8) {
            errors.password = 'Password baru minimal 8 karakter.';
        } else if (form.value.password !== form.value.passwordConfirmation) {
            errors.password_confirmation = 'Konfirmasi password tidak cocok.';
        }
    }

    return errors;
}

async function submit() {
    if (saving.value || auth.busy) {
        return;
    }

    const errors = validate();

    if (Object.keys(errors).length > 0) {
        auth.setErrors(errors);
        return;
    }

    const payload = new FormData();

    payload.append('name', form.value.name.trim());
    payload.append('username', form.value.username.trim());
    payload.append('email', form.value.email.trim());
    payload.append('bio', form.value.bio);

    if (form.value.password) {
        payload.append('password', form.value.password);
        payload.append('password_confirmation', form.value.passwordConfirmation);
    }

    if (avatarFile.value) {
        payload.append('avatar', avatarFile.value);
    }

    saving.value = true;
    const result = await auth.updateProfile(payload);
    saving.value = false;

    if (result.ok) {
        form.value.password = '';
        form.value.passwordConfirmation = '';
        avatarFile.value = null;
        avatarPreview.value = '';
        showEdit.value = false;
        toast.show(result.message || 'Profil diperbarui.');
    } else {
        toast.show(result.message);
    }
}

async function logout() {
    loggingOut.value = true;
    await auth.logout();
    loggingOut.value = false;
    toast.show('Berhasil keluar.');
    await router.push('/');
}

watch(() => auth.user, syncForm, { immediate: true });
watch(() => auth.user?.username, loadPosts, { immediate: true });
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
      <div class="lg:grid lg:grid-cols-[340px_minmax(0,1fr)] lg:gap-8">
        <aside class="min-w-0">
        <div class="mb-4 flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <b class="block text-[15px]">Profil saya</b>
                <span class="text-xs text-mu">Ubah data akun dan password.</span>
            </div>
            <RouterLink
                v-if="auth.isDeveloper"
                class="btn btn-ghost"
                :to="{ name: 'account.developer' }"
            >
                Portal developer
            </RouterLink>
            <RouterLink v-else class="btn" :to="{ name: 'account.developer' }">Jadi developer</RouterLink>
        </div>

        <div class="mb-4 flex items-center gap-4 rounded-2xl border border-ln bg-sf p-4">
            <label class="relative block h-20 w-20 flex-none cursor-pointer">
                <img
                    v-if="avatarUrl"
                    :src="avatarUrl"
                    alt="Foto profil"
                    class="h-full w-full rounded-full object-cover"
                />
                <span
                    v-else
                    class="grid h-full w-full place-items-center rounded-full bg-ink text-2xl font-extrabold text-on-ink"
                >
                    {{ initials(auth.user?.name) }}
                </span>
                <span class="absolute inset-0 grid place-items-center rounded-full bg-black/40 text-white opacity-0 transition hover:opacity-100">
                    <Icon name="plus" />
                </span>
                <input type="file" accept="image/*" class="hidden" @change="pickAvatar" />
            </label>
            <div class="min-w-0 flex-1">
                <b class="block truncate text-lg">{{ auth.user?.name }}</b>
                <span class="block truncate text-xs text-mu">@{{ auth.user?.username }}</span>
                <p v-if="auth.user?.bio" class="mt-1 truncate text-xs text-mu2">{{ auth.user.bio }}</p>
                <button class="btn btn-ghost mt-3" type="button" @click="showEdit = true">Ubah profil</button>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-3 divide-x divide-ln rounded-2xl border border-ln bg-sf py-3 text-center">
            <div v-for="count in counts" :key="count.label">
                <b class="block text-base">{{ count.value }}</b>
                <span class="text-[11.5px] text-mu">{{ count.label }}</span>
            </div>
        </div>

        <nav class="mb-4 overflow-hidden rounded-2xl border border-ln bg-sf">
            <RouterLink
                class="flex items-center gap-3 border-b border-ln px-3.5 py-3"
                :to="{ name: 'account.purchases' }"
            >
                <Icon name="download" />
                <b class="flex-1 text-sm">Pembelian saya</b>
                <Icon name="back" class="rotate-180" />
            </RouterLink>
            <RouterLink class="flex items-center gap-3 px-3.5 py-3" :to="{ name: 'account.notifications' }">
                <Icon name="bell" />
                <b class="flex-1 text-sm">Notifikasi</b>
                <Icon name="back" class="rotate-180" />
            </RouterLink>
            <RouterLink
                v-if="auth.isAdmin"
                class="flex items-center gap-3 border-t border-ln px-3.5 py-3"
                :to="{ name: 'admin.reports' }"
            >
                <Icon name="filter" />
                <b class="flex-1 text-sm">Panel admin</b>
                <Icon name="back" class="rotate-180" />
            </RouterLink>
        </nav>

        <button
            class="mt-4 w-full rounded-2xl border border-ln bg-sf py-3 text-sm font-bold text-ol"
            type="button"
            :disabled="loggingOut"
            @click="logout"
        >
            {{ loggingOut ? 'Keluar…' : 'Keluar dari akun' }}
        </button>
        </aside>

        <div class="mt-6 min-w-0 lg:mt-0">
        <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showEdit = false">
        <form class="no-sb max-h-[85dvh] w-full max-w-lg space-y-3.5 overflow-y-auto rounded-[22px] border border-ln bg-bg p-4" novalidate @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <b class="text-[15px]">Ubah profil</b>
                <button class="ib" type="button" aria-label="Tutup" @click="showEdit = false">
                    <Icon name="back" />
                </button>
            </div>
            <Field
                v-model="form.name"
                name="name"
                label="Nama lengkap"
                autocomplete="name"
                :error="auth.errors.name"
                @update:modelValue="auth.clearError('name')"
            />
            <Field
                v-model="form.username"
                name="username"
                label="Username"
                autocomplete="username"
                :error="auth.errors.username"
                @update:modelValue="auth.clearError('username')"
            />
            <Field
                v-model="form.email"
                name="email"
                label="Email"
                type="email"
                autocomplete="email"
                :error="auth.errors.email"
                @update:modelValue="auth.clearError('email')"
            />
            <label class="block" for="field-bio">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Bio</span>
                <textarea
                    id="field-bio"
                    v-model="form.bio"
                    name="bio"
                    rows="3"
                    maxlength="280"
                    :aria-invalid="auth.errors.bio ? 'true' : undefined"
                    class="w-full rounded-2xl border bg-sf px-3.5 py-2.5 text-tx outline-none transition placeholder:text-mu"
                    :class="auth.errors.bio ? 'border-ol' : 'border-ln focus:border-ol'"
                    placeholder="Ceritakan sedikit tentang kamu"
                    @input="auth.clearError('bio')"
                ></textarea>
                <span v-if="auth.errors.bio" :id="'field-bio-error'" class="mt-1 block text-xs font-semibold text-ol">
                    {{ auth.errors.bio }}
                </span>
            </label>
            <Field
                v-model="form.password"
                name="password"
                label="Password baru"
                type="password"
                placeholder="Kosongkan bila tidak diganti"
                autocomplete="new-password"
                :error="auth.errors.password"
                @update:modelValue="auth.clearError('password')"
            />
            <Field
                v-if="form.password"
                v-model="form.passwordConfirmation"
                name="password_confirmation"
                label="Konfirmasi password baru"
                type="password"
                placeholder="Ulangi password baru"
                autocomplete="new-password"
                :error="auth.errors.password_confirmation"
                @update:modelValue="auth.clearError('password_confirmation')"
            />

            <button class="btn w-full py-3! text-sm" type="submit" :disabled="saving || auth.busy">
                {{ saving ? 'Menyimpan…' : 'Simpan perubahan' }}
            </button>
        </form>
        </div>

        <section class="mt-6 lg:mt-0">
            <div class="mb-3 flex items-baseline justify-between">
                <h2 class="text-[17px] font-extrabold">Postingan saya</h2>
                <span class="text-xs text-mu">{{ posts.length }} postingan</span>
            </div>

            <div v-if="postsLoading" class="space-y-3" aria-hidden="true">
                <div v-for="n in 2" :key="n" class="h-32 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
            </div>

            <template v-else>
                <PostCard v-for="post in posts" :key="post.id" :post="post" />

                <p v-if="posts.length === 0" class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu">
                    Belum ada postingan. Bagikan kabar pertamamu dari tombol + di navigasi.
                </p>
            </template>
        </section>
        </div>
      </div>
    </div>
</template>
