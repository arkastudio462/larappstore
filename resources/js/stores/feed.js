import { defineStore } from 'pinia';
import { api, errorMessage, validationErrors } from '@/lib/api';

/**
 * Sumber tunggal untuk timeline, postingan, dan komentar.
 *
 * Setiap aksi mengembalikan `{ ok, message }` agar halaman cukup memeriksa
 * `ok` tanpa perlu menangani bentuk error axios sendiri.
 */
export const useFeedStore = defineStore('feed', {
    state: () => ({
        items: [],
        scope: 'all',
        type: 'all',
        nextCursor: null,
        hasMore: false,
        loading: false,
        loadingMore: false,
        ready: false,
        error: '',
        errors: {},
        post: null,
        postLoading: false,
        comments: [],
        commentsLoaded: false,
        commentsLoading: false,
    }),

    actions: {
        async load({ scope = this.scope, type = this.type, more = false } = {}) {
            this.scope = scope;
            this.type = type;

            if (more) {
                if (!this.hasMore || this.loadingMore) {
                    return { ok: true, message: '' };
                }

                this.loadingMore = true;
            } else {
                this.loading = true;
                this.error = '';
            }

            try {
                const params = { scope, type };

                if (more) {
                    params.cursor = this.nextCursor;
                }

                const { data } = await api.get('/feed', { params });

                this.items = more ? [...this.items, ...data.data] : data.data;
                this.nextCursor = data.meta.next_cursor;
                this.hasMore = data.meta.has_more;

                return { ok: true, message: '' };
            } catch (error) {
                this.error = errorMessage(error);

                return { ok: false, message: this.error };
            } finally {
                this.loading = false;
                this.loadingMore = false;
                this.ready = true;
            }
        },

        async refresh() {
            return this.load({ scope: this.scope, type: this.type });
        },

        async loadMore() {
            return this.load({ more: true });
        },

        async loadPost(id) {
            this.postLoading = true;
            this.post = null;
            this.comments = [];
            this.commentsLoaded = false;

            try {
                const { data } = await api.get(`/posts/${id}`);
                this.post = data.data;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.postLoading = false;
            }
        },

        async createPost({ body, status = 'draft', media = null }) {
            const payload = { body, status };

            if (media && media.length > 0) {
                payload.media = media;
            }

            try {
                const { data } = await api.post('/posts', payload);

                if (status === 'published') {
                    this.items.unshift({
                        id: `local-${data.data.id}`,
                        subject_type: 'post',
                        post: data.data,
                        created_at: data.data.created_at,
                    });
                }

                return { ok: true, message: data.message, post: data.data };
            } catch (error) {
                this.errors = validationErrors(error);

                return {
                    ok: false,
                    message: Object.keys(this.errors).length > 0 ? 'Periksa kembali isian.' : errorMessage(error),
                };
            }
        },

        async updatePost(id, payload) {
            try {
                const { data } = await api.put(`/posts/${id}`, payload);

                if (this.post?.id === id) {
                    this.post = data.data;
                }

                return { ok: true, message: data.message, post: data.data };
            } catch (error) {
                this.errors = validationErrors(error);

                return {
                    ok: false,
                    message: Object.keys(this.errors).length > 0 ? 'Periksa kembali isian.' : errorMessage(error),
                };
            }
        },

        async deletePost(id) {
            try {
                const { data } = await api.delete(`/posts/${id}`);

                this.items = this.items.filter((item) => item.post?.id !== id);

                if (this.post?.id === id) {
                    this.post = null;
                }

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async publishPost(id) {
            try {
                const { data } = await api.post(`/posts/${id}/publish`);

                if (this.post?.id === id) {
                    this.post = data.data;
                }

                return { ok: true, message: data.message, post: data.data };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async toggleLike(postId) {
            try {
                const { data } = await api.post('/likes', {
                    likeable_type: 'post',
                    likeable_id: postId,
                });

                this.applyLike(postId, data.data.is_liked, data.data.likes_count);

                return { ok: true, message: data.message, data: data.data };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        async loadComments(postId) {
            this.commentsLoading = true;

            try {
                const { data } = await api.get(`/posts/${postId}/comments`);
                this.comments = data.data;
                this.commentsLoaded = true;

                return { ok: true, message: '' };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            } finally {
                this.commentsLoading = false;
            }
        },

        async addComment(postId, { body, parentId = null }) {
            try {
                const payload = { body };

                if (parentId) {
                    payload.parent_id = parentId;
                }

                const { data } = await api.post(`/posts/${postId}/comments`, payload);

                if (parentId) {
                    const parent = this.comments.find((comment) => comment.id === parentId);

                    if (parent) {
                        parent.replies = [...(parent.replies ?? []), data.data];
                    }
                } else {
                    this.comments = [data.data, ...this.comments];
                }

                if (this.post?.id === postId) {
                    this.post.comments_count = (this.post.comments_count ?? 0) + 1;
                }

                return { ok: true, message: data.message };
            } catch (error) {
                this.errors = validationErrors(error);

                return {
                    ok: false,
                    message: Object.keys(this.errors).length > 0 ? 'Periksa kembali isian.' : errorMessage(error),
                };
            }
        },

        async deleteComment(comment) {
            try {
                const { data } = await api.delete(`/comments/${comment.id}`);

                if (comment.parent_id) {
                    this.comments = this.comments.map((parent) =>
                        parent.id === comment.parent_id
                            ? { ...parent, replies: (parent.replies ?? []).filter((r) => r.id !== comment.id) }
                            : parent,
                    );
                } else {
                    this.comments = this.comments.filter((item) => item.id !== comment.id);
                }

                if (this.post) {
                    this.post.comments_count = Math.max(0, (this.post.comments_count ?? 1) - 1);
                }

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        /**
         * Laporkan konten ke admin (PRD §4.1).
         */
        async report(type, id, reason) {
            try {
                const { data } = await api.post('/reports', {
                    reportable_type: type,
                    reportable_id: id,
                    reason,
                });

                return { ok: true, message: data.message };
            } catch (error) {
                return { ok: false, message: errorMessage(error) };
            }
        },

        /**
         * Perbarui status suka pada objek yang sama supaya kartu di feed dan
         * halaman detail ikut berubah tanpa memuat ulang data.
         */
        applyLike(postId, isLiked, likesCount) {
            const update = (post) => {
                if (!post || post.id !== postId) {
                    return;
                }

                post.is_liked = isLiked;
                post.likes_count = likesCount;
            };

            update(this.post);
            this.items.forEach((item) => update(item.post));
        },
    },
});
