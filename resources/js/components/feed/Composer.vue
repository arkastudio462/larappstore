<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import Icon from '@/components/ui/Icon.vue';
import { api, errorMessage } from '@/lib/api';
import { initials } from '@/lib/format';
import { useAuthStore } from '@/stores/auth';
import { useFeedStore } from '@/stores/feed';
import { useToastStore } from '@/stores/toast';

const emit = defineEmits(['created']);

const auth = useAuthStore();
const feed = useFeedStore();
const toast = useToastStore();

const MAX = 5000;

const body = ref('');
const busy = ref(false);
const attachments = ref([]);
const uploading = ref(false);
const recording = ref(false);
let recorder = null;
let recordChunks = [];

const remaining = computed(() => MAX - body.value.length);
const canSubmit = computed(() => (body.value.trim().length > 0 || attachments.value.length > 0) && !busy.value && !uploading.value);

async function uploadFile(kind, file, type) {
    uploading.value = true;

    try {
        const form = new FormData();
        form.append('kind', kind);
        form.append('file', file);

        const res = await api.post('/uploads', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const { key } = res.data.data;

        attachments.value.push({
            path: key,
            type,
            preview: file.type.startsWith('image/') || file.type.startsWith('video/') ? URL.createObjectURL(file) : '',
            name: file.name,
        });
    } catch (error) {
        toast.show(errorMessage(error));
    } finally {
        uploading.value = false;
    }
}

function onFiles(event, kind, type) {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';

    for (const file of files.slice(0, 4 - attachments.value.length)) {
        uploadFile(kind, file, type);
    }
}

async function toggleRecording() {
    if (recording.value) {
        recorder?.stop();
        return;
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        recordChunks = [];
        recorder = new MediaRecorder(stream);

        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                recordChunks.push(event.data);
            }
        };

        recorder.onstop = async () => {
            stream.getTracks().forEach((track) => track.stop());
            recording.value = false;

            const blob = new Blob(recordChunks, { type: 'audio/webm' });
            const file = new File([blob], `voice-note-${Date.now()}.webm`, { type: 'audio/webm' });

            await uploadFile('audio', file, 'audio');
        };

        recorder.start();
        recording.value = true;
    } catch {
        toast.show('Mikrofon tidak tersedia.');
    }
}

function removeAttachment(index) {
    attachments.value.splice(index, 1);
}

onBeforeUnmount(() => {
    if (recorder && recording.value) {
        recorder.stop();
    }
});

async function submit(status) {
    if (!canSubmit.value) {
        return;
    }

    busy.value = true;
    const result = await feed.createPost({
        body: body.value.trim(),
        status,
        media: attachments.value.map((item) => ({ path: item.path, type: item.type })),
    });

    busy.value = false;

    if (result.ok) {
        body.value = '';
        attachments.value = [];
        toast.show(result.message || 'Postingan dibuat.');
        emit('created', result.post);
    } else {
        toast.show(result.message);
    }
}
</script>

<template>
    <section class="rounded-[22px] border border-ln bg-sf p-3.5">
        <div class="flex gap-2.5">
            <div
                class="grid h-9 w-9 flex-none place-items-center rounded-full bg-ink text-[13px] font-extrabold text-on-ink"
            >
                {{ initials(auth.user?.name) }}
            </div>
            <textarea
                v-model="body"
                rows="3"
                :maxlength="MAX"
                placeholder="Bagikan kabar, atau ceritakan aplikasi yang baru kamu rilis…"
                aria-label="Tulisan postingan"
                class="min-h-[72px] w-full resize-none bg-transparent text-tx outline-none placeholder:text-mu"
            ></textarea>
        </div>

        <div v-if="attachments.length" class="mt-2.5 flex flex-wrap gap-2">
            <div v-for="(item, i) in attachments" :key="item.path" class="relative">
                <img v-if="item.type === 'image'" :src="item.preview" alt="" class="h-16 w-16 rounded-xl object-cover" />
                <video v-else-if="item.type === 'video'" :src="item.preview" class="h-16 w-16 rounded-xl object-cover" muted></video>
                <span v-else class="grid h-16 w-16 place-items-center rounded-xl bg-sf2 text-[11px] font-bold">Audio</span>
                <button
                    class="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-ol text-[10px] text-bg"
                    type="button"
                    aria-label="Hapus lampiran"
                    @click="removeAttachment(i)"
                >
                    ✕
                </button>
            </div>
        </div>

        <div class="mt-2 flex items-center justify-between gap-2">
            <span class="text-xs" :class="remaining < 100 ? 'text-ol' : 'text-mu'">{{ remaining }}</span>
            <div class="flex items-center gap-1">
                <label class="ib cursor-pointer" aria-label="Lampirkan gambar">
                    <Icon name="image" />
                    <input type="file" accept="image/*" class="hidden" @change="onFiles($event, 'image', 'image')" />
                </label>
                <label class="ib cursor-pointer" aria-label="Lampirkan video">
                    <Icon name="video" />
                    <input type="file" accept="video/*" class="hidden" @change="onFiles($event, 'video', 'video')" />
                </label>
                <button class="ib" type="button" :aria-label="recording ? 'Berhenti merekam' : 'Rekam voice note'" @click="toggleRecording">
                    <Icon :name="recording ? 'stop' : 'mic'" />
                </button>
                <button class="btn btn-ghost" type="button" :disabled="!canSubmit" @click="submit('draft')">
                    Simpan draft
                </button>
                <button class="btn" type="button" :disabled="!canSubmit" @click="submit('published')">
                    <span class="inline-flex items-center gap-1.5">
                        <Icon name="send" />
                        {{ busy ? 'Mengirim…' : 'Terbitkan' }}
                    </span>
                </button>
            </div>
        </div>

        <p v-if="recording" class="mt-2 text-xs font-bold text-ol">Merekam… ketuk lagi untuk berhenti.</p>
        <p v-if="uploading" class="mt-2 text-xs text-mu">Mengunggah berkas…</p>
    </section>
</template>
