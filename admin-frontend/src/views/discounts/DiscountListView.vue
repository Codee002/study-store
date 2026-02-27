<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Khuyến mãi</h4>
          <div class="small opacity-75">Quản lý danh sách khuyến mãi theo danh mục</div>
        </div>

        <RouterLink
          class="icon-btn icon-add"
          :to="{ name: 'discounts.create' }"
          title="Thêm khuyến mãi"
        >
          <i class="fa-solid fa-circle-plus"></i>
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-lg-5">
              <div class="input-group">
                <span class="input-group-text bg-transparent">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  v-model="keyword"
                  type="text"
                  class="form-control bg-transparent"
                  placeholder="Tìm theo mô tả hoặc danh mục..."
                />
                <button
                  v-if="keyword"
                  class="btn btn-outline-secondary"
                  @click="keyword = ''"
                  title="Clear"
                >
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-7 d-flex justify-content-md-end gap-2">
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
                  <th class="ps-3" style="width: 110px">Mã</th>
                  <th style="min-width: 180px">Danh mục</th>
                  <th style="min-width: 220px">Mô tả</th>
                  <th class="text-end" style="width: 120px">%</th>
                  <th class="text-end" style="width: 150px">Bắt đầu</th>
                  <th class="text-end" style="width: 150px">Kết thúc</th>
                  <th class="text-end" style="width: 140px">Trạng thái</th>
                  <th class="text-end pe-3" style="width: 140px">Thao tác</th>
                </tr>
              </thead>

              <tbody v-if="items.length">
                <tr v-for="d in items" :key="d.id">
                  <td class="ps-3">
                    <span class="code-pill">D{{ d.id }}</span>
                  </td>
                  <td>
                    <div class="fw-semibold">{{ d.category?.name || "-" }}</div>
                  </td>
                  <td>
                    <RouterLink
                      class="name-link"
                      :to="{ name: 'discounts.detail', params: { id: d.id } }"
                    >
                      <div class="fw-semibold">{{ d.des || "-" }}</div>
                    </RouterLink>
                  </td>
                  <td class="text-end">
                    <span class="badge percent-badge">{{ d.percent ?? 0 }}%</span>
                  </td>
                  <td class="text-end">
                    <span class="small opacity-75">{{ formatDate(d.start_at) }}</span>
                  </td>
                  <td class="text-end">
                    <span class="small opacity-75">{{ formatDate(d.end_at) }}</span>
                  </td>
                  <td class="text-end">
                    <span class="badge" :class="statusBadgeClass(d.status)">
                      {{ statusLabel(d.status) }}
                    </span>
                  </td>
                  <td class="text-end pe-3">
                    <div class="d-flex justify-content-end gap-2">
                      <RouterLink
                        class="icon-btn icon-edit"
                        :to="{ name: 'discounts.edit', params: { id: d.id } }"
                        title="Chỉnh sửa"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </RouterLink>

                      <button class="icon-btn icon-delete" title="Xóa" @click="onDeleteClick(d.id)">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>

              <tbody v-else>
                <tr>
                  <td colspan="8" class="text-center py-5">
                    <div class="opacity-75">
                      <i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i>
                      {{ loading ? "Đang tải dữ liệu..." : "Không có khuyến mãi phù hợp." }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            class="d-flex justify-content-between align-items-center p-3 border-top"
            v-if="meta.total"
          >
            <div class="small opacity-75">
              Hiển thị
              {{ (meta.current_page - 1) * meta.per_page + 1 }}
              -
              {{ Math.min(meta.current_page * meta.per_page, meta.total) }}
              / {{ meta.total }}
            </div>

            <div class="btn-group">
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="page === 1 || loading"
                @click="page--"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>
                Trang {{ page }}
              </button>
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="meta.current_page >= meta.last_page || loading"
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

import DiscountService from "../../services/discount.service";

const keyword = ref("");
const page = ref(1);
const perPage = 8;

const items = ref([]);
const loading = ref(false);
const meta = ref({
  current_page: 1,
  per_page: perPage,
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

async function fetchDiscounts() {
  loading.value = true;
  try {
    const res = await DiscountService.getAll({
      q: keyword.value.trim() || undefined,
      page: page.value,
      per_page: perPage,
    });

    const list = res?.data?.items ?? res?.items ?? [];
    const nextMeta = res?.data?.meta ?? res?.meta ?? meta.value;

    items.value = Array.isArray(list) ? list : [];
    meta.value = {
      current_page: Number(nextMeta.current_page || 1),
      per_page: Number(nextMeta.per_page || perPage),
      total: Number(nextMeta.total || 0),
      last_page: Number(nextMeta.last_page || 1),
    };

    if (page.value > meta.value.last_page) {
      page.value = Math.max(1, meta.value.last_page);
    }
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải danh sách khuyến mãi";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    loading.value = false;
  }
}

async function onDeleteClick(id) {
  const result = await Swal.fire({
    title: "Xóa khuyến mãi này?",
    text: "Không thể hoàn tác!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
  });

  if (!result.isConfirmed) return;

  try {
    await DiscountService.delete(id);
    await Swal.fire("Thành công", "Đã xóa khuyến mãi", "success");

    if (items.value.length === 1 && page.value > 1) {
      page.value -= 1;
      return;
    }

    await fetchDiscounts();
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể xóa khuyến mãi";
    await Swal.fire("Lỗi", msg, "error");
  }
}

onMounted(fetchDiscounts);

watch(keyword, async () => {
  page.value = 1;
  await fetchDiscounts();
});

watch(page, async () => {
  await fetchDiscounts();
});
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.name-link {
  text-decoration: none;
  color: inherit;
  display: inline-block;
}

.percent-badge {
  background: color-mix(in srgb, var(--main-color) 10%, transparent);
  border: 1px solid var(--hover-border-color);
  color: var(--font-color);
  font-weight: 700;
}

.status-actived {
  background: color-mix(in srgb, #16a34a 18%, transparent);
  border: 1px solid color-mix(in srgb, #16a34a 45%, transparent);
  color: var(--font-color);
  font-weight: 700;
}

.status-disabled {
  background: color-mix(in srgb, #ef4444 18%, transparent);
  border: 1px solid color-mix(in srgb, #ef4444 45%, transparent);
  color: var(--font-color);
  font-weight: 700;
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
  transition: 0.12s ease;
}

.icon-btn:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}

.icon-add {
  width: 42px;
  height: 42px;
  border-radius: 1rem;
  color: #16a34a;
}

.icon-edit {
  color: #f59e0b;
}

.icon-delete {
  color: #ef4444;
}
</style>
