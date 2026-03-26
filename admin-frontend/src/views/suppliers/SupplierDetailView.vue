<template>
  <div class="row g-3">
    <div class="col-12 d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row">
      <div>
        <h4 class="mb-1">Nhà cung cấp: {{ supplier?.name || "—" }}</h4>
        <div class="small opacity-75">
          Địa chỉ: {{ supplier?.address || "—" }} · SĐT: {{ supplier?.contact_number || "—" }}
        </div>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary" to="/suppliers">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
        </RouterLink>
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'suppliers.edit', params: { id } }">
          <i class="fa-solid fa-pen-to-square me-1"></i> Chỉnh sửa
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3">Mã phiếu</th>
                  <th>Kho</th>
                  <th style="width: 140px">Trạng thái</th>
                  <th style="width: 140px">Số sản phẩm</th>
                  <th style="width: 160px">Tổng tiền</th>
                  <th style="width: 180px">Ngày tạo</th>
                </tr>
              </thead>
              <tbody v-if="receipts.length">
                <tr v-for="r in receipts" :key="r.id" class="row-click" @click="goReceipt(r.id)">
                  <td class="ps-3 fw-semibold">PN{{ r.id }}</td>
                  <td>
                    <div  class="fw-semibold" v-if="r.warehouse?.address">
                      {{ shortWarehouse(r.warehouse.address) }}
                    </div>
                  </td>
                  <td><span class="badge" :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span></td>
                  <td>{{ r.total_quantity ?? 0 }}</td>
                  <td>{{ money(r.total_cost) }}</td>
                  <td>{{ formatDate(r.created_at) }}</td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr>
                  <td colspan="6" class="text-center py-4 opacity-75">Chưa có phiếu nhập nào.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center p-3 border-top" v-if="meta.total">
            <div class="small opacity-75">
              Hiển thị
              {{ (meta.current_page - 1) * meta.per_page + 1 }} -
              {{ Math.min(meta.current_page * meta.per_page, meta.total) }} / {{ meta.total }}
            </div>
            <div class="btn-group">
              <button class="btn btn-outline-secondary btn-sm" :disabled="page === 1" @click="page--">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>Trang {{ page }}</button>
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
import dayjs from "dayjs";
import { onMounted, ref, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import SupplierService from "../../services/supplier.service";
import ReceiptService from "../../services/receipt.service";

const route = useRoute();
const router = useRouter();
const id = route.params.id;
const supplier = ref(null);
const receipts = ref([]);
const page = ref(1);
const perPage = 10;
const meta = ref({ current_page: 1, per_page: perPage, total: 0, last_page: 1 });

function statusLabel(s) {
  return (
    {
      pending: "Chờ duyệt",
      completed: "Hoàn tất",
      rejected: "Từ chối",
    }[s] || s
  );
}
function statusClass(s) {
  return {
    pending: "bg-warning-subtle text-warning",
    completed: "bg-success-subtle text-success",
    rejected: "bg-danger-subtle text-danger",
  }[s];
}
function formatDate(d) {
  return d ? dayjs(d).format("DD/MM/YYYY HH:mm") : "—";
}
function money(v) {
  return Number(v || 0).toLocaleString("vi-VN") + " ₫";
}

async function loadSupplier() {
  const res = await SupplierService.get(id);
  supplier.value = res?.data ?? res ?? null;
}

async function loadReceipts() {
  const res = await ReceiptService.getAll({
    supplier_id: id,
    page: page.value,
    per_page: perPage,
  });
  const payload = res?.data?.data ?? res?.data ?? res ?? {};
  receipts.value = payload.items ?? payload ?? [];
  meta.value = payload.meta ?? meta.value;
}

function goReceipt(rid) {
  router.push({ name: "receipts.detail", params: { id: rid } });
}

function shortWarehouse(addr) {
  if (!addr) return null;
  return addr.length > 35 ? addr.slice(0, 32) + "..." : addr;
}

onMounted(async () => {
  await Promise.all([loadSupplier(), loadReceipts()]);
});

watch(page, loadReceipts);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}
.row-click {
  cursor: pointer;
}
.row-click:hover {
  background: var(--hover-background-color);
}
</style>
