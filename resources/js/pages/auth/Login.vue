<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '@/components/ui/Field.vue';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import { isEmail, required } from '@/lib/validate';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const remember = ref(false);
const emailField = ref(null);

onMounted(() => {
    auth.clearErrors();
    emailField.value?.focus();
});

function safeRedirect() {
    const target = route.query.redirect;

    return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/';
}

function validate() {
    const errors = {};

    if (!required(email.value)) {
        errors.email = 'Email wajib diisi.';
    } else if (!isEmail(email.value.trim())) {
        errors.email = 'Format email tidak valid.';
    }

    if (!required(password.value)) {
        errors.password = 'Password wajib diisi.';
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

    const result = await auth.login({
        email: email.value.trim(),
        password: password.value,
        remember: remember.value,
    });

    if (result.ok) {
        toast.show(result.message || 'Berhasil masuk.');
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

        <h1 class="mt-5 text-2xl font-extrabold">Masuk ke AppFeed</h1>
        <p class="mt-1 text-mu">Lanjutkan unduh aplikasi dan ikuti feed komunitas.</p>

        <form class="mt-6 space-y-3.5" novalidate @submit.prevent="submit">
            <Field
                ref="emailField"
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
                autocomplete="current-password"
                :error="auth.errors.password"
                @update:modelValue="auth.clearError('password')"
            />

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-mu">
                <input v-model="remember" type="checkbox" class="h-4 w-4 cursor-pointer accent-[var(--acc)]" />
                Ingat saya di perangkat ini
            </label>

            <button class="btn w-full py-3! text-sm" type="submit" :disabled="auth.busy">
                {{ auth.busy ? 'Memproses…' : 'Masuk' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-mu">
            Belum punya akun?
            <RouterLink class="font-bold text-acc" :to="{ name: 'register' }">Daftar</RouterLink>
        </p>
    </div>
</template>
