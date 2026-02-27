<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Chi tiet phuong thuc thanh toan</h4>
          <div class="small opacity-75">ID: {{ id }}</div>
        </div>

        <div class="d-flex gap-2">
          <RouterLink class="btn btn-outline-secondary" :to="{ name: 'payments.list' }">
            <i class="fa-solid fa-arrow-left me-1"></i> Quay lai
          </RouterLink>
          <RouterLink
            class="btn btn-outline-secondary"
            :to="{ name: 'payments.edit', params: { id } }"
          >
            <i class="fa-solid fa-pen-to-square me-1"></i> Chinh sua
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-4 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i> Dang tai du lieu...
          </div>

          <div v-else-if="payment">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <div class="label">Ma phuong thuc</div>
                <div class="value"><span class="code-pill">PAY{{ payment.id }}</span></div>
              </div>

              <div class="col-12 col-md-6">
                <div class="label">Trang thai</div>
                <div class="value">
                  <span class="badge" :class="statusBadgeClass(payment.status)">
                    {{ statusLabel(payment.status) }}
                  </span>
                </div>
              </div>

              <div class="col-12">
                <div class="label">Ten phuong thuc thanh toan</div>
                <div class="value fw-semibold">{{ payment.name || "-" }}</div>
              </div>

              <div class="col-12 col-md-6">
                <div class="label">Ngay tao</div>
                <div class="value">{{ formatDate(payment.created_at) }}</div>
              </div>

              <div class="col-12 col-md-6">
                <div class="label">Cap nhat lan cuoi</div>
                <div class="value">{{ formatDate(payment.updated_at) }}</div>
              </div>
            </div>
          </div>

          <div v-else class="py-4 text-center opacity-75">
            Khong tim thay du lieu phuong thuc thanh toan.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

import PaymentService from "../../services/payment.service";

const props = defineProps({ id: String });
const router = useRouter();

const loading = ref(true);
const payment = ref(null);

function statusLabel(status) {
  if (status === "actived") return "Dang bat";
  if (status === "disabled") return "Dang tat";
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
  return d.toLocaleString("vi-VN");
}

async function fetchPayment() {
  loading.value = true;
  try {
    const res = await PaymentService.get(props.id);
    payment.value = res?.data ?? res ?? null;
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Khong the tai chi tiet phuong thuc thanh toan.";
    await Swal.fire("Loi", msg, "error");
    router.push({ name: "payments.list" });
  } finally {
    loading.value = false;
  }
}

onMounted(fetchPayment);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.label {
  font-size: 0.875rem;
  opacity: 0.75;
  margin-bottom: 0.25rem;
}

.value {
  font-size: 0.95rem;
}

.code-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-weight: 700;
  letter-spacing: 0.03em;
  border: 1px solid var(--border-color);
  background: var(--main-bg);
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
</style>
