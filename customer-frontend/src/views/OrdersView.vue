<template>
  <div>
    <main class="container py-4">
      <section class="orders-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h1 class="orders-title mb-1">Đơn hàng đã đặt</h1>
            <div class="text-muted small">{{ meta.total }} đơn hàng</div>
          </div>
          <RouterLink to="/products" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-2"></i>Tiếp tục mua sắm
          </RouterLink>
        </div>

        <div class="filter-card mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4 filter-cell">
              <label class="form-label small mb-1">Tìm theo tên / mã đơn</label>
              <div class="input-group">
                <span class="input-group-text bg-transparent">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  v-model="keywordInput"
                  class="form-control bg-transparent"
                  type="text"
                  placeholder="Tìm mã đơn, tên người nhận, SĐT..."
                  @keydown.enter.prevent="applyFilters"
                />
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-2 filter-cell">
              <label class="form-label small mb-1">Thanh toán</label>
              <select v-model="paymentId" class="form-select bg-transparent">
                <option value="all">Tất cả phương thức</option>
                <option v-for="payment in paymentOptions" :key="payment.id" :value="String(payment.id)">
                  {{ payment.name }}
                </option>
              </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2 filter-cell">
              <label class="form-label small mb-1">Sắp xếp</label>
              <select v-model="sortBy" class="form-select bg-transparent">
                <option value="created_at_desc">Mới nhất</option>
                <option value="created_at_asc">Cũ nhất</option>
                <option value="total_price_desc">Giá trị cao nhất</option>
                <option value="total_price_asc">Giá trị thấp nhất</option>
              </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2 filter-cell">
              <label class="form-label small mb-1">Khoảng giá</label>
              <div class="filter-range">
                <input v-model="priceMin" class="form-control bg-transparent" type="number" min="0" placeholder="Từ" />
                <span>-</span>
                <input v-model="priceMax" class="form-control bg-transparent" type="number" min="0" placeholder="Đến" />
              </div>
            </div>

            <div class="col-12 col-md-8 col-lg-4 filter-cell">
              <label class="form-label small mb-1">Thời gian đặt</label>
              <div class="filter-range">
                <input v-model="orderedFrom" class="form-control bg-transparent" type="date" />
                <span>-</span>
                <input v-model="orderedTo" class="form-control bg-transparent" type="date" />
              </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 filter-actions">
              <button class="btn btn-main btn-sm filter-btn" @click="applyFilters">Áp dụng</button>
              <button class="btn btn-outline-secondary btn-sm filter-btn" @click="resetFilters">Làm mới</button>
            </div>
          </div>
        </div>

        <div class="status-tabs mb-3">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            type="button"
            class="tab-btn"
            :class="{ active: activeStatus === tab.value }"
            @click="setStatus(tab.value)"
          >
            {{ tab.label }}
            <span class="tab-count">{{ countByStatus(tab.value) }}</span>
          </button>
        </div>

        <div v-if="loading" class="empty-box text-center">
          <i class="fa-solid fa-spinner fa-spin empty-icon"></i>
          <p class="mb-0 text-muted">Đang tải danh sách đơn hàng...</p>
        </div>

        <div v-else-if="!orders.length" class="empty-box text-center">
          <i class="fa-solid fa-box-open empty-icon"></i>
          <h5 class="mb-2">Không có đơn hàng</h5>
          <p class="text-muted mb-3">Chưa có đơn hàng phù hợp với bộ lọc hiện tại.</p>
          <RouterLink to="/products" class="btn btn-main">Mua ngay</RouterLink>
        </div>

        <div v-else class="order-list">
          <RouterLink
            v-for="order in orders"
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

        <div v-if="meta.total" class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
          <div class="small text-muted">
            Hiển thị {{ (meta.current_page - 1) * meta.per_page + 1 }} -
            {{ Math.min(meta.current_page * meta.per_page, meta.total) }} / {{ meta.total }}
          </div>

          <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" :disabled="page === 1" @click="page--">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" disabled>
              Trang {{ page }}
            </button>
            <button class="btn btn-outline-secondary btn-sm" :disabled="meta.current_page >= meta.last_page" @click="page++">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import Swal from "sweetalert2";
import AppFooter from "@/components/layout/AppFooter.vue";
import orderService from "@/services/order.service";

const loading = ref(true);
const orders = ref([]);
const paymentOptions = ref([]);
const statusSummary = ref({});
const keywordInput = ref("");
const appliedKeyword = ref("");
const activeStatus = ref("all");
const paymentId = ref("all");
const priceMin = ref("");
const priceMax = ref("");
const orderedFrom = ref("");
const orderedTo = ref("");
const sortBy = ref("created_at_desc");
const page = ref(1);
const perPage = 8;
const meta = ref({
  current_page: 1,
  per_page: perPage,
  total: 0,
  last_page: 1,
});
const fallbackImage = "https://via.placeholder.com/64x64?text=No+Image";

const statusTabs = [
  { value: "all", label: "Tất cả" },
  { value: "pending", label: "Đang duyệt" },
  { value: "shipping", label: "Đang giao" },
  { value: "completed", label: "Hoàn thành" },
  { value: "cancelled", label: "Đã hủy" },
  { value: "rejected", label: "Từ chối" },
];

function countByStatus(status) {
  return Number(statusSummary.value?.[status] || 0);
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

function buildParams() {
  return {
    q: appliedKeyword.value.trim() || undefined,
    status: activeStatus.value,
    payment_id: paymentId.value !== "all" ? Number(paymentId.value) : undefined,
    price_min: priceMin.value !== "" ? Number(priceMin.value) : undefined,
    price_max: priceMax.value !== "" ? Number(priceMax.value) : undefined,
    ordered_from: orderedFrom.value || undefined,
    ordered_to: orderedTo.value || undefined,
    sort_by: sortBy.value || "created_at_desc",
    page: page.value,
    per_page: perPage,
  };
}

async function loadOrders() {
  loading.value = true;
  try {
    const res = await orderService.getMyOrders(buildParams());
    orders.value = Array.isArray(res?.items) ? res.items : [];
    paymentOptions.value = Array.isArray(res?.filters?.payments) ? res.filters.payments : [];
    statusSummary.value = res?.status_summary || {};
    meta.value = res?.meta || {
      current_page: 1,
      per_page: perPage,
      total: 0,
      last_page: 1,
    };
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải danh sách đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
    orders.value = [];
  } finally {
    loading.value = false;
  }
}

async function applyFilters() {
  appliedKeyword.value = keywordInput.value.trim();
  page.value = 1;
  await loadOrders();
}

async function resetFilters() {
  keywordInput.value = "";
  appliedKeyword.value = "";
  activeStatus.value = "all";
  paymentId.value = "all";
  priceMin.value = "";
  priceMax.value = "";
  orderedFrom.value = "";
  orderedTo.value = "";
  sortBy.value = "created_at_desc";
  page.value = 1;
  await loadOrders();
}

async function setStatus(status) {
  activeStatus.value = status;
  page.value = 1;
  await loadOrders();
}

onMounted(async () => {
  await loadOrders();
});

watch(page, async () => {
  await loadOrders();
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

.filter-card {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 1rem;
}

.filter-cell {
  display: flex;
  flex-direction: column;
}

.filter-cell .form-label {
  min-height: 20px;
}

.filter-card :deep(.form-control),
.filter-card :deep(.form-select),
.filter-card :deep(.input-group-text),
.filter-card .btn {
  min-height: 42px;
}

.filter-card :deep(.input-group) {
  flex-wrap: nowrap;
}

.filter-card :deep(.input-group .form-control) {
  min-width: 0;
}

.filter-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-range > .form-control {
  flex: 1 1 0;
  min-width: 0;
}

.filter-range span {
  opacity: 0.65;
  flex: 0 0 auto;
}

.filter-actions {
  align-items: center;
}

.filter-btn {
  min-height: 38px !important;
  padding: 0.4rem 0.9rem;
  font-weight: 600;
  flex: 0 0 auto;
}

@media (max-width: 991.98px) {
  .filter-actions {
    justify-content: flex-start !important;
    flex-wrap: wrap;
  }
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
