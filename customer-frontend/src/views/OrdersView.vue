<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" />

    <main class="container py-4">
      <section class="orders-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h1 class="orders-title mb-1">Đơn hàng đã đặt</h1>
            <div class="text-muted small">{{ filteredOrders.length }} đơn hàng</div>
          </div>
          <RouterLink to="/products" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-2"></i>Tiếp tục mua sắm
          </RouterLink>
        </div>

        <div class="status-tabs mb-3">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            type="button"
            class="tab-btn"
            :class="{ active: activeStatus === tab.value }"
            @click="activeStatus = tab.value"
          >
            {{ tab.label }}
            <span class="tab-count">{{ countByStatus(tab.value) }}</span>
          </button>
        </div>

        <div v-if="loading" class="empty-box text-center">
          <i class="fa-solid fa-spinner fa-spin empty-icon"></i>
          <p class="mb-0 text-muted">Đang tải danh sách đơn hàng...</p>
        </div>

        <div v-else-if="!filteredOrders.length" class="empty-box text-center">
          <i class="fa-solid fa-box-open empty-icon"></i>
          <h5 class="mb-2">Không có đơn hàng</h5>
          <p class="text-muted mb-3">Trạng thái này hiện chưa có đơn hàng nào.</p>
          <RouterLink to="/products" class="btn btn-main">Mua ngay</RouterLink>
        </div>

        <div v-else class="order-list">
          <RouterLink
            v-for="order in filteredOrders"
            :key="`order-${order.id}`"
            :to="orderDetailTo(order)"
            class="order-card"
          >
            <div class="order-top">
              <div>
                <div class="fw-semibold">Mã đơn #{{ order.id }}</div>
                <div class="small text-muted">{{ formatDateTime(order.created_at) }}</div>
              </div>
              <span class="status-badge" :class="`status-${order.status}`">
                {{ statusLabel(order.status) }}
              </span>
            </div>

            <div class="order-mid mt-2">
              <div class="small text-muted">{{ order.items_count }} sản phẩm</div>
              <div class="thumb-row mt-2">
                <img
                  v-for="(it, idx) in (order.items || []).slice(0, 4)"
                  :key="`thumb-${order.id}-${it.id || idx}`"
                  :src="it.image || fallbackImage"
                  :alt="it.name || 'product'"
                  class="order-thumb"
                />
                <span v-if="(order.items || []).length > 4" class="more-count">
                  +{{ (order.items || []).length - 4 }}
                </span>
              </div>
            </div>

            <div class="order-bottom mt-2">
              <span class="text-muted">Tổng thanh toán</span>
              <strong class="text-danger">{{ formatVnd(order.total_price) }}</strong>
            </div>
          </RouterLink>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import Swal from "sweetalert2";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import orderService from "@/services/order.service";

const cartCount = ref(0);
const loading = ref(false);
const orders = ref([]);
const activeStatus = ref("all");
const fallbackImage = "https://via.placeholder.com/64x64?text=No+Image";

const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const statusTabs = [
  { value: "all", label: "Tất cả" },
  { value: "pending", label: "Đang duyệt" },
  { value: "shipping", label: "Đang giao" },
  { value: "completed", label: "Hoàn thành" },
  { value: "cancelled", label: "Đã hủy" },
  { value: "rejected", label: "Từ chối" },
];

const filteredOrders = computed(() => {
  if (activeStatus.value === "all") return orders.value;
  return orders.value.filter((o) => String(o?.status || "") === activeStatus.value);
});

function countByStatus(status) {
  if (status === "all") return orders.value.length;
  return orders.value.filter((o) => String(o?.status || "") === status).length;
}

function formatDateTime(v) {
  if (!v) return "";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString("vi-VN");
}

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(Number(n || 0));
}

function statusLabel(status) {
  const map = {
    pending: "Đang duyệt",
    shipping: "Đang giao",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    rejected: "Từ chối",
  };
  return map[String(status)] || "Không xác định";
}

function orderDetailTo(order) {
  return { name: "order-detail", params: { id: Number(order?.id || 0) } };
}

async function fetchMe() {
  try {
    const meRes = await authService.me();
    const me = meRes?.data ?? meRes;
    const meUser = me?.user ?? me ?? {};
    user.value = {
      ...meUser,
      name: meUser?.name || "Guest",
      avatar: meUser?.avatar || "/default-user-avatar.svg",
      tier_id: meUser?.tier_id ?? meUser?.profile?.tier ?? null,
      profile: meUser?.profile ?? null,
    };
  } catch {
    user.value = {
      name: "Guest",
      avatar: "/default-user-avatar.svg",
      tier_id: null,
      profile: null,
    };
  }
}

async function loadOrders() {
  loading.value = true;
  try {
    orders.value = await orderService.getMyOrders("all");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải danh sách đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
    orders.value = [];
  } finally {
    loading.value = false;
  }
}

async function loadCartCount() {
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }
}

onMounted(async () => {
  await fetchMe();
  await Promise.all([loadCartCount(), loadOrders()]);
});
</script>

<style scoped>
.orders-shell {
  min-height: 60vh;
}

.orders-title {
  font-size: 1.55rem;
  font-weight: 800;
}

.status-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tab-btn {
  border: 1px solid var(--border-color);
  border-radius: 999px;
  background: var(--main-extra-bg);
  padding: 6px 12px;
  font-weight: 600;
}

.tab-btn.active {
  border-color: var(--hover-border-color);
  background: var(--hover-background-color);
}

.tab-count {
  margin-left: 6px;
  font-size: 0.85rem;
  color: var(--font-extra-color);
}

.empty-box {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 40px 16px;
}

.empty-icon {
  font-size: 2rem;
  color: var(--font-extra-color);
  margin-bottom: 12px;
}

.order-list {
  display: grid;
  gap: 12px;
}

.order-card {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 14px;
  padding: 14px;
  cursor: pointer;
  display: block;
  text-decoration: none;
  color: inherit;
}

.order-card:hover {
  border-color: var(--hover-border-color);
}

.order-top,
.order-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.thumb-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.order-thumb {
  width: 7rem;
  height: 7rem;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.more-count {
  font-size: 0.82rem;
  color: var(--font-extra-color);
}

.status-badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
}

.status-pending {
  background: rgba(255, 193, 7, 0.18);
  color: #916400;
}

.status-shipping {
  background: rgba(13, 110, 253, 0.15);
  color: #0a58ca;
}

.status-completed {
  background: rgba(25, 135, 84, 0.15);
  color: #0f5132;
}

.status-cancelled,
.status-rejected {
  background: rgba(220, 53, 69, 0.14);
  color: #842029;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}
</style>
