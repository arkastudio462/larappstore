<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CommentItem from '@/components/feed/CommentItem.vue';
import PostCard from '@/components/feed/PostCard.vue';
import Icon from '@/components/ui/Icon.vue';
import { useFeedStore } from '@/stores/feed';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const feed = useFeedStore();
const toast = useToastStore();

const postId = computed(() => Number(route.params.id));

const body = ref('');
const sending = ref(false);
const error = ref('');

const replyTo = ref(null);
const replyBody = ref('');
const replySending = ref(false);

onMounted(load);

async function load() {
    const result = await feed.loadPost(postId.value);

    if (!result.ok) {
        error.value = result.message;
        return;
    }

    feed.loadComments(postId.value);
}

async function submit() {
    if (!body.value.trim() || sending.value) {
        return;
    }

    sending.value = true;
    const result = await feed.addComment(postId.value, { body: body.value.trim() });
    sending.value = false;

    if (result.ok) {
        body.value = '';
    }

    toast.show(result.message);
}

function startReply(comment) {
    replyTo.value = comment.id;
    replyBody.value = '';
}

async function submitReply() {
    if (!replyBody.value.trim() || replySending.value) {
        return;
    }

    replySending.value = true;
    const result = await feed.addComment(postId.value, {
        body: replyBody.value.trim(),
        parentId: replyTo.value,
    });
    replySending.value = false;

    if (result.ok) {
        replyTo.value = null;
        replyBody.value = '';
    }

    toast.show(result.message);
}

async function remove(comment) {
    if (!window.confirm('Hapus komentar ini?')) {
        return;
    }

    const result = await feed.deleteComment(comment);
    toast.show(result.message);
}
</script>

<template>
    <div class="w-full px-4 pb-28 pt-4 lg:pb-10 lg:pt-8">
        <header class="mb-4 flex items-center gap-2.5">
            <button class="ib" type="button" aria-label="Kembali" @click="router.back()">
                <Icon name="back" />
            </button>
            <b class="text-[15px]">Postingan</b>
        </header>

        <div
            v-if="error"
            class="rounded-[22px] border border-ln bg-sf px-4 py-10 text-center text-sm text-mu"
        >
            {{ error }}
        </div>

        <div v-else-if="feed.postLoading" class="h-40 animate-pulse rounded-[22px] border border-ln bg-sf"></div>

        <template v-else-if="feed.post">
            <PostCard :post="feed.post" :linkable="false" @removed="router.push('/')" />

            <section class="mt-4 rounded-[22px] border border-ln bg-sf p-3.5">
                <b class="mb-2.5 block text-[15px]">
                    Komentar ({{ feed.post.comments_count ?? 0 }})
                </b>

                <textarea
                    v-model="body"
                    rows="2"
                    maxlength="1000"
                    placeholder="Tulis komentar…"
                    aria-label="Tulis komentar"
                    class="w-full resize-none rounded-2xl border border-ln bg-bg px-3.5 py-2.5 text-tx outline-none transition focus:border-ol placeholder:text-mu"
                ></textarea>

                <div class="mt-2 flex justify-end">
                    <button
                        class="btn"
                        type="button"
                        :disabled="sending || !body.trim()"
                        @click="submit"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Icon name="send" />
                            {{ sending ? 'Mengirim…' : 'Kirim' }}
                        </span>
                    </button>
                </div>
            </section>

            <section v-if="feed.commentsLoading" class="mt-4 space-y-4" aria-hidden="true">
                <div v-for="n in 2" :key="n" class="h-16 animate-pulse rounded-2xl bg-sf"></div>
            </section>

            <section v-else-if="feed.comments.length" class="mt-4 space-y-4">
                <div v-for="comment in feed.comments" :key="comment.id">
                    <CommentItem :comment="comment" @reply="startReply" @delete="remove" />

                    <CommentItem
                        v-for="reply in comment.replies"
                        :key="reply.id"
                        :comment="reply"
                        compact
                        @delete="remove"
                    />

                    <div v-if="replyTo === comment.id" class="ml-9 mt-2.5 flex gap-2">
                        <input
                            v-model="replyBody"
                            type="text"
                            maxlength="1000"
                            placeholder="Tulis balasan…"
                            aria-label="Tulis balasan"
                            class="h-10 min-w-0 flex-1 rounded-2xl border border-ln bg-bg px-3.5 text-sm text-tx outline-none transition focus:border-ol placeholder:text-mu"
                            @keyup.enter="submitReply"
                        />
                        <button class="btn" type="button" :disabled="replySending" @click="submitReply">
                            Kirim
                        </button>
                    </div>
                </div>
            </section>

            <p
                v-else-if="feed.commentsLoaded"
                class="mt-4 rounded-[22px] border border-ln bg-sf px-4 py-8 text-center text-sm text-mu"
            >
                Belum ada komentar. Jadilah yang pertama.
            </p>
        </template>
    </div>
</template>
