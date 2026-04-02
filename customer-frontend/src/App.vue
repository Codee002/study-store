<script setup>
import { ref, onMounted } from "vue";
import AppHeader from "@/components/layout/AppHeader.vue";

const cartCount = ref(0);

onMounted(() => {
  // cart count is fetched inside AppHeader; keep ref for prop compatibility
});
</script>

<template>
  <AppHeader :cart-count="cartCount" />
  <router-view />
</template>

<style scoped></style>
