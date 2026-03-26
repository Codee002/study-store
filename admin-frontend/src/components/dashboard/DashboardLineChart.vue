<template>
  <div class="card card-soft h-100">
    <div class="card-body">
      <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
          <div class="eyebrow">{{ subtitle }}</div>
          <div class="fw-semibold">{{ title }}</div>
        </div>
        <slot name="badge"></slot>
      </div>
      <div class="chart-wrap" :style="{ height: height + 'px' }">
        <canvas ref="canvas"></canvas>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const props = defineProps({
  title: { type: String, default: "" },
  subtitle: { type: String, default: "" },
  labels: { type: Array, default: () => [] },
  datasets: { type: Array, default: () => [] },
  height: { type: Number, default: 320 },
  showLegend: { type: Boolean, default: false },
});

const canvas = ref(null);
let chart;

const buildDatasets = (ctx) =>
  props.datasets.map((ds, index) => {
    const baseColor = ds.color || ["#3b82f6", "#f59e0b", "#10b981", "#8b5cf6"][index % 4];
    const gradient = ctx?.createLinearGradient(0, 0, 0, props.height || 320);
    if (gradient) {
      gradient.addColorStop(0, `${baseColor}33`);
      gradient.addColorStop(1, `${baseColor}05`);
    }

    return {
      borderColor: baseColor,
      backgroundColor: ds.backgroundColor || gradient || baseColor,
      borderWidth: ds.borderWidth ?? 2,
      tension: ds.tension ?? 0.35,
      pointRadius: ds.pointRadius ?? 0,
      pointHoverRadius: ds.pointHoverRadius ?? 3,
      fill: ds.fill ?? true,
      ...ds,
    };
  });

const renderChart = () => {
  if (!canvas.value) return;
  const ctx = canvas.value.getContext("2d");
  if (chart) chart.destroy();

  const hasSecondaryAxis = props.datasets.some((ds) => ds.yAxisID && ds.yAxisID !== "y");

  chart = new Chart(ctx, {
    type: "line",
    data: {
      labels: props.labels,
      datasets: buildDatasets(ctx),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: props.showLegend,
          position: "bottom",
          labels: { boxWidth: 12 },
        },
        tooltip: {
          mode: "index",
          intersect: false,
          callbacks: {
            label: (context) => {
              const val = context.parsed.y ?? 0;
              if (context.dataset.yLabel === "money") {
                return `${context.dataset.label}: ${Number(val).toLocaleString("vi-VN")} ₫`;
              }
              return `${context.dataset.label}: ${val}`;
            },
          },
        },
      },
      interaction: { intersect: false, mode: "index" },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: "var(--font-color)" },
        },
        y: {
          grid: { color: "rgba(0,0,0,0.06)" },
          ticks: { color: "var(--font-color)" },
        },
        ...(hasSecondaryAxis
          ? {
              y1: {
                position: "right",
                grid: { display: false },
                ticks: { color: "var(--font-color)" },
              },
            }
          : {}),
      },
    },
  });
};

watch(
  () => [props.labels, props.datasets],
  () => renderChart(),
  { deep: true }
);

onMounted(renderChart);
onBeforeUnmount(() => chart?.destroy());
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.chart-wrap {
  position: relative;
}

.eyebrow {
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-size: 0.78rem;
  color: var(--font-extra-color);
}
</style>
