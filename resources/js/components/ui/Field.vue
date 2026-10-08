<script setup>
import { computed, ref } from 'vue';
import Icon from '@/components/ui/Icon.vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: 'off',
    },
    name: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => `field-${props.name || props.label.toLowerCase().replace(/\s+/g, '-')}`);
const errorId = computed(() => `${inputId.value}-error`);
const isPassword = computed(() => props.type === 'password');

const revealed = ref(false);
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));
const inputEl = ref(null);

function onInput(event) {
    emit('update:modelValue', event.target.value);
}

defineExpose({
    focus: () => inputEl.value?.focus(),
});
</script>

<template>
    <span class="block">
        <label :for="inputId" class="mb-1.5 block text-[13px] font-semibold text-mu2">{{ label }}</label>

        <span class="relative block">
            <input
                :id="inputId"
                ref="inputEl"
                :name="name"
                :type="inputType"
                :value="modelValue"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? errorId : undefined"
                class="h-11 w-full rounded-2xl border bg-sf px-3.5 text-tx outline-none transition placeholder:text-mu"
                :class="[
                    error ? 'border-ol bg-sf2' : 'border-ln focus:border-ol',
                    isPassword ? 'pr-11' : '',
                ]"
                @input="onInput"
            />

            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 grid w-11 place-items-center text-mu transition hover:text-tx"
                :aria-label="revealed ? 'Sembunyikan password' : 'Tampilkan password'"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <Icon :name="revealed ? 'eye-off' : 'eye'" />
            </button>
        </span>

        <span v-if="error" :id="errorId" class="mt-1 block text-xs font-semibold text-ol">{{ error }}</span>
    </span>
</template>
