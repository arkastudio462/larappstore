<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const auth = useAuthStore();
const toast = useToastStore();

const busy = ref(false);

const benefits = [
    'Unggah aplikasi dan game ke katalog',
    'Terbitkan versi baru beserta catatan perubahan',
    'Lihat statistik unduhan untuk produkmu',
    'Dapat 1 slot upload gratis saat pertama kali upgrade',
];

async function upgrade() {
    busy.value = true;
    const result = await auth.upgradeToDeveloper();
    busy.value = false;

    if (result.ok) {
        toast.show(result.message || 'Selamat, kamu sekarang developer.');
    } else {
        toast.show(result.message);
    }
}

</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <div class="mb-4">
            <b class="block text-[15px]">Akun developer</b>
            <span class="text-xs text-mu">Gratis, instan, tanpa verifikasi.</span>
        </div>

        <template v-if="auth.isDeveloper">
            <div class="rounded-[22px] border border-ln bg-sf p-4">
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-12 w-12 flex-none place-items-center rounded-2xl bg-ink text-[22px]"
                        aria-hidden="true"
                    >
                        🚀
                    </div>
                    <div class="min-w-0 flex-1">
                        <b class="block text-[15px]">
                            {{ auth.user?.developer_profile?.studio_name || auth.user?.name }}
                        </b>
                        <span class="text-xs text-mu">
                            Studio aktif · @{{ auth.user?.username }}
                        </span>
                    </div>
                </div>

                <div class="mt-3.5 rounded-2xl border border-ln bg-bg py-3 text-center">
                    <b class="block text-base">
                        {{ auth.hasUnlimitedUploads ? '∞' : auth.uploadCredits }}
                    </b>
                    <span class="text-[11.5px] text-mu">
                        {{ auth.hasUnlimitedUploads ? 'Upload tanpa batas' : 'Slot upload tersisa' }}
                    </span>
                </div>

                <RouterLink class="btn mt-3.5 w-full py-3! text-sm" :to="{ name: 'kelola.dashboard' }">
                    Buka dashboard developer
                </RouterLink>
            </div>
        </template>

        <template v-else>
            <div class="rounded-[22px] border border-ln bg-sf p-4">
                <b class="text-[15px]">Naikkan level akunmu</b>
                <p class="mt-1 text-[13px] text-mu">
                    Dengan status developer kamu bisa menerbitkan aplikasi sendiri di AppFeed.
                </p>

                <ul class="mt-3.5 space-y-2.5">
                    <li v-for="benefit in benefits" :key="benefit" class="flex gap-2.5 text-[13px] text-mu2">
                        <span class="font-bold text-acc" aria-hidden="true">✓</span>
                        <span>{{ benefit }}</span>
                    </li>
                </ul>

                <button class="btn mt-4 w-full py-3! text-sm" type="button" :disabled="busy" @click="upgrade">
                    {{ busy ? 'Memproses…' : 'Upgrade jadi developer' }}
                </button>

                <p class="mt-2.5 text-center text-[11.5px] text-mu">Tidak dipungut biaya.</p>
            </div>
        </template>
    </div>
</template>
