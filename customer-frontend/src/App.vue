<script setup>
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import AppHeader from "@/components/layout/AppHeader.vue";

const cartCount = ref(0);
const route = useRoute();
const showHeader = computed(() => !route.meta?.hideHeader);

onMounted(() => {
  // cart count is fetched inside AppHeader; keep ref for prop compatibility
});
</script>

<template>
  <AppHeader v-if="showHeader" :cart-count="cartCount" />
  <router-view />
</template>

<style scoped></style>
