<script setup>
import { computed, onMounted } from 'vue';
import { useDashboard } from '../composables/useDashboard.js';
import { formatDate, formatNumber, formatPercent } from '../utils/format.js';
import KpiCard from './KpiCard.vue';
import StatusChart from './StatusChart.vue';
import TopMachineChart from './TopMachineChart.vue';
import TopMachineTable from './TopMachineTable.vue';
import TrendChart from './TrendChart.vue';

const { data, loading, error, load } = useDashboard();

onMounted(load);

const kpis = computed(() => {
    const summary = data.value?.summary;
    if (!summary) return [];

    const dataDateHint = `Data date: ${formatDate(summary.data_date)}`;

    return [
        { label: 'Total Machine', value: formatNumber(summary.total_machine), tone: 'slate' },
        { label: 'Running Work Order', value: formatNumber(summary.running_order), tone: 'blue' },
        { label: 'Finished Work Order', value: formatNumber(summary.finished_order), tone: 'green' },
        { label: 'Achievement', value: formatPercent(summary.achievement), hint: dataDateHint, tone: 'indigo' },
        { label: 'Good Qty', value: formatNumber(summary.today_good), hint: dataDateHint, tone: 'green' },
        { label: 'Reject Qty', value: formatNumber(summary.today_reject), hint: dataDateHint, tone: 'red' },
    ];
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">Production Monitoring</h1>
                <p class="text-sm text-slate-500">
                    PT XYZ Manufacturing
                    <template v-if="data?.summary?.data_date">
                        · Latest data {{ formatDate(data.summary.data_date) }}
                    </template>
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="loading"
                @click="load"
            >
                <svg
                    class="h-4 w-4"
                    :class="{ 'animate-spin': loading }"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                {{ loading ? 'Loading…' : 'Refresh' }}
            </button>
        </header>

        <div
            v-if="error && !loading"
            class="rounded-xl border border-rose-200 bg-rose-50 p-6 text-center"
            role="alert"
        >
            <p class="font-medium text-rose-800">Failed to load dashboard data.</p>
            <p class="mt-1 text-sm text-rose-700">{{ error }}</p>
            <button
                type="button"
                class="mt-4 rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700"
                @click="load"
            >
                Try again
            </button>
        </div>

        <div v-else-if="!data" class="animate-pulse space-y-6" aria-busy="true" aria-label="Loading dashboard">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="n in 6" :key="n" class="h-24 rounded-xl bg-slate-200"></div>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="h-80 rounded-xl bg-slate-200 lg:col-span-2"></div>
                <div class="h-80 rounded-xl bg-slate-200"></div>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="h-96 rounded-xl bg-slate-200"></div>
                <div class="h-96 rounded-xl bg-slate-200"></div>
            </div>
        </div>

        <main v-else class="space-y-6">
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6" aria-label="Key metrics">
                <KpiCard
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    :label="kpi.label"
                    :value="kpi.value"
                    :hint="kpi.hint"
                    :tone="kpi.tone"
                />
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Production Trend — Last 7 Days</h2>
                    <TrendChart :trend="data.trend_7_days" />
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Work Order Status</h2>
                    <StatusChart :breakdown="data.status_breakdown" />
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Top 10 Machines by Good Qty</h2>
                    <TopMachineChart class="lg:min-h-80 lg:flex-1" :machines="data.top_machines" />
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700">Top 10 Machine Performance</h2>
                    <TopMachineTable :machines="data.top_machines" />
                </div>
            </section>
        </main>
    </div>
</template>
