<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" />

    <main class="container py-4">
      <section class="price-shell">
        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-3">
          <div>
            <h1 class="price-title mb-1">Báo giá và tra cứu giá</h1>
            <p class="text-muted mb-0">Xuất file báo giá, nhập file mua vật phẩm và tra cứu giá theo sản phẩm.</p>
          </div>
          <RouterLink to="/home" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-2"></i>Quay lại
          </RouterLink>
        </div>

        <article class="panel mb-3">
          <h5 class="mb-1">1) Xuất file báo giá</h5>
          <div class="small text-muted mb-3">Nhận file Excel (.xlsx) báo giá theo tier hiện tại của tài khoản.</div>
          <button class="btn btn-main" type="button" :disabled="downloading" @click="downloadMyQuotation">
            <i v-if="downloading" class="fa-solid fa-spinner fa-spin me-1"></i>
            <i v-else class="fa-solid fa-file-arrow-down me-1"></i>
            Nhận file báo giá
          </button>
          <div v-if="downloadError" class="text-danger small mt-2">{{ downloadError }}</div>
        </article>

        <article class="panel mb-3">
          <h5 class="mb-1">2) Nhập file mua vật phẩm</h5>
          <div class="small text-muted mb-3">
            Hỗ trợ Excel (.xlsx/.xls) với cột: <b>product_id</b> hoặc <b>product_name</b>, <b>color_option</b>, <b>unit</b>,
            <b>min_quantity</b> hoặc <b>quantity</b>.
          </div>

          <div class="d-flex align-items-end gap-2 flex-wrap mb-3">
            <input
              ref="purchaseFileRef"
              class="form-control"
              type="file"
              accept=".xlsx,.xls"
              style="max-width: 360px"
              @change="onPurchaseFileChange"
            />

            <button class="btn btn-main" type="button" :disabled="!purchaseRows.length || calculatingFilePrice" @click="validatePurchaseFileRows">
              <i v-if="calculatingFilePrice" class="fa-solid fa-spinner fa-spin me-1"></i>
              Kiểm tra lại file
            </button>

            <button class="btn btn-outline-danger" type="button" :disabled="!purchaseRows.length" @click="clearPurchaseRows">
              Xóa file
            </button>
          </div>

          <div v-if="validationSummary" class="row g-2 mb-3">
            <div class="col-12 col-md-4">
              <div class="summary-card">
                <div class="summary-label">Tổng dòng</div>
                <div class="summary-value">{{ validationSummary.total_rows || 0 }}</div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="summary-card summary-valid">
                <div class="summary-label">Hợp lệ</div>
                <div class="summary-value">{{ validationSummary.valid_rows || 0 }}</div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="summary-card summary-invalid">
                <div class="summary-label">Không hợp lệ</div>
                <div class="summary-value">{{ validationSummary.invalid_rows || 0 }}</div>
              </div>
            </div>
          </div>

          <div v-if="purchaseRows.length" class="table-responsive">
            <table class="table align-middle table-sm">
              <thead>
                <tr>
                  <th style="width: 60px">#</th>
                  <th>Sản phẩm</th>
                  <th>Màu</th>
                  <th>Đơn vị</th>
                  <th class="text-end" style="width: 110px">SL đặt</th>
                  <th class="text-end" style="width: 110px">Tồn</th>
                  <th class="text-end" style="width: 170px">Đơn giá</th>
                  <th class="text-end" style="width: 170px">Thành tiền</th>
                  <th style="width: 260px">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in purchaseRows" :key="`upload-row-${row.row_no}`">
                  <td>{{ row.row_no }}</td>
                  <td>{{ row.product_name || row.product_id || "-" }}</td>
                  <td>{{ row.color_option || "-" }}</td>
                  <td>{{ row.unit || "-" }}</td>
                  <td class="text-end">{{ row.quantity || row.min_quantity || 0 }}</td>
                  <td class="text-end">{{ row.available_stock ?? "-" }}</td>
                  <td class="text-end">{{ formatVnd(row.unit_price || 0) }}</td>
                  <td class="text-end">{{ formatVnd(row.line_total || 0) }}</td>
                  <td>
                    <div class="d-flex flex-column gap-1">
                      <span class="badge" :class="row.is_valid ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis'">
                        {{ row.is_valid ? "Hợp lệ" : "Lỗi dữ liệu" }}
                      </span>
                      <div v-if="row.errors?.length" class="small text-danger">
                        {{ row.errors.join("; ") }}
                      </div>
                      <div v-if="row.warnings?.length" class="small text-warning-emphasis">
                        {{ row.warnings.join("; ") }}
                      </div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <th colspan="7" class="text-end">Tổng tạm tính</th>
                  <th class="text-end text-danger">{{ formatVnd(uploadGrandTotal) }}</th>
                  <th></th>
                </tr>
                <tr>
                  <th colspan="9" class="text-end">
                    <button
                      class="btn btn-success"
                      type="button"
                      :disabled="!hasValidRows || addingToCart"
                      @click="addValidRowsToCart"
                    >
                      <i v-if="addingToCart" class="fa-solid fa-spinner fa-spin me-1"></i>
                      Thêm giỏ hàng
                    </button>
                  </th>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="small text-muted">Chưa có dữ liệu file.</div>
        </article>

        <article class="panel">
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
            <div>
              <h5 class="mb-1">3) Tra cứu giá theo sản phẩm</h5>
              <div class="small text-muted">Tìm nhanh giá bán của sản phẩm.</div>
            </div>
            <button class="btn btn-outline-secondary btn-sm" type="button" @click="loadLookupData">
              <i class="fa-solid fa-rotate me-1"></i>Tải lại
            </button>
          </div>

          <div v-if="loading" class="small text-muted">Đang tải dữ liệu...</div>

          <div v-else class="row g-3">
            <div class="col-12">
              <label class="form-label">Sản phẩm</label>
              <input v-model.trim="keyword" class="form-control mb-2" placeholder="Tìm theo tên sản phẩm..." />
              <select v-model="selectedProductId" class="form-select" @change="onPickProduct">
                <option value="">-- Chọn sản phẩm --</option>
                <option v-for="p in filteredProducts" :key="`p-${p.id}`" :value="String(p.id)">{{ p.name }}</option>
              </select>
            </div>

            <div v-if="product" class="col-12">
              <div class="d-flex gap-3 align-items-start mb-3">
                <div class="thumb">
                  <img v-if="productThumb" :src="productThumb" alt="thumb" />
                  <div v-else class="thumb-placeholder"><i class="fa-solid fa-image"></i></div>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold">{{ product.name }}</div>
                  <div class="small text-muted">Danh mục: {{ product?.category?.name || "-" }}</div>
                  <div class="small text-muted">Tổng tồn có thể mua: {{ product?.stock_quantity ?? 0 }}</div>
                </div>
              </div>

              <div class="fw-semibold">Bảng giá theo số lượng</div>
              <div class="small text-muted">Mỗi dòng là số lượng tối thiểu và giá theo từng cấp.</div>

              <div v-if="!priceRows.length" class="small text-muted mt-2">Sản phẩm này chưa có bảng giá.</div>
              <div v-else class="row g-2 mt-2">
                <div v-for="(r, idx) in priceRows" :key="r._key" class="col-12">
                  <div class="border rounded p-2">
                    <div class="fw-semibold">Mốc giá #{{ idx + 1 }} (từ {{ r.min_quantity }} sản phẩm)</div>
                    <div class="row g-2 mt-1">
                      <div v-for="pr in r.prices" :key="`${r._key}-${pr.tier_id}`" class="col-12 col-md-6 col-xl-4">
                        <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1">
                          <span class="badge badge-tier">{{ pr.tier_code }}</span>
                          <span>{{ formatVnd(pr.price) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </article>
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
import priceQuotationService from "@/services/price-quotation.service";
import tierService from "@/services/tier.service";
import ProductService from "@/services/product.service";
import * as XLSX from "xlsx";

const cartCount = ref(0);
const user = ref({ name: "Guest", avatar: "/default-user-avatar.svg", tier_id: null, profile: null });

const loading = ref(false);
const downloading = ref(false);
const calculatingFilePrice = ref(false);

const tiers = ref([]);
const products = ref([]);
const product = ref(null);
const priceRows = ref([]);
const keyword = ref("");
const selectedProductId = ref("");

const purchaseFileRef = ref(null);
const purchaseRows = ref([]);
const validationSummary = ref(null);
const downloadError = ref("");
const addingToCart = ref(false);

const productThumb = computed(() => product.value?.images?.[0]?.url || "");
const uploadGrandTotal = computed(() =>
  (purchaseRows.value || [])
    .filter((row) => row?.is_valid)
    .reduce((sum, row) => sum + Number(row.line_total || 0), 0),
);
const hasValidRows = computed(() => (purchaseRows.value || []).length > 0 && (purchaseRows.value || []).every((r) => r?.is_valid));
// const hasValidRows = computed(() => (purchaseRows.value || []).some((r) => r?.is_valid));

const filteredProducts = computed(() => {
  const kw = keyword.value.toLowerCase().trim();
  if (!kw) return products.value || [];
  return (products.value || []).filter((p) => String(p?.name || "").toLowerCase().includes(kw));
});

const ID_HEADERS = ["product_id", "id", "ma_sp", "ma_san_pham"];
const NAME_HEADERS = ["product_name", "name", "ten_sp", "ten_san_pham", "product"];
const COLOR_HEADERS = ["color_option", "color", "mau", "mau_sac"];
const UNIT_HEADERS = ["unit", "don_vi"];
const QTY_HEADERS = ["quantity", "qty", "so_luong", "min_quantity"];

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(Number(n || 0));
}

function normalizePricesToRows(prices = []) {
  const map = new Map();
  for (const p of prices) {
    const minq = Number(p?.min_quantity || 1);
    if (!map.has(minq)) map.set(minq, { tierMap: {} });
    map.get(minq).tierMap[String(p.tier_id)] = {
      tier_id: String(p.tier_id),
      tier_code: String(p?.tier?.code || "TIER"),
      price: Number(p?.price || 0),
    };
  }

  return Array.from(map.entries())
    .sort((a, b) => Number(a[0]) - Number(b[0]))
    .map(([minq, group]) => ({
      _key: `${minq}-${Math.random()}`,
      min_quantity: Number(minq),
      prices: Object.values(group.tierMap),
    }));
}

function pickDownloadFilename(headers = {}, fallback = "bao-gia-customer.xlsx") {
  const cd = headers?.["content-disposition"] || headers?.["Content-Disposition"] || "";
  const utf8 = cd.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8?.[1]) return decodeURIComponent(utf8[1]);
  const plain = cd.match(/filename=\"?([^\"]+)\"?/i);
  return plain?.[1] || fallback;
}

async function extractBlobErrorMessage(blob) {
  try {
    const text = await blob.text();
    const parsed = JSON.parse(text || "{}");
    return parsed?.message || parsed?.error || "";
  } catch {
    return "";
  }
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
  } catch {}
}

async function loadCartCount() {
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }
}

async function loadLookupData() {
  loading.value = true;
  try {
    const [tierRes, productRes] = await Promise.all([
      tierService.getAll({ per_page: 200, status: "actived" }),
      ProductService.getHomeProducts({ per_page: 500, page: 1, status: "actived" }),
    ]);

    tiers.value = tierRes?.data?.items ?? tierRes?.data ?? tierRes ?? [];
    products.value = productRes?.items ?? [];
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải dữ liệu tra cứu giá.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    loading.value = false;
  }
}

async function onPickProduct() {
  const id = Number(selectedProductId.value || 0);
  if (!id) {
    product.value = null;
    priceRows.value = [];
    return;
  }

  try {
    const res = await ProductService.getCustomerProductDetail(id, { status: "actived" });
    const nextProduct = res?.data?.product || null;
    product.value = nextProduct;
    priceRows.value = normalizePricesToRows(nextProduct?.prices || []);
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải bảng giá của sản phẩm.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function downloadMyQuotation() {
  downloading.value = true;
  downloadError.value = "";
  try {
    const token = localStorage.getItem("access_token");
    if (!token) {
      const msg = "Vui lòng đăng nhập để tải file báo giá.";
      downloadError.value = msg;
      await Swal.fire("Cần đăng nhập", msg, "warning");
      return;
    }

    const res = await fetch(`${import.meta.env.VITE_API_BASE_URL}/api/price-quotations/my-export`, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      },
      credentials: "include",
    });

    if (!res.ok) {
      let errMsg = "Không thể xuất báo giá.";
      try {
        const asJson = await res.clone().json();
        errMsg = asJson?.message || asJson?.error || errMsg;
      } catch {
        try {
          errMsg = await res.text();
        } catch {}
      }
      downloadError.value = errMsg;
      await Swal.fire("Lỗi", errMsg, "error");
      return;
    }

    const blob = await res.blob();
    const headersObj = {};
    res.headers.forEach((v, k) => {
      headersObj[k] = v;
    });
    const fileName = pickDownloadFilename(headersObj, "bao-gia-customer.xlsx");

    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    const msg = e?.message || "Không thể xuất báo giá.";
    downloadError.value = msg;
    console.error("downloadMyQuotation error", e);
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    downloading.value = false;
  }
}

function stripAccents(text = "") {
  return text
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/Đ/g, "d");
}

function normalizeHeader(header) {
  return stripAccents(String(header || "").trim().toLowerCase()).replace(/\s+/g, "_");
}

async function onPurchaseFileChange(event) {
  const file = event?.target?.files?.[0];
  if (!file) return;

  try {
    const ext = String(file.name || "").toLowerCase();
    if (!ext.endsWith(".xlsx") && !ext.endsWith(".xls")) {
      await Swal.fire("Sai định dạng", "Chỉ hỗ trợ file Excel (.xlsx, .xls).", "warning");
      if (event?.target) event.target.value = "";
      purchaseRows.value = [];
      validationSummary.value = null;
      return;
    }

    const buffer = await file.arrayBuffer();
    const parsed = parsePurchaseXlsx(buffer);

    if (!parsed.length) {
      await Swal.fire(
        "Không đọc được file",
        "Vui lòng dùng file Excel với cột product_id/product_name, color_option, unit và min_quantity hoặc quantity.",
        "warning",
      );
      purchaseRows.value = [];
      validationSummary.value = null;
      return;
    }

    purchaseRows.value = parsed;
    await validatePurchaseFileRows();
  } catch {
    await Swal.fire("Lỗi", "Không thể đọc file đã chọn.", "error");
    purchaseRows.value = [];
    validationSummary.value = null;
    if (event?.target) event.target.value = "";
  }
}

function pickFirst(keys, normalizedMap) {
  for (const key of keys) {
    if (normalizedMap.has(key)) {
      const val = normalizedMap.get(key);
      if (val !== undefined && String(val).trim() !== "") return val;
    }
  }
  return undefined;
}

function mapRowFromRecord(record, rowNo = 1) {
  const normalized = new Map();
  for (const [rawKey, value] of Object.entries(record)) {
    normalized.set(normalizeHeader(rawKey), value);
  }

  const productIdRaw = pickFirst(ID_HEADERS, normalized);
  const productName = String(pickFirst(NAME_HEADERS, normalized) || "").trim();
  const colorOption = String(pickFirst(COLOR_HEADERS, normalized) || "").trim();
  const unit = String(pickFirst(UNIT_HEADERS, normalized) || "").trim();
  const quantityRaw = pickFirst(QTY_HEADERS, normalized);
  const quantity = Number(quantityRaw ?? 0);

  if (!productIdRaw && !productName && !quantityRaw) return null;

  return {
    row_no: rowNo,
    product_id: productIdRaw ? Number(productIdRaw) || null : null,
    product_name: productName,
    color_option: colorOption,
    unit,
    min_quantity: Number.isFinite(quantity) ? quantity : 0,
    quantity: Number.isFinite(quantity) ? quantity : 0,
    unit_price: 0,
    line_total: 0,
    available_stock: null,
    is_valid: false,
    errors: [],
    warnings: [],
    matched_product_id: null,
  };
}

function parsePurchaseXlsx(arrayBuffer) {
  const workbook = XLSX.read(arrayBuffer, { type: "array" });
  const sheetName = workbook.SheetNames[0];
  if (!sheetName) return [];
  const ws = workbook.Sheets[sheetName];
  const records = XLSX.utils.sheet_to_json(ws, { defval: "" });
  const parsed = [];
  records.forEach((rec, idx) => {
    const mapped = mapRowFromRecord(rec, idx + 2); // header is row 1
    if (mapped) parsed.push(mapped);
  });
  return parsed;
}

async function validatePurchaseFileRows() {
  if (!purchaseRows.value.length) return;

  calculatingFilePrice.value = true;
  try {
    const res = await priceQuotationService.validatePurchaseFile(
      purchaseRows.value.map((row) => ({
        row_no: row.row_no,
        product_id: row.product_id,
        product_name: row.product_name,
        color_option: row.color_option,
        unit: row.unit,
        min_quantity: row.min_quantity ?? row.quantity,
      })),
    );

    purchaseRows.value = res?.data?.rows || [];
    validationSummary.value = res?.data?.summary || null;

    const invalidRows = Number(validationSummary.value?.invalid_rows || 0);
    await Swal.fire(
      invalidRows > 0 ? "File có dòng chưa hợp lệ" : "File hợp lệ",
      invalidRows > 0
        ? `Có ${invalidRows} dòng cần sửa trước khi mua hàng.`
        : "Tất cả dòng đều đã được kiểm tra thành công.",
      invalidRows > 0 ? "warning" : "success",
    );
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể kiểm tra file mua hàng.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    calculatingFilePrice.value = false;
  }
}

function clearPurchaseRows() {
  purchaseRows.value = [];
  validationSummary.value = null;
  if (purchaseFileRef.value) purchaseFileRef.value.value = "";
}

async function addValidRowsToCart() {
  const rows = (purchaseRows.value || []).filter((r) => r?.is_valid);
  if (!rows.length) {
    await Swal.fire("Chưa có dòng hợp lệ", "Hãy kiểm tra lại file trước khi đặt hàng.", "warning");
    return;
  }

  addingToCart.value = true;
  let success = 0;
  let failed = 0;
  let failMsg = "";
  try {
    for (const row of rows) {
      const pid = Number(row.matched_product_id || row.product_id || 0);
      const colorId =
        row.matched_color_id !== undefined && row.matched_color_id !== null
          ? Number(row.matched_color_id)
          : null;
      const qty = Number(row.quantity || row.min_quantity || 0) || 1;
      if (!pid) {
        failed++;
        continue;
      }
      try {
        await cartService.addItem({ product_id: pid, color_id: colorId, quantity: qty });
        success++;
      } catch (e) {
        failed++;
        if (!failMsg && e?.response?.data?.message) failMsg = e.response.data.message;
      }
    }
    await loadCartCount();
    await Swal.fire(
      "Đặt hàng",
      `Thêm vào giỏ: ${success} dòng thành công${failed ? `, ${failed} dòng lỗi${failMsg ? `: ${failMsg}` : ""}` : ""}`,
      failed ? "warning" : "success",
    );
  } finally {
    addingToCart.value = false;
  }
}

onMounted(async () => {
  await Promise.all([fetchMe(), loadCartCount()]);
  await loadLookupData();
});
</script>

<style scoped>
.price-shell {
  min-height: 60vh;
}

.price-title {
  font-size: 1.55rem;
  font-weight: 800;
}

.panel {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 16px;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}

.btn-main:hover {
  filter: var(--brightness);
}

.thumb {
  width: 72px;
  height: 72px;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  overflow: hidden;
  display: grid;
  place-items: center;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-placeholder {
  color: var(--font-extra-color);
}

.badge-tier {
  background: var(--hover-background-color);
  color: var(--font-color);
  border: 1px solid var(--border-color);
}

.summary-card {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 12px;
  background: #fff;
}

.summary-valid {
  background: #f1fff5;
}

.summary-invalid {
  background: #fff5f5;
}

.summary-label {
  color: var(--font-extra-color);
  font-size: 0.85rem;
}

.summary-value {
  font-size: 1.35rem;
  font-weight: 800;
}
</style>