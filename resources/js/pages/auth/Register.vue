<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '@/components/ui/Field.vue';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { isEmail, isUsername, required } from '@/lib/validate';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const route = useRoute();

const name = ref('');
const username = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const nameField = ref(null);

onMounted(() => {
    auth.clearErrors();
    nameField.value?.focus();
});

function safeRedirect() {
    const target = route.query.redirect;

    return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/';
}

function validate() {
    const errors = {};

    if (!required(name.value)) {
        errors.name = 'Nama lengkap wajib diisi.';
    } else if (name.value.trim().length > 80) {
        errors.name = 'Nama lengkap maksimal 80 karakter.';
    }

    if (!required(username.value)) {
        errors.username = 'Username wajib diisi.';
    } else if (username.value.trim().length < 3) {
        errors.username = 'Username minimal 3 karakter.';
    } else if (username.value.trim().length > 30) {
        errors.username = 'Username maksimal 30 karakter.';
    } else if (!isUsername(username.value.trim())) {
        errors.username = 'Username hanya boleh huruf, angka, tanda hubung, dan garis bawah.';
    }

    if (!required(email.value)) {
        errors.email = 'Email wajib diisi.';
    } else if (!isEmail(email.value.trim())) {
        errors.email = 'Format email tidak valid.';
    }

    if (!required(password.value)) {
        errors.password = 'Password wajib diisi.';
    } else if (password.value.length < 8) {
        errors.password = 'Password minimal 8 karakter.';
    }

    if (!required(passwordConfirmation.value)) {
        errors.password_confirmation = 'Konfirmasi password wajib diisi.';
    } else if (passwordConfirmation.value !== password.value) {
        errors.password_confirmation = 'Konfirmasi password tidak cocok.';
    }

    return errors;
}

async function submit() {
    if (auth.busy) {
        return;
    }

    const errors = validate();

    if (Object.keys(errors).length > 0) {
        auth.setErrors(errors);
        return;
    }

    const result = await auth.register({
        name: name.value.trim(),
        username: username.value.trim(),
        email: email.value.trim(),
        password: password.value,
        password_confirmation: passwordConfirmation.value,
    });

    if (result.ok) {
        toast.show(result.message || 'Akun berhasil dibuat.');
        await router.push(safeRedirect());
    } else {
        toast.show(result.message);
    }
}
</script>

<template>
    <div class="mx-auto w-full max-w-xl px-6 pb-16 pt-12">
        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-ink text-xl font-extrabold text-on-ink">
            A
        </div>

        <h1 class="mt-5 text-2xl font-extrabold">Buat akun AppFeed</h1>
        <p class="mt-1 text-mu">Gratis, dan kamu langsung bisa posting serta mengikuti developer.</p>

        <form class="mt-6 space-y-3.5" novalidate @submit.prevent="submit">
            <Field
                ref="nameField"
                v-model="name"
                name="name"
                label="Nama lengkap"
                placeholder="Rina Dewi"
                autocomplete="name"
                :error="auth.errors.name"
                @update:modelValue="auth.clearError('name')"
            />
            <div>
                <Field
                    v-model="username"
                    name="username"
                    label="Username"
                    placeholder="rinadewi"
                    autocomplete="username"
                    :error="auth.errors.username"
                    @update:modelValue="auth.clearError('username')"
                />
                <p class="mt-1 text-xs text-mu">Huruf, angka, tanda hubung, dan garis bawah. 3–30 karakter.</p>
            </div>
            <Field
                v-model="email"
                name="email"
                label="Email"
                type="email"
                placeholder="nama@email.com"
                autocomplete="email"
                :error="auth.errors.email"
                @update:modelValue="auth.clearError('email')"
            />
            <Field
                v-model="password"
                name="password"
                label="Password"
                type="password"
                placeholder="Minimal 8 karakter"
                autocomplete="new-password"
                :error="auth.errors.password"
                @update:modelValue="auth.clearError('password')"
            />
            <Field
                v-model="passwordConfirmation"
                name="password_confirmation"
                label="Konfirmasi password"
                type="password"
                placeholder="Ulangi password"
                autocomplete="new-password"
                :error="auth.errors.password_confirmation"
                @update:modelValue="auth.clearError('password_confirmation')"
            />

            <button class="btn w-full py-3! text-sm" type="submit" :disabled="auth.busy">
                {{ auth.busy ? 'Memproses…' : 'Daftar' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-mu">
            Sudah punya akun?
            <RouterLink class="font-bold text-acc" :to="{ name: 'login' }">Masuk</RouterLink>
        </p>
    </div>
</template>
