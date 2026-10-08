<script setup>
import { onMounted, ref } from 'vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import Field from '@/components/ui/Field.vue';
import { useAdminStore } from '@/stores/admin';
import { useToastStore } from '@/stores/toast';

const admin = useAdminStore();
const toast = useToastStore();

const editing = ref(null);
const busy = ref(false);
const form = ref({ name: '', slug: '', icon: '', position: 0, is_active: true });

onMounted(() => admin.loadCategories());

function reset() {
    editing.value = null;
    form.value = { name: '', slug: '', icon: '', position: 0, is_active: true };
}

function edit(category) {
    editing.value = category;
    form.value = {
        name: category.name,
        slug: category.slug,
        icon: category.icon ?? '',
        position: category.position ?? 0,
        is_active: category.is_active,
    };
}

async function submit() {
    busy.value = true;

    const result = await admin.saveCategory(
        {
            name: form.value.name.trim(),
            slug: form.value.slug.trim() || null,
            icon: form.value.icon.trim() || null,
            position: Number(form.value.position) || 0,
            is_active: form.value.is_active,
        },
        editing.value?.id ?? null,
    );

    busy.value = false;
    toast.show(result.message);

    if (result.ok) {
        reset();
        admin.loadCategories();
    }
}

async function remove(category) {
    if (!window.confirm(`Hapus kategori ${category.name}?`)) {
        return;
    }

    const result = await admin.deleteCategory(category.id);
    toast.show(result.message);

    if (result.ok) {
        admin.loadCategories();
    }
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-3.5">
            <b class="block text-[15px]">Kategori</b>
            <span class="text-xs text-mu">Kelola kategori katalog.</span>
        </header>

        <AdminNav />

        <section class="mb-4 space-y-3.5 rounded-[22px] border border-ln bg-sf p-3.5">
            <b class="block text-[15px]">{{ editing ? `Ubah ${editing.name}` : 'Kategori baru' }}</b>

            <Field
                v-model="form.name"
                name="name"
                label="Nama"
                placeholder="Media Sosial"
                :error="admin.errors.name"
                @update:modelValue="admin.errors.name = ''"
            />
            <Field
                v-model="form.slug"
                name="slug"
                label="Slug (opsional)"
                placeholder="media-sosial"
                :error="admin.errors.slug"
                @update:modelValue="admin.errors.slug = ''"
            />
            <Field v-model="form.icon" name="icon" label="Ikon (opsional)" placeholder="chat" />

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

        <div v-if="admin.categoriesLoading" class="loading h-32"></div>

        <div v-else class="space-y-2.5">
            <article
                v-for="category in admin.categories"
                :key="category.id"
                class="flex items-center gap-3 rounded-2xl border border-ln bg-sf p-3.5"
            >
                <div class="min-w-0 flex-1">
                    <b class="block truncate text-sm">{{ category.name }}</b>
                    <span class="block text-xs text-mu">
                        {{ category.slug }} · {{ category.products_count }} produk
                        <template v-if="!category.is_active"> · nonaktif</template>
                    </span>
                </div>
                <button class="btn btn-ghost" type="button" @click="edit(category)">Ubah</button>
                <button class="btn btn-ghost" type="button" @click="remove(category)">Hapus</button>
            </article>
        </div>
    </div>
</template>
