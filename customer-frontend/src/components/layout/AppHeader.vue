<template>
  <header class="app-header border-bottom">
    <div class="container h-100 d-flex align-items-center gap-3">
      <RouterLink to="/home" class="brand d-flex align-items-center gap-2 text-decoration-none">
        <span class="logo-circle">
          <i class="fa-solid fa-pencil"></i>
        </span>
        <div class="d-flex flex-column lh-sm">
          <span class="brand-name">Study Store</span>
          <span class="brand-sub">Văn phòng phẩm xinh</span>
        </div>
      </RouterLink>

      <nav class="d-none d-lg-flex ms-2 gap-2">
        <RouterLink class="nav-pill" to="/home">Trang chủ</RouterLink>
        <RouterLink class="nav-pill" to="/products">Sản phẩm</RouterLink>
        <RouterLink class="nav-pill" to="/orders">Đơn hàng</RouterLink>
        <RouterLink class="nav-pill" to="/price-quotation">Báo giá</RouterLink>
        <RouterLink class="nav-pill" to="/contact">Liên hệ</RouterLink>
      </nav>

      <form class="ms-auto d-none d-md-block search-wrap" @submit.prevent="emitSearch">
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
          <button class="btn btn-main d-none d-lg-inline-flex" type="submit">Tìm</button>
        </div>
      </form>

      <div class="d-flex align-items-center gap-2">
        <button class="icon-btn d-md-none" type="button" title="Tim kiem">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>

        <RouterLink
          to="/cart"
          class="icon-btn position-relative d-inline-flex align-items-center justify-content-center text-decoration-none"
          title="Gio hang"
        >
          <i class="fa-solid fa-cart-shopping"></i>
          <span class="badge rounded-pill bg-dark badge-cart">{{ cartCount }}</span>
        </RouterLink>

        <div class="dropdown">
          <button class="user-btn dropdown-toggle" data-bs-toggle="dropdown" type="button">
            <img class="avatar" :src="resolvedAvatar" alt="avatar" @click.stop="goAccountSettings" />
            <span class="d-none d-lg-inline" @click.stop="goAccountSettings">{{ localUser.name }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <RouterLink class="dropdown-item" to="/account/settings">
                <i class="fa-solid fa-user me-2"></i>Tài khoản
              </RouterLink>
            </li>
            <li>
              <RouterLink class="dropdown-item" to="/orders">
                <i class="fa-solid fa-receipt me-2"></i>Đơn hàng
              </RouterLink>
            </li>
            <li>
              <RouterLink class="dropdown-item" to="/price-quotation">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i>Báo giá
              </RouterLink>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
              <button class="dropdown-item" type="button" @click="onLogout">
                <i class="fa-solid fa-right-from-bracket me-2"></i>Đăng xuất
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";
import authService from "@/services/auth.service";

const router = useRouter();

const props = defineProps({
  cartCount: { type: Number, default: 0 },
  user: {
    type: Object,
    default: () => ({
      name: "Khach",
      avatar: "/default-user-avatar.svg",
    }),
  },
});

const emit = defineEmits(["search"]);
const keyword = ref("");

const DEFAULT_USER = {
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
};
const HEADER_ME_SYNC_KEY = "customer_header_me_synced";

const localUser = ref(readStoredUser() || props.user || DEFAULT_USER);

function readStoredUser() {
  try {
    const raw = localStorage.getItem("currentUser");
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function normalizeUserPayload(res) {
  const me = res?.data ?? res;
  const meUser = me?.user ?? me ?? {};
  return {
    ...meUser,
    name: meUser?.name || "Guest",
    avatar:
      meUser?.avatar ||
      meUser?.profile?.avatar ||
      "/default-user-avatar.svg",
    tier_id: meUser?.tier_id ?? meUser?.profile?.tier ?? null,
    profile: meUser?.profile ?? null,
  };
}

const resolvedAvatar = computed(
  () =>
    localUser.value?.avatar ||
    localUser.value?.profile?.avatar ||
    props.user?.avatar ||
    props.user?.profile?.avatar ||
    "/default-user-avatar.svg",
);

function emitSearch() {
  emit("search", keyword.value);
}

async function loadMe() {
  if (!authService.isLoggin()) return;

  try {
    const res = await authService.me();
    const normalized = normalizeUserPayload(res);
    localUser.value = normalized;
    localStorage.setItem("currentUser", JSON.stringify(normalized));
  } catch {
    // Keep current user data if request fails.
  }
}

function goAccountSettings() {
  router.push("/account/settings");
}

function onUserUpdated() {
  const stored = readStoredUser();
  if (stored) {
    localUser.value = stored;
    return;
  }
  loadMe();
}

async function onLogout() {
  try {
    await authService.logout();
    sessionStorage.removeItem(HEADER_ME_SYNC_KEY);
    localUser.value = { ...DEFAULT_USER };
    await router.push({ name: "login" });
  } catch {
    await Swal.fire("Lï¿½-i", "Äï¿½fng xuáº¥t tháº¥t báº¡i. Vui lÃ²ng thá»­ láº¡i.", "error");
  }
}

onMounted(() => {
  const stored = readStoredUser();
  if (stored) {
    localUser.value = stored;
  }

  const synced = sessionStorage.getItem(HEADER_ME_SYNC_KEY) === "1";
  if (!synced) {
    loadMe();
    sessionStorage.setItem(HEADER_ME_SYNC_KEY, "1");
  }
  window.addEventListener("customer-user-updated", onUserUpdated);
});

onBeforeUnmount(() => {
  window.removeEventListener("customer-user-updated", onUserUpdated);
});
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

.nav-pill.router-link-active,
.nav-pill.router-link-exact-active {
  background: var(--main-color);
  border-color: var(--hover-border-color);
  color: var(--dark);
  font-weight: 600;
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



