<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '@/components/ui/Field.vue';
import Icon from '@/components/ui/Icon.vue';
import { mediaUrl, rupiah } from '@/lib/format';
import { useCatalogStore } from '@/stores/catalog';
import { useDeveloperStore } from '@/stores/developer';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const catalog = useCatalogStore();
const developer = useDeveloperStore();
const toast = useToastStore();

const types = [
    { value: 'aplikasi', label: 'Aplikasi' },
    { value: 'game', label: 'Game' },
    { value: 'software', label: 'Software' },
    { value: 'file', label: 'File' },
    { value: 'lainnya', label: 'Lainnya' },
];

const slug = computed(() => (route.params.slug ? String(route.params.slug) : null));
const isEdit = computed(() => slug.value !== null);

const form = ref({
    title: '',
    category_id: '',
    type: 'aplikasi',
    summary: '',
    description: '',
    price: 0,
});

const iconPath = ref('');
const iconFile = ref(null);
const uploading = ref(false);
const progress = ref(0);
const busy = ref(false);

onMounted(async () => {
    catalog.loadCategories();

    if (isEdit.value) {
        const result = await developer.loadProduct(slug.value);

        if (result.ok && developer.product) {
            const p = developer.product;

            form.value = {
                title: p.title ?? '',
                category_id: p.category ? undefined : '',
                type: p.type ?? 'aplikasi',
                summary: p.summary ?? '',
                description: p.description ?? '',
                price: p.price ?? 0,
            };
            iconPath.value = p.icon_path ?? '';

            // Cocokkan kategori lewat slug dari daftar kategori.
            const match = findCategory(catalog.categories, p.category?.slug);
            form.value.category_id = match?.id ?? '';
        } else {
            toast.show(result.message);
        }
    }
});

async function uploadIcon() {
    if (!iconFile.value) {
        return;
    }

    uploading.value = true;
    const result = await developer.uploadFile('image', iconFile.value, (percent) => (progress.value = percent));
    uploading.value = false;
    progress.value = 0;

    if (result.ok) {
        iconPath.value = result.key;
        toast.show('Ikon terunggah.');
    } else {
        toast.show(result.message);
    }
}

async function submit() {
    if (busy.value) {
        return;
    }

    busy.value = true;

    const payload = {
        category_id: Number(form.value.category_id),
        type: form.value.type,
        title: form.value.title.trim(),
        summary: form.value.summary.trim() || null,
        description: form.value.description.trim() || null,
        price: Number(form.value.price) || 0,
    };

    if (iconPath.value) {
        payload.icon_path = iconPath.value;
    }

    const result = isEdit.value
        ? await developer.updateProduct(slug.value, payload)
        : await developer.createProduct(payload);

    busy.value = false;
    toast.show(result.message);

    if (result.ok) {
        await router.push({ name: 'kelola.products' });
    }
}

function findCategory(list, targetSlug) {
    for (const item of list ?? []) {
        if (item.slug === targetSlug) {
            return item;
        }

        const nested = findCategory(item.children, targetSlug);

        if (nested) {
            return nested;
        }
    }

    return null;
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <RouterLink class="ib" :to="{ name: 'kelola.products' }" aria-label="Kembali">
                <Icon name="back" />
            </RouterLink>
            <b class="text-[15px]">{{ isEdit ? 'Ubah produk' : 'Produk baru' }}</b>
        </header>

        <form class="space-y-3.5" novalidate @submit.prevent="submit">
            <Field
                v-model="form.title"
                name="title"
                label="Judul produk"
                placeholder="Catatan Kilat"
                :error="developer.errors.title"
                @update:modelValue="developer.errors.title = ''"
            />

            <label class="block">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Kategori</span>
                <select
                    v-model="form.category_id"
                    class="h-11 w-full rounded-2xl border border-ln bg-sf px-3.5 text-tx outline-none"
                >
                    <option value="" disabled>Pilih kategori</option>
                    <option v-for="category in catalog.categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
                <span v-if="developer.errors.category_id" class="mt-1 block text-xs font-semibold text-ol">
                    {{ developer.errors.category_id }}
                </span>
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Jenis</span>
                <select
                    v-model="form.type"
                    class="h-11 w-full rounded-2xl border border-ln bg-sf px-3.5 text-tx outline-none"
                >
                    <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </label>

            <Field
                v-model="form.summary"
                name="summary"
                label="Ringkasan"
                placeholder="Satu baris tentang produkmu"
                :error="developer.errors.summary"
            />

            <label class="block">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Deskripsi</span>
                <textarea
                    v-model="form.description"
                    rows="5"
                    class="w-full resize-none rounded-2xl border border-ln bg-sf px-3.5 py-2.5 text-tx outline-none transition focus:border-ol"
                    placeholder="Ceritakan fitur dan keunggulan produkmu"
                ></textarea>
            </label>

            <Field
                v-model="form.price"
                name="price"
                label="Harga (Rp, isi 0 bila gratis)"
                type="number"
                placeholder="0"
                :error="developer.errors.price"
            />

            <section class="rounded-[22px] border border-ln bg-sf p-3.5">
                <b class="mb-2 block text-[13px] font-semibold text-mu2">Ikon produk</b>
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-16 w-16 flex-none place-items-center overflow-hidden rounded-2xl border border-ln bg-sf2 text-[26px]"
                        aria-hidden="true"
                    >
                        <img v-if="iconPath" :src="mediaUrl(iconPath)" alt="" class="h-full w-full object-cover" />
                        <span v-else>📦</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="w-full text-xs text-mu"
                            @change="iconFile = $event.target.files[0] ?? null"
                        />
                        <button
                            class="btn btn-ghost mt-2"
                            type="button"
                            :disabled="!iconFile || uploading"
                            @click="uploadIcon"
                        >
                            {{ uploading ? `Mengunggah ${progress}%` : 'Unggah ikon' }}
                        </button>
                    </div>
                </div>
            </section>

            <button class="btn w-full py-3! text-sm" type="submit" :disabled="busy">
                {{ busy ? 'Menyimpan…' : isEdit ? 'Simpan perubahan' : 'Simpan sebagai draft' }}
            </button>
        </form>
    </div>
</template>
