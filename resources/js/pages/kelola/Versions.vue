<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Icon from '@/components/ui/Icon.vue';
import { relativeTime } from '@/lib/format';
import { useDeveloperStore } from '@/stores/developer';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const developer = useDeveloperStore();
const toast = useToastStore();

const fileTypes = ['apk', 'ipa', 'exe', 'msi', 'dmg', 'zip', 'pdf', 'dll'];

const selected = ref(String(route.query.produk ?? ''));
const form = ref({ version: '', file_type: 'apk', changelog: '' });
const file = ref(null);
const uploading = ref(false);
const progress = ref(0);
const busy = ref(false);

const product = computed(() => developer.products.find((item) => item.slug === selected.value) ?? null);

onMounted(async () => {
    await developer.loadProducts();

    if (selected.value) {
        developer.loadVersions(selected.value);
    }
});

watch(selected, (slug) => {
    if (slug) {
        developer.loadVersions(slug);
    }
});

async function submit() {
    if (!selected.value || !file.value || busy.value) {
        return;
    }

    busy.value = true;
    uploading.value = true;
    const upload = await developer.uploadFile('product_file', file.value, (percent) => (progress.value = percent));
    uploading.value = false;
    progress.value = 0;

    if (!upload.ok) {
        busy.value = false;
        toast.show(upload.message);

        return;
    }

    const result = await developer.createVersion(selected.value, {
        version: form.value.version.trim(),
        file_path: upload.key,
        file_size: upload.size,
        file_type: form.value.file_type,
        changelog: form.value.changelog.trim() || null,
    });

    busy.value = false;
    toast.show(result.message);

    if (result.ok) {
        form.value = { version: '', file_type: form.value.file_type, changelog: '' };
        file.value = null;
        developer.loadVersions(selected.value);
    }
}

async function publish(version) {
    const result = await developer.publishVersion(selected.value, version.id);
    toast.show(result.message);

    if (result.ok) {
        developer.loadVersions(selected.value);
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <RouterLink class="ib" :to="{ name: 'kelola.dashboard' }" aria-label="Kembali">
                <Icon name="back" />
            </RouterLink>
            <b class="text-[15px]">Kelola versi</b>
        </header>

        <label class="block">
            <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Pilih produk</span>
            <select
                v-model="selected"
                class="h-11 w-full rounded-2xl border border-ln bg-sf px-3.5 text-tx outline-none"
            >
                <option value="" disabled>Pilih produk</option>
                <option v-for="item in developer.products" :key="item.id" :value="item.slug">
                    {{ item.title }}
                </option>
            </select>
        </label>

        <p v-if="!developer.products.length" class="mt-3 text-sm text-mu">
            Belum ada produk. Buat produk dulu di menu Produk saya.
        </p>

        <template v-if="selected">
            <h2 class="mb-2.5 mt-5 text-[17px] font-extrabold">Versi {{ product?.title }}</h2>

            <div class="space-y-2.5">
                <article
                    v-for="version in developer.versions"
                    :key="version.id"
                    class="rounded-2xl border border-ln bg-sf p-3.5"
                >
                    <div class="flex items-center gap-2">
                        <b class="text-sm">v{{ version.version }}</b>
                        <span class="chip" aria-hidden="true">{{ version.status === 'published' ? 'Terbit' : 'Draft' }}</span>
                        <span v-if="version.is_latest" class="chip" aria-hidden="true">Terbaru</span>
                        <span class="ml-auto text-xs text-mu">{{ relativeTime(version.published_at) }}</span>
                    </div>
                    <p v-if="version.changelog" class="mt-1.5 whitespace-pre-wrap text-xs text-mu">
                        {{ version.changelog }}
                    </p>
                    <button
                        v-if="version.status !== 'published'"
                        class="btn mt-2.5"
                        type="button"
                        @click="publish(version)"
                    >
                        Terbitkan
                    </button>
                </article>

                <p
                    v-if="!developer.versionsLoading && developer.versions.length === 0"
                    class="rounded-2xl border border-ln bg-sf px-3.5 py-6 text-center text-xs text-mu"
                >
                    Belum ada versi untuk produk ini.
                </p>
            </div>

            <section class="mt-5 rounded-[22px] border border-ln bg-sf p-3.5">
                <b class="mb-2.5 block text-[15px]">Rilis versi baru</b>

                <div class="space-y-3">
                    <label class="block">
                        <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Nomor versi</span>
                        <input
                            v-model="form.version"
                            type="text"
                            placeholder="1.0.0"
                            class="h-11 w-full rounded-2xl border border-ln bg-bg px-3.5 text-tx outline-none transition focus:border-ol"
                        />
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Tipe berkas</span>
                        <select
                            v-model="form.file_type"
                            class="h-11 w-full rounded-2xl border border-ln bg-bg px-3.5 text-tx outline-none"
                        >
                            <option v-for="type in fileTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Berkas rilis</span>
                        <input
                            type="file"
                            class="w-full text-xs text-mu"
                            @change="file = $event.target.files[0] ?? null"
                        />
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Catatan perubahan</span>
                        <textarea
                            v-model="form.changelog"
                            rows="3"
                            class="w-full resize-none rounded-2xl border border-ln bg-bg px-3.5 py-2.5 text-tx outline-none transition focus:border-ol"
                            placeholder="Apa yang baru di versi ini?"
                        ></textarea>
                    </label>
                </div>

                <button
                    class="btn mt-3.5 w-full py-3! text-sm"
                    type="button"
                    :disabled="busy || !form.version || !file"
                    @click="submit"
                >
                    {{ uploading ? `Mengunggah ${progress}%` : busy ? 'Menyimpan…' : 'Simpan versi draft' }}
                </button>
            </section>
        </template>
    </div>
</template>
