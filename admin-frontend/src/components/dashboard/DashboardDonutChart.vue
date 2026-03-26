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
      <div class="small opacity-75 mt-2" v-if="legend">
        <div class="legend-item" v-for="(label, idx) in labels" :key="label">
          <span class="dot" :style="{ background: legendColors[idx] }"></span>
          <span>{{ label }}</span>
          <span class="ms-auto fw-semibold">{{ legendValues[idx] ?? 0 }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch, computed } from "vue";
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const props = defineProps({
  title: { type: String, default: "" },
  subtitle: { type: String, default: "" },
  labels: { type: Array, default: () => [] },
  data: { type: Array, default: () => [] },
  colors: { type: Array, default: () => [] },
  height: { type: Number, default: 260 },
  legend: { type: Boolean, default: true },
});

const canvas = ref(null);
let chart;

const palette = ["#3b82f6", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#0ea5e9"];
const computedColors = computed(() =>
  props.colors.length ? props.colors : palette.slice(0, props.data.length || 1)
);
const legendValues = computed(() => props.data || []);
const legendColors = computed(() =>
  legendValues.value.map((_, idx) => computedColors.value[idx] ?? palette[idx % palette.length])
);

const renderChart = () => {
  if (!canvas.value) return;
  const ctx = canvas.value.getContext("2d");
  if (chart) chart.destroy();

  chart = new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: props.labels,
      datasets: [
        {
          data: props.data,
          backgroundColor: computedColors.value,
          borderWidth: 0,
          hoverOffset: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "65%",
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const value = ctx.parsed ?? 0;
              const label = ctx.label || "";
              return `${label}: ${value}`;
            },
          },
        },
      },
    },
  });
};

watch(
  () => [props.labels, props.data, props.colors],
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
.legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0;
}
.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.eyebrow {
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-size: 0.78rem;
  color: var(--font-extra-color);
}
</style>
