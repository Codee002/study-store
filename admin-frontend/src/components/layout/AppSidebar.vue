<template>
  <aside class="sidebar p-3" :style="{ width: collapsed ? '84px' : '280px' }">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="d-flex align-items-center gap-2 me-3">
        <span class="badge rounded-pill brand-badge">SS</span>
        <span v-if="!collapsed" class="fw-semibold">StudyStore</span>
      </div>

      <button class="btn btn-sm btn-outline-secondary" @click="$emit('toggle')">
        <i class="fa-solid fa-arrow-left"></i>
      </button>
    </div>

    <div class="small text-uppercase opacity-75 mb-2" v-if="!collapsed">
      Menu
    </div>

    <nav class="nav flex-column gap-1">
      <RouterLink
        class="nav-link"
        :class="{ active: route.name === 'dashboard' }"
        to="/"
      >
        <i class="bi bi-speedometer2 me-2"></i>
        <span v-if="!collapsed">Dashboard</span>
      </RouterLink>

      <RouterLink
        class="nav-link"
        :class="{ active: route.name?.toString().startsWith('categories.') }"
        to="/categories"
      >
        <i class="fa-solid fa-folder-tree me-2"></i>
        <span v-if="!collapsed">Categories</span>
      </RouterLink>

      <RouterLink
        class="nav-link"
        :class="{ active: route.name?.toString().startsWith('suppliers.') }"
        to="/suppliers"
      >
        <i class="fa-solid fa-folder-tree me-2"></i>
        <span v-if="!collapsed">Suppliers</span>
      </RouterLink>

      <RouterLink
        class="nav-link"
        :class="{ active: route.name?.toString().startsWith('tiers.') }"
        to="/tiers"
      >
        <i class="fa-solid fa-folder-tree me-2"></i>
        <span v-if="!collapsed">Tiers</span>
      </RouterLink>

      <RouterLink
        class="nav-link"
        :class="{ active: route.name === 'orders' }"
        to="/orders"
      >
        <i class="bi bi-receipt me-2"></i>
        <span v-if="!collapsed">Orders</span>
        <span
          v-if="!collapsed"
          class="ms-auto badge bg-secondary-subtle text-secondary"
          >Soon</span
        >
      </RouterLink>

      <RouterLink
        class="nav-link"
        :class="{ active: route.name === 'products' }"
        to="/products"
      >
        <i class="bi bi-box-seam me-2"></i>
        <span v-if="!collapsed">Products</span>
        <span
          v-if="!collapsed"
          class="ms-auto badge bg-secondary-subtle text-secondary"
          >Soon</span
        >
      </RouterLink>
    </nav>

    <div
      class="mt-auto pt-3 border-top border-opacity-25 small"
      v-if="!collapsed"
    >
      <div class="opacity-75">Logged in</div>
      <div class="fw-semibold">Admin</div>
    </div>
  </aside>
</template>

<script setup>
import { useRoute } from "vue-router";
defineProps({ collapsed: { type: Boolean, default: false } });
const route = useRoute();
</script>

<style scoped>
.sidebar {
  transition: width 0.2s ease;
  background: var(--main-extra-bg);
  border-right: 1px solid var(--border-color);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  color: var(--font-color);
}
.brand-badge {
  background: var(--main-color);
  color: var(--dark);
}
.nav-link {
  border-radius: 0.65rem;
  padding: 0.55rem 0.75rem;
  color: var(--font-color);
  border: 1px solid transparent;
}
.nav-link:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}
.nav-link.active {
  background: color-mix(in srgb, var(--main-color) 18%, transparent);
  border-color: var(--hover-border-color);
}
</style>
