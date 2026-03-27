<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Tra cứu giá bán</h4>
          <div class="small opacity-75">
            Chọn sản phẩm để xem các mức giá theo số lượng và cấp
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <select
            class="form-select bg-transparent"
            v-model="selectedExportTierId"
            style="min-width: 220px"
            title="Chọn tier để xuất báo giá"
          >
            <option value="">-- Chọn tier xuất báo giá --</option>
            <option v-for="t in tiers" :key="`export-tier-${t.id}`" :value="String(t.id)">
              {{ t.name }} ({{ t.code }})
            </option>
          </select>

          <button
            class="btn btn-accent"
            type="button"
            :disabled="exporting || !selectedExportTierId"
            @click="downloadQuotation"
            title="Xuất file báo giá theo tier"
          >
            <i v-if="exporting" class="fa-solid fa-spinner fa-spin me-1"></i>
            <i v-else class="fa-solid fa-file-arrow-down me-1"></i>
            Nhận file báo giá
          </button>

          <button class="btn btn-outline-secondary" @click="$router.back()">
            <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
          </button>

          <button
            class="btn btn-outline-secondary"
            type="button"
            @click="refetch"
            :disabled="loading"
            title="Tải lại"
          >
            <i class="fa-solid fa-rotate"></i>
          </button>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-4 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i> Đang tải dữ liệu...
          </div>

          <template v-else>
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="form-label">Sản phẩm</label>

                <input
                  class="form-control bg-transparent mb-2"
                  v-model="keyword"
                  placeholder="Gõ để tìm theo tên..."
                />

                <select
                  class="form-select bg-transparent"
                  v-model="selectedProductId"
                  @change="onPickProduct"
                >
                  <option value="">-- Chọn sản phẩm --</option>
                  <option v-for="p in filteredProducts" :key="p.id" :value="String(p.id)">
                    {{ p.name }}
                    <template v-if="p?.category?.name"> ({{ p.category.name }})</template>
                  </option>
                </select>
              </div>

              <div class="col-12 col-lg-6 d-flex align-items-end gap-2">
                <button
                  class="btn btn-accent"
                  type="button"
                  :disabled="!selectedProductId"
                  @click="goEdit"
                >
                  <i class="fa-solid fa-pen-to-square me-1"></i>
                  Chỉnh sửa giá
                </button>
              </div>
            </div>

            <div v-if="product" class="d-flex gap-3 align-items-center mt-3">
              <div class="thumb">
                <img v-if="productThumb" :src="productThumb" alt="thumb" />
                <div v-else class="thumb-placeholder">
                  <i class="fa-regular fa-image"></i>
                </div>
              </div>

              <div class="flex-grow-1">
                <div class="fw-semibold fs-5">{{ product?.name || "—" }}</div>
                <div class="small opacity-75">Danh mục: {{ product?.category?.name || "—" }}</div>
                <div class="small opacity-75">ID: P{{ product?.id || "—" }}</div>
              </div>
            </div>

            <div v-if="product" class="mt-3">
              <div class="fw-semibold">Tham khảo giá nhập</div>
              <div class="small opacity-75">
                Dựa trên các phiếu nhập đã hoàn tất, có thể giúp ước lượng giá bán hợp lý.
              </div>

              <div v-if="purchaseStats.loading" class="small mt-2 opacity-75">
                <i class="fa-solid fa-spinner fa-spin me-1"></i> Dang lay thong ke gia nhap...
              </div>

              <div
                v-else-if="
                  purchaseStats.data?.total_entries > 0 &&
                  purchaseStats.data?.avg_purchase_price >= 0
                "
                class="row g-3 mt-1"
              >
                <div class="col-12 col-md-6">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Giá nhập TB</div>
                    <div class="fs-4 fw-semibold">
                      {{ formatMoney(purchaseStats.data.avg_purchase_price) }}
                    </div>
                    <div class="small opacity-75">
                      {{ purchaseStats.data.total_entries }} phiếu nhập hoàn tất
                    </div>
                  </div>
                </div>

                <div class="col-12 col-md-6">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Lần gần nhất</div>
                    <div class="fs-4 fw-semibold">
                      {{
                        purchaseStats.data.last_purchase_price
                          ? formatMoney(purchaseStats.data.last_purchase_price)
                          : "-"
                      }}
                    </div>
                    <div class="small opacity-75">
                      Tổng SL đã nhập: {{ purchaseStats.data.total_quantity ?? 0 }}
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-else-if="purchaseStats.data?.total_entries === 0"
                class="small mt-2 text-warning"
              >
                Chưa có phiếu nhập hoàn tất cho sản phẩm này.
              </div>

              <div v-else-if="purchaseStats.error" class="small mt-2 text-danger">
                {{ purchaseStats.error }}
              </div>
            </div>

            <div v-if="stockSummary" class="mt-3">
              <div class="fw-semibold">Tồn kho</div>
              <div class="small opacity-75">Theo từng kho, màu và số đang đặt</div>

              <div class="row g-3 mt-1">
                <div class="col-12 col-md-4">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Tổng tồn</div>
                    <div class="fs-4 fw-semibold">{{ stockSummary?.total_quantity ?? 0 }}</div>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Đang đặt</div>
                    <div class="fs-4 fw-semibold">{{ stockSummary?.pending_quantity ?? 0 }}</div>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Số kho</div>
                    <div class="fs-4 fw-semibold">{{ stockSummary?.warehouses?.length || 0 }}</div>
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-2">
                <div class="col-12 col-lg-6">
                  <div class="fw-semibold mb-2">Tồn theo kho</div>
                  <div v-if="!stockSummary?.warehouses?.length" class="small opacity-75">
                    Chưa có tồn kho
                  </div>
                  <div v-else class="soft-list">
                    <div
                      v-for="wh in stockSummary.warehouses"
                      :key="`wh-${wh.warehouse_id}`"
                      class="soft-item"
                    >
                      <div class="fw-semibold">{{ wh.quantity }}</div>
                      <div class="small opacity-75">
                        Kho #{{ wh.warehouse_id }} - {{ wh.address || "Chưa có địa chỉ" }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="col-12 col-lg-6">
                  <div class="fw-semibold mb-2">Tồn theo màu</div>
                  <div v-if="!stockSummary?.colors?.length" class="small opacity-75">Chưa có màu</div>
                  <div v-else class="soft-list">
                    <div
                      v-for="c in stockSummary.colors"
                      :key="`color-${c.color_id || c.color_name}`"
                      class="soft-item"
                    >
                      <div class="fw-semibold">{{ c.quantity }}</div>
                      <div class="small opacity-75">{{ c.color_name || "Không màu" }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-3">
              <div class="fw-semibold">Bảng giá theo số lượng</div>
              <div class="small opacity-75">
                Mỗi dòng là 1 mức số lượng tối thiểu + giá theo từng cấp
              </div>

              <div v-if="loadingPrices" class="py-3 text-center opacity-75">
                <i class="fa-solid fa-spinner fa-spin me-2"></i> Đang tải bảng giá...
              </div>

              <template v-else>
                <div v-if="!rows.length" class="py-4 text-center opacity-75">
                  Sản phẩm này chưa có bảng giá.
                </div>

                <div v-else class="mt-2 d-flex flex-column gap-2">
                  <div v-for="(r, idx) in rows" :key="r._key" class="row-box">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-semibold">Mức giá #{{ idx + 1 }}</div>
                      <div class="small opacity-75">
                        Min: <b>{{ r.min_quantity }}</b>
                      </div>
                    </div>

                    <div class="row g-3">
                      <div
                        v-for="(t, tierIdx) in tiers"
                        :key="`${r._key}-${t.id}`"
                        class="col-12 col-md-6 col-xl-4"
                      >
                        <div class="d-flex align-items-center gap-2 mb-1">
                          <span class="badge badge-tier">{{ t.code }}</span>
                          <span class="fw-semibold">{{ t.name }}</span>
                        </div>

                        <div class="form-control bg-transparent">
                          {{ moneyText(r.prices?.[tierIdx]?.price) }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

import PriceQuotationService from "@/services/price-quotation.service";
import ProductService from "@/services/product.service";
import TierService from "@/services/tier.service";
import WarehouseService from "@/services/warehouse.service";
import { formatMoney } from "@/utils/utils";

const router = useRouter();

const loading = ref(true);
const loadingPrices = ref(false);
const exporting = ref(false);

const tiers = ref([]);
const products = ref([]);

const keyword = ref("");
const selectedProductId = ref("");
const selectedExportTierId = ref("");

const product = ref(null);
const rows = ref([]);
const stockSummary = ref(null);
const purchaseStats = ref({ loading: false, data: null, error: null });

const productThumb = computed(() => product.value?.images?.[0]?.url || "");

const filteredProducts = computed(() => {
  const kw = keyword.value.trim().toLowerCase();
  const list = products.value || [];
  if (!kw) return list;
  return list.filter((p) => (p.name || "").toLowerCase().includes(kw));
});

function moneyText(v) {
  if (v === "" || v === null || v === undefined) return "—";
  return formatMoney(v);
}

function buildRow(minQty = 1, tierMap = null) {
  const prices = tiers.value.map((t) => ({
    tier_id: String(t.id),
    price: tierMap?.[String(t.id)] ?? "",
  }));

  return {
    _key: crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
    min_quantity: minQty,
    prices,
  };
}

function normalizePricesToRows(prices = []) {
  const map = new Map();

  for (const p of prices) {
    const minq = String(p.min_quantity);
    if (!map.has(minq)) {
      map.set(minq, { tierMap: {} });
    }
    const g = map.get(minq);
    g.tierMap[String(p.tier_id)] = p.price;
  }

  return Array.from(map.entries())
    .map(([minq, g]) => buildRow(Number(minq), g.tierMap))
    .sort((a, b) => Number(a.min_quantity) - Number(b.min_quantity));
}

function pickDownloadFilename(headers = {}, fallback = "bao-gia.csv") {
  const cd = headers?.["content-disposition"] || headers?.["Content-Disposition"] || "";
  const utf8Match = cd.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1]);

  const plainMatch = cd.match(/filename="?([^"]+)"?/i);
  if (plainMatch?.[1]) return plainMatch[1];
  return fallback;
}

async function extractBlobErrorMessage(blob) {
  try {
    const text = await blob.text();
    if (!text) return "";
    const parsed = JSON.parse(text);
    return parsed?.message || parsed?.error || "";
  } catch {
    return "";
  }
}

async function onPickProduct() {
  if (!selectedProductId.value) {
    product.value = null;
    rows.value = [];
    stockSummary.value = null;
    purchaseStats.value = { loading: false, data: null, error: null };
    return;
  }

  loadingPrices.value = true;
  purchaseStats.value = { loading: true, data: null, error: null };
  try {
    const [res, purchaseRes] = await Promise.all([
      ProductService.get(selectedProductId.value),
      ProductService.getPurchaseStats(selectedProductId.value),
    ]);
    product.value = res?.product ?? res;
    rows.value = normalizePricesToRows(res?.product?.prices || []);
    stockSummary.value = res?.stock_summary || res?.product?.stock_summary || null;
    purchaseStats.value = {
      loading: false,
      data: purchaseRes?.data || null,
      error: null,
    };
  } catch (e) {
    purchaseStats.value = {
      loading: false,
      data: null,
      error:
        e?.response?.data?.message ||
        e?.response?.data?.error ||
        "Khong the tai thong ke gia nhap.",
    };
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải bảng giá. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    loadingPrices.value = false;
  }
}

function goEdit() {
  if (!selectedProductId.value) return;
  router.push({ name: "prices.edit", params: { id: selectedProductId.value } });
}

async function downloadQuotation() {
  if (!selectedExportTierId.value) {
    await Swal.fire("Thiếu tier", "Vui lòng chọn tier để xuất báo giá.", "warning");
    return;
  }

  exporting.value = true;
  try {
    const res = await PriceQuotationService.downloadAdminExport(selectedExportTierId.value);
    const blob = new Blob([res.data], { type: "text/csv;charset=utf-8;" });
    const fileName = pickDownloadFilename(res.headers, "bao-gia-admin.csv");

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    const blobMsg =
      e?.response?.data instanceof Blob ? await extractBlobErrorMessage(e.response.data) : "";
    const msg =
      blobMsg ||
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể xuất báo giá. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    exporting.value = false;
  }
}

async function refetch() {
  loading.value = true;
  try {
    const tierRes = await TierService.getAll({ per_page: 200 });
    tiers.value = tierRes?.data?.items ?? tierRes?.data ?? tierRes ?? [];
    if (!selectedExportTierId.value && tiers.value.length) {
      selectedExportTierId.value = String(tiers.value[0].id);
    }

    const prodRes = await WarehouseService.getProductTotalQuantity({ per_page: 200 });
    products.value = prodRes?.items ?? [];
  } finally {
    loading.value = false;
  }
}

onMounted(refetch);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.btn-accent {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
}

.btn-accent:hover {
  filter: var(--brightness);
}

.thumb {
  width: 8rem;
  border-radius: 0.6rem;
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: rgba(255, 255, 255, 0.03);
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-placeholder {
  opacity: 0.6;
  font-size: 1.1rem;
}

.badge-tier {
  border-radius: 999px;
  padding: 0.4rem 0.6rem;
  background: rgba(255, 166, 0, 0.15);
  border: 1px solid rgba(255, 166, 0, 0.35);
  color: #ffa500;
  font-weight: 700;
}

.row-box {
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.02);
}

.stat-box {
  border: 1px solid var(--border-color);
  border-radius: 0.8rem;
  padding: 0.9rem 1rem;
  background: rgba(255, 255, 255, 0.02);
}

.soft-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.soft-item {
  border: 1px dashed var(--border-color);
  border-radius: 0.8rem;
  padding: 0.75rem 0.9rem;
  background: rgba(255, 255, 255, 0.02);
}
</style>
