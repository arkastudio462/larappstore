<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '@/components/ui/Icon.vue';
import { initials, mediaUrl, relativeTime } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useFeedStore } from '@/stores/feed';
import { useToastStore } from '@/stores/toast';

const props = defineProps({
    post: {
        type: Object,
        required: true,
    },
    linkable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['removed']);

const auth = useAuthStore();
const feed = useFeedStore();
const toast = useToastStore();
const router = useRouter();

const liking = ref(false);

const canDelete = computed(() => props.post.author?.is_self === true || auth.isAdmin);

const media = computed(() => (props.post.media ?? []).filter((item) => item?.path));

const timestamp = computed(() => props.post.published_at ?? props.post.created_at);

async function toggleLike() {
    if (liking.value) {
        return;
    }

    liking.value = true;
    const result = await feed.toggleLike(props.post.id);
    liking.value = false;

    if (result.ok && result.data) {
        props.post.is_liked = result.data.is_liked;
        props.post.likes_count = result.data.likes_count;
    } else {
        toast.show(result.message);
    }
}

function open() {
    if (props.linkable) {
        router.push(`/postingan/${props.post.id}`);
    }
}

async function share() {
    try {
        await navigator.clipboard.writeText(`${window.location.origin}/postingan/${props.post.id}`);
        toast.show('Tautan postingan disalin.');
    } catch {
        toast.show('Tidak bisa menyalin tautan.');
    }
}

async function remove() {
    if (!window.confirm('Hapus postingan ini?')) {
        return;
    }

    const result = await feed.deletePost(props.post.id);
    toast.show(result.message);

    if (result.ok) {
        emit('removed', props.post.id);
    }
}

const editing = ref(false);
const editBody = ref('');
const savingEdit = ref(false);

function startEdit() {
    editBody.value = props.post.body ?? '';
    editing.value = true;
}

async function saveEdit() {
    if (!editBody.value.trim() || savingEdit.value) {
        return;
    }

    savingEdit.value = true;
    const result = await feed.updatePost(props.post.id, { body: editBody.value.trim() });
    savingEdit.value = false;

    if (result.ok) {
        Object.assign(props.post, result.post);
        editing.value = false;
        toast.show(result.message || 'Postingan diperbarui.');
    } else {
        toast.show(result.message);
    }
}

async function report() {
    const reason = window.prompt('Kenapa konten ini melanggar aturan?');

    if (!reason || !reason.trim()) {
        return;
    }

    const result = await feed.report('post', props.post.id, reason.trim());
    toast.show(result.message);
}

async function publish() {
    const result = await feed.publishPost(props.post.id);
    toast.show(result.message);

    if (result.ok && result.post) {
        Object.assign(props.post, result.post);
    }
}

</script>

<template>
    <article class="mb-3 rounded-[22px] border border-ln bg-sf p-3.5">
        <header class="mb-2.5 flex items-center gap-2.5">
            <RouterLink
                v-if="linkable"
                class="grid h-9 w-9 flex-none place-items-center rounded-full bg-ink text-[13px] font-extrabold text-on-ink"
                :to="`/u/${post.author?.username}`"
            >
                {{ initials(post.author?.name) }}
            </RouterLink>
            <span
                v-else
                class="grid h-9 w-9 flex-none place-items-center rounded-full bg-ink text-[13px] font-extrabold text-on-ink"
            >
                {{ initials(post.author?.name) }}
            </span>

            <div class="min-w-0 flex-1">
                <b class="block truncate">{{ post.author?.name }}</b>
                <span class="block truncate text-xs text-mu">
                    @{{ post.author?.username }} · {{ relativeTime(timestamp) }}
                </span>
            </div>

            <span
                v-if="post.status === 'draft'"
                class="rounded-full bg-sf2 px-2.5 py-0.5 text-[11px] font-bold text-acc"
            >
                Draft
            </span>

            <button
                v-if="canDelete"
                class="grid h-8 w-8 flex-none place-items-center rounded-xl border border-ln bg-sf text-mu"
                type="button"
                aria-label="Edit postingan"
                @click="startEdit"
            >
                <Icon name="pencil" />
            </button>

            <button
                v-if="canDelete"
                class="grid h-8 w-8 flex-none place-items-center rounded-xl border border-ln bg-sf text-mu"
                type="button"
                aria-label="Hapus postingan"
                @click="remove"
            >
                <Icon name="trash" />
            </button>
        </header>

        <div v-if="editing" class="mb-2.5">
            <textarea
                v-model="editBody"
                rows="3"
                maxlength="5000"
                aria-label="Edit tulisan postingan"
                class="w-full rounded-2xl border border-ln bg-bg px-3.5 py-2.5 text-tx outline-none placeholder:text-mu"
            ></textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button class="btn btn-ghost" type="button" @click="editing = false">Batal</button>
                <button class="btn" type="button" :disabled="savingEdit" @click="saveEdit">
                    {{ savingEdit ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </div>
        </div>

        <p v-else-if="post.body" class="mb-2.5 whitespace-pre-wrap break-words">{{ post.body }}</p>

        <div v-if="media.length" class="mb-2.5 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
            <template v-for="item in media" :key="item.path">
                <img
                    v-if="item.type !== 'video' && item.type !== 'audio'"
                    :src="mediaUrl(item.path)"
                    alt=""
                    class="aspect-square w-full rounded-2xl border border-ln object-cover"
                    loading="lazy"
                />
                <video
                    v-else-if="item.type === 'video'"
                    :src="mediaUrl(item.path)"
                    controls
                    class="aspect-video w-full rounded-2xl border border-ln bg-black"
                ></video>
                <audio
                    v-else
                    :src="mediaUrl(item.path)"
                    controls
                    class="w-full self-center"
                ></audio>
            </template>
        </div>

        <div class="flex gap-1">
            <button class="act" type="button" :aria-pressed="post.is_liked" @click="toggleLike">
                <Icon name="heart" />
                <span>{{ post.likes_count ?? 0 }}</span>
            </button>
            <button class="act" type="button" @click="open">
                <Icon name="chat" />
                {{ post.comments_count ?? 0 }}
            </button>
            <button class="act" type="button" @click="share">
                <Icon name="share" />
                Bagikan
            </button>
            <button
                v-if="post.status === 'draft'"
                class="act ml-auto"
                type="button"
                aria-label="Terbitkan"
                @click="publish"
            >
                <Icon name="send" />
                Terbitkan
            </button>
            <button
                v-else-if="!canDelete && auth.isAuthenticated"
                class="act ml-auto"
                type="button"
                @click="report"
            >
                Laporkan
            </button>
        </div>
    </article>
</template>
