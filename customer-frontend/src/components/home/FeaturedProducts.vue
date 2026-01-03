<template>
  <section class="container mt-4">
    <div
      class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3"
    >
      <div>
        <h3 class="h5 fw-bold mb-1">Sản phẩm nổi bật</h3>
        <div class="text-muted small">
          Mock data — sau này thay bằng API backend.
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button
          v-for="c in categories"
          :key="c"
          class="btn btn-sm btn-filter"
          :class="{ active: c === activeCategory }"
          @click="$emit('change-category', c)"
        >
          {{ c }}
        </button>
      </div>
    </div>

    <div class="row g-3">
      <div v-for="p in products" :key="p.id" class="col-12 col-sm-6 col-lg-3">
        <ProductCard :product="p" />
      </div>
    </div>
  </section>
</template>

<script setup>
import ProductCard from "./ProductCard.vue";

defineProps({
  products: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  activeCategory: { type: String, default: "Tất cả" },
});

defineEmits(["change-category"]);
</script>

<style scoped>
.btn-filter {
  border-radius: 999px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  color: var(--font-color);
}
.btn-filter:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}
.btn-filter.active {
  background: var(--main-color);
  border-color: var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}
</style>
