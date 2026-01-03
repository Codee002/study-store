<template>
  <header class="app-header border-bottom">
    <div class="container h-100 d-flex align-items-center gap-3">
      <!-- Brand -->
      <RouterLink
        to="/"
        class="brand d-flex align-items-center gap-2 text-decoration-none"
      >
        <span class="logo-circle">
          <i class="fa-solid fa-pencil"></i>
        </span>
        <div class="d-flex flex-column lh-sm">
          <span class="brand-name">Study Store</span>
          <span class="brand-sub">Văn phòng phẩm xinh</span>
        </div>
      </RouterLink>

      <!-- Nav (optional) -->
      <nav class="d-none d-lg-flex ms-2 gap-2">
        <a class="nav-pill" href="#">Trang chủ</a>
        <a class="nav-pill" href="#">Sản phẩm</a>
        <a class="nav-pill" href="#">Khuyến mãi</a>
        <a class="nav-pill" href="#">Liên hệ</a>
      </nav>

      <!-- Search -->
      <form
        class="ms-auto d-none d-md-block search-wrap"
        @submit.prevent="emitSearch"
      >
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0">
            <i class="fa-solid fa-magnifying-glass"></i>
          </span>
          <input
            v-model.trim="keyword"
            class="form-control border-start-0"
            type="text"
            placeholder="Tìm bút, vở, sticker..."
          />
          <button class="btn btn-main d-none d-lg-inline-flex" type="submit">
            Tìm
          </button>
        </div>
      </form>

      <!-- Actions -->
      <div class="d-flex align-items-center gap-2">
        <button class="icon-btn d-md-none" type="button" title="Tìm kiếm">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>

        <button
          class="icon-btn position-relative"
          type="button"
          title="Giỏ hàng"
        >
          <i class="fa-solid fa-cart-shopping"></i>
          <span class="badge rounded-pill bg-dark badge-cart">{{
            cartCount
          }}</span>
        </button>

        <div class="dropdown">
          <button
            class="user-btn dropdown-toggle"
            data-bs-toggle="dropdown"
            type="button"
          >
            <img class="avatar" :src="user.avatar" alt="avatar" />
            <span class="d-none d-lg-inline">{{ user.name }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <a class="dropdown-item" href="#"
                ><i class="fa-solid fa-user me-2"></i>Tài khoản</a
              >
            </li>
            <li>
              <a class="dropdown-item" href="#"
                ><i class="fa-solid fa-receipt me-2"></i>Đơn hàng</a
              >
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
              <a class="dropdown-item" href="#"
                ><i class="fa-solid fa-right-from-bracket me-2"></i>Đăng xuất</a
              >
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
  cartCount: { type: Number, default: 2 },
  user: {
    type: Object,
    default: () => ({
      name: "Guest",
      avatar: "https://i.pravatar.cc/80?img=12",
    }),
  },
});

const emit = defineEmits(["search"]);

const keyword = ref("");
function emitSearch() {
  emit("search", keyword.value);
}
</script>

<style scoped>
.app-header {
  height: var(--header-heigh);
  background: var(--main-extra-bg);
  position: sticky;
  top: 0;
  z-index: 1030;
  opacity: 1;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  filter: none !important;
}

.brand-name {
  font-weight: 800;
  color: var(--dark);
}
.brand-sub {
  font-size: 0.8rem;
  color: var(--font-extra-color);
}
.logo-circle {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  border-radius: 12px;
  color: var(--dark);
}

.nav-pill {
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  text-decoration: none;
  color: var(--font-color);
  border: 1px solid transparent;
}
.nav-pill:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}

.search-wrap .input-group {
  min-width: 360px;
}
.search-wrap .form-control,
.search-wrap .input-group-text {
  background: var(--main-extra-bg);
  border-color: var(--border-color);
}
.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 600;
}
.btn-main:hover {
  filter: var(--brightness);
}

.icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  color: var(--font-color);
}
.icon-btn:hover {
  background: var(--hover-color);
}

.badge-cart {
  position: absolute;
  top: -6px;
  right: -6px;
  font-size: 0.7rem;
  padding: 0.25rem 0.4rem;
}

.user-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.6rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  color: var(--font-color);
}
.user-btn:hover {
  background: var(--hover-color);
}
.avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
}
</style>
