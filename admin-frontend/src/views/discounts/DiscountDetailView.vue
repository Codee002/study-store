<template>
  <div class="row g-3">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-start gap-2 flex-column flex-md-row">
        <div>
          <h4 class="mb-1">Chi tiết khuyến mãi</h4>
          <div class="small opacity-75">Thông tin chi tiết khuyến mãi theo danh mục</div>
        </div>

        <div class="d-flex gap-2">
          <RouterLink class="btn btn-outline-secondary" :to="{ name: 'discounts.list' }">
            <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
          </RouterLink>
          <RouterLink
            class="btn btn-outline-secondary"
            :to="{ name: 'discounts.edit', params: { id } }"
          >
            <i class="fa-solid fa-pen-to-square me-1"></i> Chỉnh sửa
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-4 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i> Đang tải dữ liệu...
          </div>

          <div v-else-if="item" class="row g-3">
            <div class="col-12 col-md-6">
              <div class="label">Mã khuyến mãi</div>
              <div class="value">D{{ item.id }}</div>
            </div>
            <div class="col-12 col-md-6">
              <div class="label">Danh mục</div>
              <div class="value">{{ item.category?.name || "-" }}</div>
            </div>
            <div class="col-12 col-md-6">
              <div class="label">% Khuyến mãi</div>
              <div class="value">{{ item.percent ?? 0 }}%</div>
            </div>
            <div class="col-12 col-md-6">
              <div class="label">Trạng thái</div>
              <div class="value">
                <span class="badge" :class="statusBadgeClass(item.status)">
                  {{ statusLabel(item.status) }}
                </span>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="label">Ngày bắt đầu</div>
              <div class="value">{{ formatDate(item.start_at) }}</div>
            </div>
            <div class="col-12 col-md-6">
              <div class="label">Ngày kết thúc</div>
              <div class="value">{{ formatDate(item.end_at) }}</div>
            </div>
            <div class="col-12">
              <div class="label">Mô tả</div>
              <div class="value value-block">{{ item.des || "-" }}</div>
            </div>
          </div>

          <div v-else class="py-4 text-center opacity-75">Không tìm thấy khuyến mãi</div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between gap-2 flex-column flex-md-row mb-3">
            <div>
              <h5 class="mb-1">Đơn hàng đã áp dụng khuyến mãi</h5>
              <div class="small opacity-75">Danh sách đơn hàng đã dùng khuyến mãi này</div>
            </div>
            <span class="badge bg-secondary-subtle text-secondary">
              Tổng: {{ orderMeta.total }}
            </span>
          </div>

          <div class="row g-2 align-items-end mb-3">
            <div class="col-12 col-lg-4">
              <label class="form-label small mb-1">Tìm theo tên / mã đơn</label>
              <div class="input-group">
                <span class="input-group-text bg-transparent">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  v-model="keywordInput"
                  class="form-control bg-transparent"
                  type="text"
                  placeholder="Tìm mã đơn, tên khách, email, SĐT..."
                  @keydown.enter.prevent="applyOrderFilters"
                />
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
              <label class="form-label small mb-1">Trạng thái</label>
              <select v-model="status" class="form-select bg-transparent">
                <option value="all">Tất cả trạng thái</option>
                <option value="pending">Đang duyệt</option>
                <option value="shipping">Đang giao</option>
                <option value="completed">Hoàn thành</option>
                <option value="cancelled">Đã hủy</option>
                <option value="rejected">Từ chối</option>
              </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
              <label class="form-label small mb-1">Thanh toán</label>
              <select v-model="paymentId" class="form-select bg-transparent">
                <option value="all">Tất cả phương thức</option>
                <option v-for="payment in paymentOptions" :key="payment.id" :value="String(payment.id)">
                  {{ payment.name }}
                </option>
              </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
              <label class="form-label small mb-1">Sắp xếp</label>
              <select v-model="sortBy" class="form-select bg-transparent">
                <option value="created_at_desc">Mới nhất</option>
                <option value="created_at_asc">Cũ nhất</option>
                <option value="total_price_desc">Giá trị cao nhất</option>
                <option value="total_price_asc">Giá trị thấp nhất</option>
              </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
              <label class="form-label small mb-1">Khoảng giá</label>
              <div class="filter-range">
                <input v-model="priceMin" class="form-control bg-transparent" type="number" min="0" placeholder="Từ" />
                <span>-</span>
                <input v-model="priceMax" class="form-control bg-transparent" type="number" min="0" placeholder="Đến" />
              </div>
            </div>

            <div class="col-12 col-md-8 col-lg-4">
              <label class="form-label small mb-1">Thời gian đặt</label>
              <div class="filter-range">
                <input v-model="orderedFrom" class="form-control bg-transparent" type="date" />
                <span>-</span>
                <input v-model="orderedTo" class="form-control bg-transparent" type="date" />
              </div>
            </div>

            <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
              <button class="btn btn-primary btn-sm flex-grow-1" @click="applyOrderFilters">Áp dụng</button>
              <button class="btn btn-outline-secondary btn-sm" @click="resetOrderFilters">Làm mới</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3" style="width: 120px">Mã đơn</th>
                  <th style="min-width: 260px">Sản phẩm</th>
                  <th style="min-width: 220px">Khách hàng</th>
                  <th style="width: 150px">Thanh toán</th>
                  <th class="text-end" style="width: 180px">Giảm giá áp dụng</th>
                  <th class="text-end" style="width: 180px">Tổng tiền</th>
                  <th class="text-end" style="width: 140px">Trạng thái</th>
                  <th class="text-end pe-3" style="width: 120px">Thao tác</th>
                </tr>
              </thead>

              <tbody v-if="loadingOrders">
                <tr>
                  <td colspan="8" class="text-center py-5 opacity-75">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i>Đang tải đơn hàng...
                  </td>
                </tr>
              </tbody>

              <tbody v-else-if="orders.length">
                <tr v-for="order in orders" :key="order.id">
                  <td class="ps-3">
                    <div class="fw-semibold">#{{ order.id }}</div>
                    <div class="small opacity-75">{{ formatDateTime(order.created_at) }}</div>
                  </td>

                  <td>
                    <RouterLink class="name-link" :to="{ name: 'orders.detail', params: { id: order.id } }">
                      <div class="d-flex align-items-center gap-2">
                        <div class="thumb">
                          <img v-if="order.preview_image" :src="order.preview_image" alt="thumb" />
                          <div v-else class="thumb-placeholder">
                            <i class="fa-regular fa-image"></i>
                          </div>
                        </div>
                        <div>
                          <div class="fw-semibold text-truncate item-name">{{ order.preview_name }}</div>
                        </div>
                      </div>
                    </RouterLink>
                  </td>

                  <td>
                    <div class="fw-semibold">{{ order.customer?.name || "-" }}</div>
                    <div class="small opacity-75">{{ order.customer?.email || "-" }}</div>
                    <div class="small opacity-75">{{ order.delivery_info?.phone || "-" }}</div>
                  </td>

                  <td>
                    <span class="badge bg-secondary-subtle text-secondary">
                      {{ order.payment?.name || "-" }}
                    </span>
                  </td>

                  <td class="text-end fw-semibold text-success">
                    {{ formatMoney(order.applied_discount_price) }}
                  </td>

                  <td class="text-end fw-semibold text-danger">
                    {{ formatMoney(order.total_price) }}
                  </td>

                  <td class="text-end">
                    <span class="badge" :class="orderStatusClass(order.status)">
                      {{ orderStatusLabel(order.status) }}
                    </span>
                  </td>

                  <td class="text-end pe-3">
                    <RouterLink
                      class="icon-btn icon-view"
                      :to="{ name: 'orders.detail', params: { id: order.id } }"
                      title="Xem chi tiết"
                    >
                      <i class="fa-solid fa-eye"></i>
                    </RouterLink>
                  </td>
                </tr>
              </tbody>

              <tbody v-else>
                <tr>
                  <td colspan="8" class="text-center py-5 opacity-75">
                    <i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i>
                    Chưa có đơn hàng nào áp dụng khuyến mãi này.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="orderMeta.total"
            class="d-flex justify-content-between align-items-center p-3 border-top mt-3"
          >
            <div class="small opacity-75">
              Hiển thị {{ (orderMeta.current_page - 1) * orderMeta.per_page + 1 }} -
              {{ Math.min(orderMeta.current_page * orderMeta.per_page, orderMeta.total) }} / {{ orderMeta.total }}
            </div>
            <div class="btn-group">
              <button class="btn btn-outline-secondary btn-sm" :disabled="orderPage === 1" @click="orderPage--">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>
                Trang {{ orderPage }}
              </button>
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="orderMeta.current_page >= orderMeta.last_page"
                @click="orderPage++"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

import DiscountService from "../../services/discount.service";
import { formatMoney, formatDateTimeVN as formatDateTime } from "@/utils/utils";

const props = defineProps({ id: String });
const router = useRouter();
const loading = ref(true);
const loadingOrders = ref(false);
const item = ref(null);
const orders = ref([]);
const paymentOptions = ref([]);
const keywordInput = ref("");
const appliedKeyword = ref("");
const status = ref("all");
const paymentId = ref("all");
const priceMin = ref("");
const priceMax = ref("");
const orderedFrom = ref("");
const orderedTo = ref("");
const sortBy = ref("created_at_desc");
const orderPage = ref(1);
const orderMeta = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1,
});

function statusLabel(status) {
  if (status === "actived") return "Đang bật";
  if (status === "disabled") return "Đang tắt";
  return "-";
}

function statusBadgeClass(status) {
  if (status === "actived") return "status-actived";
  if (status === "disabled") return "status-disabled";
  return "bg-secondary-subtle text-secondary";
}

function formatDate(value) {
  if (!value) return "-";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString("vi-VN");
}

function orderStatusLabel(statusValue) {
  const map = {
    pending: "Đang duyệt",
    shipping: "Đang giao",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    rejected: "Từ chối",
  };
  return map[String(statusValue || "")] || "Không rõ";
}

function orderStatusClass(statusValue) {
  const v = String(statusValue || "");
  if (v === "pending") return "bg-warning-subtle text-warning-emphasis";
  if (v === "shipping") return "bg-info-subtle text-info-emphasis";
  if (v === "completed") return "bg-success-subtle text-success-emphasis";
  if (v === "cancelled") return "bg-danger-subtle text-danger-emphasis";
  if (v === "rejected") return "bg-danger-subtle text-danger-emphasis";
  return "bg-secondary-subtle text-secondary";
}

function buildOrderParams() {
  return {
    q: appliedKeyword.value.trim() || undefined,
    status: status.value,
    payment_id: paymentId.value !== "all" ? Number(paymentId.value) : undefined,
    price_min: priceMin.value !== "" ? Number(priceMin.value) : undefined,
    price_max: priceMax.value !== "" ? Number(priceMax.value) : undefined,
    ordered_from: orderedFrom.value || undefined,
    ordered_to: orderedTo.value || undefined,
    sort_by: sortBy.value || "created_at_desc",
    page: orderPage.value,
    per_page: 10,
  };
}

async function fetchDetail() {
  if (!item.value) {
    loading.value = true;
  }
  loadingOrders.value = true;
  try {
    const res = await DiscountService.get(props.id, buildOrderParams());
    item.value = res?.data?.discount ?? res?.data ?? res ?? null;
    orders.value = Array.isArray(res?.data?.orders?.items) ? res.data.orders.items : [];
    orderMeta.value = res?.data?.orders?.meta || {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1,
    };
    paymentOptions.value = Array.isArray(res?.data?.filters?.payments)
      ? res.data.filters.payments
      : [];
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải chi tiết khuyến mãi";
    await Swal.fire("Lỗi", msg, "error");
    router.push({ name: "discounts.list" });
  } finally {
    loading.value = false;
    loadingOrders.value = false;
  }
}

async function applyOrderFilters() {
  appliedKeyword.value = keywordInput.value.trim();
  orderPage.value = 1;
  await fetchDetail();
}

async function resetOrderFilters() {
  keywordInput.value = "";
  appliedKeyword.value = "";
  status.value = "all";
  paymentId.value = "all";
  priceMin.value = "";
  priceMax.value = "";
  orderedFrom.value = "";
  orderedTo.value = "";
  sortBy.value = "created_at_desc";
  orderPage.value = 1;
  await fetchDetail();
}

onMounted(fetchDetail);

watch(orderPage, fetchDetail);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.label {
  font-size: 0.825rem;
  opacity: 0.75;
  margin-bottom: 0.35rem;
}

.value {
  font-weight: 600;
}

.value-block {
  white-space: pre-wrap;
  font-weight: 500;
}

.status-actived {
  background: color-mix(in srgb, #16a34a 18%, transparent);
  border: 1px solid color-mix(in srgb, #16a34a 45%, transparent);
  color: var(--font-color);
}

.status-disabled {
  background: color-mix(in srgb, #ef4444 18%, transparent);
  border: 1px solid color-mix(in srgb, #ef4444 45%, transparent);
  color: var(--font-color);
}

.filter-range {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-range span {
  opacity: 0.65;
  flex: 0 0 auto;
}

.name-link {
  color: inherit;
  text-decoration: none;
}

.thumb {
  width: 52px;
  height: 52px;
  border-radius: 0.6rem;
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: rgba(255, 255, 255, 0.03);
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-placeholder {
  opacity: 0.6;
}

.item-name {
  max-width: 180px;
}

.icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 0.75rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border-color);
  background: transparent;
  text-decoration: none;
}

.icon-btn:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}

.icon-view {
  color: #0d6efd;
}
</style>
