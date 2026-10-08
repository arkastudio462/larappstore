<script setup>
import { onMounted, ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import Field from '@/components/ui/Field.vue';
import { useAdminStore } from '@/stores/admin';
import { useCatalogStore } from '@/stores/catalog';
import { useToastStore } from '@/stores/toast';

const admin = useAdminStore();
const catalog = useCatalogStore();
const toast = useToastStore();

const editing = ref(null);
const busy = ref(false);
const form = ref({ name: '', slug: '', description: '', is_active: true, product_ids: [] });

onMounted(() => {
    admin.loadCollections();
    catalog.loadProducts({ per_page: 50, sort: 'title' });
});

function reset() {
    editing.value = null;
    form.value = { name: '', slug: '', description: '', is_active: true, product_ids: [] };
}

function edit(collection) {
    editing.value = collection;
    form.value = {
        name: collection.name,
        slug: collection.slug,
        description: collection.description ?? '',
        is_active: collection.is_active,
        product_ids: (collection.products ?? []).map((product) => product.id),
    };
}

async function submit() {
    busy.value = true;

    const result = await admin.saveCollection(
        {
            name: form.value.name.trim(),
            slug: form.value.slug.trim() || null,
            description: form.value.description.trim() || null,
            is_active: form.value.is_active,
            product_ids: form.value.product_ids.map(Number),
        },
        editing.value?.id ?? null,
    );

    busy.value = false;
    toast.show(result.message);

    if (result.ok) {
        reset();
        admin.loadCollections();
    }
}

async function remove(collection) {
    if (!window.confirm(`Hapus koleksi ${collection.name}?`)) {
        return;
    }

    const result = await admin.deleteCollection(collection.id);
    toast.show(result.message);

    if (result.ok) {
        admin.loadCollections();
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5">
            <b class="block text-[15px]">Koleksi</b>
            <span class="text-xs text-mu">Kurasi produk pilihan.</span>
        </header>

        <AdminNav />

        <section class="mb-4 space-y-3.5 rounded-[22px] border border-ln bg-sf p-3.5">
            <b class="block text-[15px]">{{ editing ? `Ubah ${editing.name}` : 'Koleksi baru' }}</b>

            <Field
                v-model="form.name"
                name="collection_name"
                label="Nama"
                placeholder="Pilihan Editor"
                :error="admin.errors.name"
                @update:modelValue="admin.errors.name = ''"
            />
            <Field v-model="form.slug" name="collection_slug" label="Slug (opsional)" placeholder="pilihan-editor" />

            <label class="block">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">Deskripsi</span>
                <textarea
                    v-model="form.description"
                    rows="2"
                    class="w-full resize-none rounded-2xl border border-ln bg-bg px-3.5 py-2.5 text-tx outline-none transition focus:border-ol"
                ></textarea>
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[13px] font-semibold text-mu2">
                    Produk ({{ form.product_ids.length }} dipilih)
                </span>
                <select
                    v-model="form.product_ids"
                    multiple
                    size="6"
                    class="w-full rounded-2xl border border-ln bg-bg px-3 py-2 text-sm text-tx outline-none"
                >
                    <option v-for="product in catalog.products" :key="product.id" :value="product.id">
                        {{ product.title }}
                    </option>
                </select>
            </label>

            <label class="flex items-center gap-2.5 text-sm text-mu">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-[var(--acc)]" />
                Aktif
            </label>

            <div class="flex gap-2">
                <button class="btn" type="button" :disabled="busy" @click="submit">
                    {{ busy ? 'Menyimpan…' : 'Simpan' }}
                </button>
                <button v-if="editing" class="btn btn-ghost" type="button" @click="reset">Batal</button>
            </div>
        </section>

        <div v-if="admin.collectionsLoading" class="loading h-32"></div>

        <div v-else class="space-y-2.5">
            <article
                v-for="collection in admin.collections"
                :key="collection.id"
                class="flex items-center gap-3 rounded-2xl border border-ln bg-sf p-3.5"
            >
                <div class="min-w-0 flex-1">
                    <b class="block truncate text-sm">{{ collection.name }}</b>
                    <span class="block text-xs text-mu">
                        {{ collection.products_count }} produk
                        <template v-if="!collection.is_active"> · nonaktif</template>
                    </span>
                </div>
                <button class="btn btn-ghost" type="button" @click="edit(collection)">Ubah</button>
                <button class="btn btn-ghost" type="button" @click="remove(collection)">Hapus</button>
            </article>
        </div>
    </div>
</template>
