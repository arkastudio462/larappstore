<script setup>
import { ref } from 'vue';

const props = defineProps({
    app: {
        type: Object,
        required: true,
    },
});

const downloaded = ref(false);
</script>

<template>
    <article class="w-[150px] flex-none cursor-pointer rounded-[20px] border border-ln bg-sf p-2.5">
        <div
            class="mb-2 grid h-[110px] place-items-center overflow-hidden rounded-2xl text-[44px]"
            :style="props.app.icon ? null : { backgroundImage: props.app.grad }"
            aria-hidden="true"
        >
            <img v-if="props.app.icon" :src="props.app.icon" alt="" class="h-full w-full object-cover" />
            <span v-else>{{ props.app.emoji }}</span>
        </div>
        <b class="block truncate text-sm">{{ props.app.name }}</b>
        <span class="block truncate text-xs text-mu">{{ props.app.cat }} · {{ props.app.dl }} unduhan</span>
        <div class="mt-2 flex items-center justify-between">
            <span class="text-xs font-bold text-acc">{{ props.app.rate ? `★ ${props.app.rate}` : 'Baru' }}</span>
            <button class="btn" type="button" @click.stop="downloaded = !downloaded">
                {{ downloaded ? 'Terbuka' : 'Unduh' }}
            </button>
        </div>
    </article>
</template>
