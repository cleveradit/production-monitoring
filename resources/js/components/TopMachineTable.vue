<script setup>
import { formatNumber, formatPercent } from '../utils/format.js';

defineProps({
    machines: { type: Array, required: true },
});
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="py-2 pr-3 font-medium">#</th>
                    <th scope="col" class="py-2 pr-3 font-medium">Machine</th>
                    <th scope="col" class="py-2 pr-3 text-right font-medium">Good Qty</th>
                    <th scope="col" class="py-2 font-medium">Achievement</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="(machine, index) in machines" :key="machine.machine_code">
                    <td class="py-2.5 pr-3 tabular-nums text-slate-500">{{ index + 1 }}</td>
                    <td class="py-2.5 pr-3">
                        <div class="font-medium text-slate-900">{{ machine.machine_code }}</div>
                        <div class="text-xs text-slate-500">{{ machine.machine_name }}</div>
                    </td>
                    <td class="py-2.5 pr-3 text-right tabular-nums">{{ formatNumber(machine.good_qty) }}</td>
                    <td class="min-w-32 py-2.5">
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{ width: `${Math.min(machine.achievement, 100)}%` }"
                                ></div>
                            </div>
                            <span class="w-16 text-right text-xs tabular-nums text-slate-700">
                                {{ formatPercent(machine.achievement) }}
                            </span>
                        </div>
                    </td>
                </tr>
                <tr v-if="machines.length === 0">
                    <td colspan="4" class="py-6 text-center text-slate-500">No data</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
