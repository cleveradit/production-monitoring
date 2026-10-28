<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: 'slate' },
});

const tones = {
    slate: 'bg-slate-400',
    blue: 'bg-blue-500',
    green: 'bg-emerald-500',
    amber: 'bg-amber-500',
    red: 'bg-rose-500',
    indigo: 'bg-indigo-500',
};

const accent = computed(() => tones[props.tone] ?? tones.slate);
</script>

<template>
    <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <span class="absolute inset-y-0 left-0 w-1" :class="accent" aria-hidden="true"></span>
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</p>
        <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
