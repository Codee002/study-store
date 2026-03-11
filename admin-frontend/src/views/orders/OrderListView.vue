<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Đơn hàng</h4>
          <div class="small opacity-75">Danh sách đơn hàng khách đã đặt</div>
        </div>
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'orders.create' }">
          <i class="fa-solid fa-plus me-1"></i> Tạo đơn hàng
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div class="row g-2">
            <div class="col-12 col-lg-5">
              <div class="input-group">
                <span class="input-group-text bg-transparent">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  v-model="keyword"
                  class="form-control bg-transparent"
                  type="text"
                  placeholder="Tìm mã đơn, tên khách, email, SĐT..."
                />
              </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
              <select v-model="status" class="form-select bg-transparent">
                <option value="all">Tất cả trạng thái</option>
                <option value="pending">Đang duyệt</option>
                <option value="shipping">Đang giao</option>
                <option value="completed">Hoàn thành</option>
                <option value="cancelled">Đã hủy</option>
                <option value="rejected">Từ chối</option>
              </select>
            </div>
            <div class="col-12 col-md-6 col-lg-4 d-flex justify-content-md-end">
              <span class="badge bg-secondary-subtle text-secondary align-self-center">
                Tổng: {{ meta.total }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3" style="width: 120px">Mã đơn</th>
                  <th style="min-width: 260px">Sản phẩm</th>
                  <th style="min-width: 220px">Khách hàng</th>
                  <th style="width: 150px">Thanh toán</th>
                  <th class="text-end" style="width: 180px">Tổng tiền</th>
                  <th class="text-end" style="width: 140px">Trạng thái</th>
                  <th class="text-end pe-3" style="width: 120px">Thao tác</th>
                </tr>
              </thead>

              <tbody v-if="loading">
                <tr>
                  <td colspan="8" class="text-center py-5 opacity-75">
                    <i class="fa-solid fa-spinner fa-spin me-2"></i>Đang tải đơn hàng...
                  </td>
                </tr>
              </tbody>

              <tbody v-else-if="items.length">
                <tr v-for="order in items" :key="order.id">
                  <td class="ps-3">
                    <div class="fw-semibold">#{{ order.id }}</div>
                    <div class="small opacity-75">{{ formatDateTime(order.created_at) }}</div>
                  </td>

                  <td>
                    <RouterLink
                      class="name-link"
                      :to="{ name: 'orders.detail', params: { id: order.id } }"
                    >
                      <div class="d-flex align-items-center gap-2">
                        <div class="thumb">
                          <img
                            v-if="previewImage(order)"
                            :src="previewImage(order)"
                            alt="thumb"
                          />
                          <div v-else class="thumb-placeholder">
                            <i class="fa-regular fa-image"></i>
                          </div>
                        </div>
                        <div>
                          <div class="fw-semibold text-truncate item-name">
                            {{ previewName(order) }}
                          </div>
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

                  <td class="text-end">{{ order.items_count }}</td>

                  <td class="text-end fw-semibold text-danger">
                    {{ formatMoney(order.total_price) }}
                  </td>

                  <td class="text-end">
                    <span class="badge" :class="statusClass(order.status)">
                      {{ statusLabel(order.status) }}
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
                    Không có đơn hàng phù hợp.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="meta.total"
            class="d-flex justify-content-between align-items-center p-3 border-top"
          >
            <div class="small opacity-75">
              Hiển thị {{ (meta.current_page - 1) * meta.per_page + 1 }} -
              {{ Math.min(meta.current_page * meta.per_page, meta.total) }} / {{ meta.total }}
            </div>
            <div class="btn-group">
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="page === 1"
                @click="page--"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>
                Trang {{ page }}
              </button>
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="meta.current_page >= meta.last_page"
                @click="page++"
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
import Swal from "sweetalert2";
import OrderService from "@/services/order.service";
import { formatMoney, formatDateTimeVN as formatDateTime } from "@/utils/utils";

const keyword = ref("");
const status = ref("all");
const page = ref(1);
const perPage = 10;
const loading = ref(false);
const items = ref([]);
const meta = ref({
  current_page: 1,
  per_page: perPage,
  total: 0,
  last_page: 1,
});

function previewImage(order) {
  return order?.items?.[0]?.image || "";
}

function previewName(order) {
  const first = order?.items?.[0];
  if (!first) return "Không có sản phẩm";
  if ((order?.items_count || 0) <= 1) return first.name || "Sản phẩm";
  return `${first.name || "Sản phẩm"} (+${Math.max((order?.items_count || 1) - 1, 0)})`;
}

function statusLabel(statusValue) {
  const map = {
    pending: "Đang duyệt",
    shipping: "Đang giao",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    rejected: "Từ chối",
  };
  return map[String(statusValue || "")] || "Không rõ";
}

function statusClass(statusValue) {
  const v = String(statusValue || "");
  if (v === "pending") return "bg-warning-subtle text-warning-emphasis";
  if (v === "shipping") return "bg-info-subtle text-info-emphasis";
  if (v === "completed") return "bg-success-subtle text-success-emphasis";
  if (v === "cancelled") return "bg-danger-subtle text-danger-emphasis";
  if (v === "rejected") return "bg-danger-subtle text-danger-emphasis";
  return "bg-secondary-subtle text-secondary";
}

async function fetchOrders() {
  loading.value = true;
  try {
    const res = await OrderService.getAll({
      q: keyword.value.trim() || undefined,
      status: status.value,
      page: page.value,
      per_page: perPage,
    });
    items.value = Array.isArray(res?.data?.items) ? res.data.items : [];
    meta.value = res?.data?.meta || {
      current_page: 1,
      per_page: perPage,
      total: 0,
      last_page: 1,
    };
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải danh sách đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    loading.value = false;
  }
}

onMounted(fetchOrders);

watch([keyword, status], async () => {
  page.value = 1;
  await fetchOrders();
});

watch(page, fetchOrders);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
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
