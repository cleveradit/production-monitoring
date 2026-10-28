<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ArcElement, Chart, DoughnutController, Legend, Tooltip } from 'chart.js';
import { formatNumber } from '../utils/format.js';

Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

const props = defineProps({
    breakdown: { type: Array, required: true },
});

const statusColors = {
    RUNNING: '#3b82f6',
    FINISHED: '#10b981',
    OPEN: '#f59e0b',
    CANCELLED: '#94a3b8',
};

const canvas = ref(null);
let chart = null;

function chartData() {
    return {
        labels: props.breakdown.map((row) => row.status),
        datasets: [
            {
                data: props.breakdown.map((row) => row.total),
                backgroundColor: props.breakdown.map((row) => statusColors[row.status] ?? '#cbd5e1'),
                borderColor: '#ffffff',
                borderWidth: 2,
            },
        ],
    };
}

onMounted(() => {
    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: chartData(),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ${formatNumber(ctx.parsed)}`,
                    },
                },
            },
        },
    });
});

watch(
    () => props.breakdown,
    () => {
        if (!chart) return;
        chart.data = chartData();
        chart.update();
    },
);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="relative h-72">
        <canvas ref="canvas" role="img" aria-label="Donut chart of work order count per status"></canvas>
    </div>
</template>
