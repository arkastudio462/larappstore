<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import RatingBar from '@/components/product/RatingBar.vue';
import ScreenshotCarousel from '@/components/product/ScreenshotCarousel.vue';
import Icon from '@/components/ui/Icon.vue';
import { initials, mediaUrl, relativeTime, rupiah } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useCatalogStore } from '@/stores/catalog';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const catalog = useCatalogStore();
const toast = useToastStore();

const tabs = [
    { value: 'tentang', label: 'Tentang' },
    { value: 'versi', label: 'Versi' },
    { value: 'ulasan', label: 'Ulasan' },
];

const tab = ref('tentang');
const error = ref('');
const downloading = ref(false);
const buying = ref(false);
const payment = ref(null);
const rating = ref(0);
const reviewBody = ref('');
const submitting = ref(false);

const slug = computed(() => route.params.slug);
const product = computed(() => catalog.product);

onMounted(load);
watch(slug, load);

async function load() {
    error.value = '';
    tab.value = 'tentang';
    rating.value = 0;
    reviewBody.value = '';

    const result = await catalog.loadProduct(slug.value);

    if (!result.ok) {
        error.value = result.message;
        return;
    }

    catalog.loadVersions(slug.value);
    catalog.loadReviews(slug.value);
}

function openTab(value) {
    tab.value = value;

    if (value === 'versi' && catalog.versions.length === 0) {
        catalog.loadVersions(slug.value);
    }

    if (value === 'ulasan' && catalog.reviews.length === 0) {
        catalog.loadReviews(slug.value);
    }
}

async function download() {
    downloading.value = true;
    const result = await catalog.download(slug.value);
    downloading.value = false;

    if (!result.ok) {
        toast.show(result.message);
        return;
    }

    if (/^https?:\/\//.test(result.url ?? '')) {
        window.open(result.url, '_blank', 'noopener');
        toast.show('Unduhan dimulai.');
    } else {
        toast.show('Tautan unduhan belum dikonfigurasi (R2).');
    }
}

async function buy() {
    buying.value = true;
    const result = await catalog.purchaseProduct(product.value);
    buying.value = false;

    toast.show(result.message);

    if (!result.ok) {
        return;
    }

    payment.value = result.payment ?? null;

    if (result.payment?.redirect_url) {
        window.open(result.payment.redirect_url, '_blank', 'noopener');
    }
}

async function submitReview() {
    if (rating.value < 1) {
        toast.show('Pilih dulu bintang penilaianmu.');
        return;
    }

    submitting.value = true;
    const result = await catalog.submitReview(slug.value, {
        rating: rating.value,
        body: reviewBody.value.trim() || null,
    });
    submitting.value = false;

    toast.show(result.message);

    if (result.ok) {
        rating.value = 0;
        reviewBody.value = '';
        catalog.loadReviews(slug.value);
        catalog.loadProduct(slug.value);
    }
}

function formatSize(bytes) {
    if (!bytes) {
        return '-';
    }

    const mb = bytes / 1048576;

    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <b class="min-w-0 flex-1 truncate text-[15px]">Detail aplikasi</b>
        </header>

        <div
            v-if="error"
            class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
        >
            {{ error }}
        </div>

        <div v-else-if="catalog.productLoading" class="space-y-4" aria-hidden="true">
            <div class="h-40 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
            <div class="h-24 animate-pulse rounded-[22px] border border-ln bg-sf"></div>
        </div>

        <template v-else-if="product">
            <section class="flex items-center gap-3.5">
                <div
                    class="grid h-20 w-20 flex-none place-items-center overflow-hidden rounded-3xl border border-ln bg-sf2 text-[38px]"
                    aria-hidden="true"
                >
                    <img
                        v-if="product.icon_path"
                        :src="mediaUrl(product.icon_path)"
                        alt=""
                        class="h-full w-full object-cover"
                    />
                    <span v-else>📦</span>
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-extrabold">{{ product.title }}</h1>
                    <RouterLink
                        v-if="product.developer"
                        class="block truncate text-xs text-mu"
                        :to="`/u/${product.developer.username}`"
                    >
                        {{ product.developer.name }}
                    </RouterLink>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <RatingBar :avg="product.rating_avg ?? 0" :count="product.rating_count ?? 0" />
                        <span class="text-xs text-mu">{{ product.downloads_count }} unduhan</span>
                    </div>
                </div>
            </section>

            <div class="mt-3 flex flex-wrap gap-1.5">
                <span v-if="product.category" class="chip">{{ product.category.name }}</span>
                <span class="chip">{{ product.type }}</span>
                <span v-if="product.latest_version" class="chip">
                    v{{ product.latest_version.version }} · {{ formatSize(product.latest_version.file_size) }}
                </span>
            </div>

            <p v-if="product.summary" class="mt-3.5 text-sm text-mu">{{ product.summary }}</p>

            <div class="mt-4 flex items-center gap-2.5">
                <span class="text-base font-extrabold">
                    {{ product.is_free ? 'Gratis' : rupiah(product.price) }}
                </span>
                <button
                    v-if="product.is_free || product.is_purchased"
                    class="btn ml-auto py-3!"
                    type="button"
                    :disabled="downloading"
                    @click="download"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <Icon name="download" />
                        {{ downloading ? 'Menyiapkan…' : 'Unduh' }}
                    </span>
                </button>
                <button
                    v-else
                    class="btn ml-auto py-3!"
                    type="button"
                    :disabled="buying"
                    @click="buy"
                >
                    {{ buying ? 'Memproses…' : 'Beli' }}
                </button>
            </div>

            <div v-if="payment" class="mt-3 rounded-2xl border border-ln bg-sf p-3.5">
                <b class="block text-sm">Pesanan dibuat</b>
                <p class="mt-1 text-xs text-mu">
                    Selesaikan pembayaran untuk membuka unduhan. Akses aktif otomatis setelah pembayaran
                    terkonfirmasi.
                </p>
                <a
                    v-if="payment.redirect_url"
                    class="btn mt-3 inline-block"
                    :href="payment.redirect_url"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Buka halaman pembayaran
                </a>
            </div>

            <div class="mt-5">
                <ScreenshotCarousel :screenshots="product.screenshots ?? []" :title="product.title" />
            </div>

            <div class="mt-4 flex gap-1.5" role="tablist">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    class="tab"
                    type="button"
                    role="tab"
                    :aria-pressed="tab === item.value"
                    @click="openTab(item.value)"
                >
                    {{ item.label }}
                </button>
            </div>

            <section v-if="tab === 'tentang'" class="mt-3">
                <p class="whitespace-pre-wrap break-words text-sm">
                    {{ product.description || product.summary || 'Belum ada deskripsi.' }}
                </p>
            </section>

            <section v-else-if="tab === 'versi'" class="mt-3 space-y-2.5">
                <div
                    v-for="version in catalog.versions"
                    :key="version.id"
                    class="rounded-2xl border border-ln bg-sf p-3.5"
                >
                    <div class="flex items-center gap-2">
                        <b class="text-sm">v{{ version.version }}</b>
                        <span v-if="version.is_latest" class="chip" aria-hidden="true">Terbaru</span>
                        <span class="ml-auto text-xs text-mu">
                            {{ relativeTime(version.published_at) }}
                        </span>
                    </div>
                    <p v-if="version.changelog" class="mt-1.5 whitespace-pre-wrap text-xs text-mu">
                        {{ version.changelog }}
                    </p>
                    <span class="mt-1 block text-xs text-mu">
                        {{ formatSize(version.file_size) }} · {{ version.download_count }} unduhan
                    </span>
                </div>
                <p
                    v-if="catalog.versions.length === 0"
                    class="rounded-[22px] border border-ln bg-sf px-4 py-8 text-center text-sm text-mu"
                >
                    Belum ada versi terbit.
                </p>
            </section>

            <section v-else class="mt-3">
                <div v-if="auth.isAuthenticated && !product.my_review_id" class="rounded-2xl border border-ln bg-sf p-3.5">
                    <b class="mb-2 block text-sm">Beri ulasanmu</b>
                    <div class="mb-2 flex gap-1.5">
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            :aria-label="`${star} bintang`"
                            :aria-pressed="rating === star"
                            @click="rating = star"
                        >
                            <Icon
                                name="star"
                                class="h-6! w-6!"
                                :class="star <= rating ? 'text-acc' : 'text-mu'"
                            />
                        </button>
                    </div>
                    <textarea
                        v-model="reviewBody"
                        rows="2"
                        maxlength="1000"
                        placeholder="Ceritakan pengalamanmu (opsional)"
                        aria-label="Isi ulasan"
                        class="w-full resize-none rounded-2xl border border-ln bg-bg px-3.5 py-2.5 text-sm text-tx outline-none transition focus:border-ol placeholder:text-mu"
                    ></textarea>
                    <div class="mt-2 flex justify-end">
                        <button class="btn" type="button" :disabled="submitting" @click="submitReview">
                            {{ submitting ? 'Mengirim…' : 'Kirim ulasan' }}
                        </button>
                    </div>
                </div>

                <p
                    v-else-if="auth.isAuthenticated"
                    class="mb-3 rounded-2xl border border-ln bg-sf px-3.5 py-2.5 text-xs text-mu"
                >
                    Kamu sudah memberi ulasan untuk produk ini.
                </p>

                <div class="space-y-2.5">
                    <article
                        v-for="review in catalog.reviews"
                        :key="review.id"
                        class="rounded-2xl border border-ln bg-sf p-3.5"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="grid h-8 w-8 flex-none place-items-center rounded-full bg-sf2 text-[12px] font-extrabold"
                            >
                                {{ initials(review.author?.name) }}
                            </div>
                            <b class="min-w-0 flex-1 truncate text-[13px]">{{ review.author?.name }}</b>
                            <RatingBar :rating="review.rating" :avg="review.rating" :count="1" />
                        </div>
                        <p v-if="review.body" class="mt-2 whitespace-pre-wrap break-words text-sm">
                            {{ review.body }}
                        </p>
                        <span class="mt-1 block text-xs text-mu">{{ relativeTime(review.created_at) }}</span>
                    </article>
                </div>

                <p
                    v-if="catalog.reviews.length === 0"
                    class="rounded-[22px] border border-ln bg-sf px-4 py-8 text-center text-sm text-mu"
                >
                    Belum ada ulasan. Jadilah yang pertama.
                </p>
            </section>
        </template>
    </div>
</template>
