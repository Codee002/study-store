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
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

import DiscountService from "../../services/discount.service";

const props = defineProps({ id: String });
const router = useRouter();
const loading = ref(true);
const item = ref(null);

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

async function fetchDetail() {
  loading.value = true;
  try {
    const res = await DiscountService.get(props.id);
    item.value = res?.data ?? res ?? null;
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải chi tiết khuyến mãi";
    await Swal.fire("Lỗi", msg, "error");
    router.push({ name: "discounts.list" });
  } finally {
    loading.value = false;
  }
}

onMounted(fetchDetail);
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
</style>
