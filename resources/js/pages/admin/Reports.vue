<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import Icon from '@/components/ui/Icon.vue';
import { relativeTime } from '@/lib/format';
import { useAdminStore } from '@/stores/admin';
import { useToastStore } from '@/stores/toast';

const admin = useAdminStore();
const toast = useToastStore();

const status = ref('open');

const tabs = [
    { value: 'open', label: 'Terbuka' },
    { value: 'resolved', label: 'Selesai' },
    { value: 'dismissed', label: 'Diabaikan' },
];

onMounted(() => admin.loadReports(status.value));

watch(status, (value) => admin.loadReports(value));

async function resolve(report, next) {
    const result = await admin.resolveReport(report.id, next);
    toast.show(result.message);

    if (result.ok) {
        admin.loadReports(status.value);
    }
}

async function removeContent(report) {
    if (!window.confirm('Hapus konten yang dilaporkan ini?')) {
        return;
    }

    const result = await admin.deleteContent(report);
    toast.show(result.message);

    if (result.ok) {
        admin.resolveReport(report.id, 'resolved');
        admin.loadReports(status.value);
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5">
            <b class="block text-[15px]">Panel admin</b>
            <span class="text-xs text-mu">Laporan konten dan aksi hapus cepat.</span>
        </header>

        <AdminNav />

        <div class="mb-3.5 flex gap-1.5" role="group" aria-label="Status laporan">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                class="tab"
                type="button"
                :aria-pressed="status === tab.value"
                @click="status = tab.value"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="admin.reportsLoading" class="space-y-3" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="h-24 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
        </div>

        <template v-else>
            <article
                v-for="report in admin.reports"
                :key="report.id"
                class="mb-3 rounded-[22px] border border-ln bg-sf p-3.5"
            >
                <div class="flex items-start gap-2">
                    <span class="chip" aria-hidden="true">{{ report.reportable_type }}</span>
                    <span class="ml-auto text-xs text-mu">{{ relativeTime(report.created_at) }}</span>
                </div>

                <p class="mt-2 text-sm font-semibold">{{ report.reason }}</p>

                <p v-if="report.subject" class="mt-1 break-words text-xs text-mu">
                    {{ report.subject.label }}
                </p>
                <a
                    v-if="report.subject?.url"
                    class="mt-1 inline-block text-xs font-bold text-acc"
                    :href="report.subject.url"
                >
                    Lihat konten
                </a>

                <p class="mt-1 text-xs text-mu">
                    Dilaporkan oleh
                    <b>{{ report.reporter?.name ?? 'pengguna' }}</b>
                </p>

                <div v-if="report.status === 'open'" class="mt-3 flex flex-wrap gap-2">
                    <button class="btn" type="button" @click="removeContent(report)">
                        <span class="inline-flex items-center gap-1.5">
                            <Icon name="trash" />
                            Hapus konten
                        </span>
                    </button>
                    <button class="btn btn-ghost" type="button" @click="resolve(report, 'resolved')">
                        Tandai selesai
                    </button>
                    <button class="btn btn-ghost" type="button" @click="resolve(report, 'dismissed')">
                        Abaikan
                    </button>
                </div>

                <p v-else class="mt-2 text-xs text-mu">
                    Status: {{ report.status }}
                </p>
            </article>

            <p
                v-if="admin.reports.length === 0"
                class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
            >
                Tidak ada laporan pada kategori ini.
            </p>
        </template>
    </div>
</template>
