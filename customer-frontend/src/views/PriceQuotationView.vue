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
          <div class="small text-muted mb-3">Nhận file CSV báo giá theo tier hiện tại của tài khoản.</div>
          <button class="btn btn-main" type="button" :disabled="downloading" @click="downloadMyQuotation">
            <i v-if="downloading" class="fa-solid fa-spinner fa-spin me-1"></i>
            <i v-else class="fa-solid fa-file-arrow-down me-1"></i>
            Nhận file báo giá
          </button>
        </article>

        <article class="panel mb-3">
          <h5 class="mb-1">2) Nhập file mua vật phẩm</h5>
          <div class="small text-muted mb-3">Hỗ trợ CSV với cột: <b>product_id</b> hoặc <b>product_name</b>, <b>quantity</b>.</div>

          <div class="d-flex align-items-end gap-2 flex-wrap mb-3">
            <!-- <div>
              <label class="small text-muted mb-1 d-block">Tier tính giá</label>
              <select v-model="selectedFileTierId" class="form-select form-select-sm" style="min-width: 190px">
                <option value="">-- Chọn tier --</option>
                <option v-for="t in tiers" :key="`tier-file-${t.id}`" :value="String(t.id)">{{ t.name }} ({{ t.code }})</option>
              </select>
            </div> -->

            <input
              ref="purchaseFileRef"
              class="form-control"
              type="file"
              accept=".csv,text/csv"
              style="max-width: 360px"
              @change="onPurchaseFileChange"
            />

            <button class="btn btn-main" type="button" :disabled="!purchaseRows.length || calculatingFilePrice" @click="calculatePriceFromFile">
              <i v-if="calculatingFilePrice" class="fa-solid fa-spinner fa-spin me-1"></i>
              Tính giá từ file
            </button>

            <button class="btn btn-outline-danger" type="button" :disabled="!purchaseRows.length" @click="clearPurchaseRows">Xóa file</button>
          </div>

          <div v-if="purchaseRows.length" class="table-responsive">
            <table class="table align-middle table-sm">
              <thead>
                <tr>
                  <th style="width: 60px">#</th>
                  <th>Sản phẩm</th>
                  <th class="text-end" style="width: 110px">Số lượng</th>
                  <th class="text-end" style="width: 170px">Đơn giá</th>
                  <th class="text-end" style="width: 170px">Thành tiền</th>
                  <th style="width: 160px">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in purchaseRows" :key="`upload-row-${row.row_no}`">
                  <td>{{ row.row_no }}</td>
                  <td>{{ row.product_name || row.product_id || "-" }}</td>
                  <td class="text-end">{{ row.quantity }}</td>
                  <td class="text-end">{{ formatVnd(row.unit_price || 0) }}</td>
                  <td class="text-end">{{ formatVnd(row.line_total || 0) }}</td>
                  <td>
                    <span
                      class="badge"
                      :class="row.matched ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis'"
                    >
                      {{ row.matched ? "Đã dò sản phẩm" : "Chưa dò sản phẩm" }}
                    </span>
                  </td>
                </tr>
                <tr>
                  <th colspan="4" class="text-end">Tổng tạm tính</th>
                  <th class="text-end text-danger">{{ formatVnd(uploadGrandTotal) }}</th>
                  <th></th>
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
            <div class="col-12 col-lg-4">
              <label class="form-label">Sản phẩm</label>
              <input v-model.trim="keyword" class="form-control mb-2" placeholder="Tìm theo tên sản phẩm..." />
              <select v-model="selectedProductId" class="form-select" @change="onPickProduct">
                <option value="">-- Chọn sản phẩm --</option>
                <option v-for="p in filteredProducts" :key="`p-${p.id}`" :value="String(p.id)">{{ p.name }}</option>
              </select>
            </div>

            <div class="col-12 col-lg-8" v-if="product">
              <div class="d-flex gap-3 align-items-start mb-3">
                <div class="thumb">
                  <img v-if="productThumb" :src="productThumb" alt="thumb" />
                  <div v-else class="thumb-placeholder"><i class="fa-solid fa-image"></i></div>
                </div>
                <div>
                  <div class="fw-semibold">{{ product.name }}</div>
                  <div class="small text-muted">Danh mục: {{ product?.category?.name || "-" }}</div>
                </div>
              </div>

              <div class="fw-semibold">Bảng giá theo số lượng</div>
              <div class="small text-muted">Mỗi dòng là số lượng tối thiểu + giá theo từng cấp.</div>

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
const selectedFileTierId = ref("");

const productThumb = computed(() => product.value?.images?.[0]?.url || "");
const uploadGrandTotal = computed(() =>
  (purchaseRows.value || []).reduce((sum, r) => sum + Number(r.line_total || 0), 0),
);

const filteredProducts = computed(() => {
  const kw = keyword.value.toLowerCase().trim();
  if (!kw) return products.value || [];
  return (products.value || []).filter((p) => String(p?.name || "").toLowerCase().includes(kw));
});

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

function resolveUnitPriceByTierAndQty(prices = [], tierId, qty = 1) {
  const rows = (prices || [])
    .filter((p) => String(p?.tier_id || "") === String(tierId || ""))
    .sort((a, b) => Number(a?.min_quantity || 0) - Number(b?.min_quantity || 0));

  if (!rows.length) return 0;
  let picked = rows[0];
  for (const row of rows) {
    if (Number(row?.min_quantity || 0) <= Number(qty || 1)) picked = row;
  }
  return Number(picked?.price || 0);
}

function pickDownloadFilename(headers = {}, fallback = "bao-gia-customer.csv") {
  const cd = headers?.["content-disposition"] || headers?.["Content-Disposition"] || "";
  const utf8 = cd.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8?.[1]) return decodeURIComponent(utf8[1]);
  const plain = cd.match(/filename="?([^"]+)"?/i);
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

    if (!selectedFileTierId.value) {
      const meTierId = String(user.value?.tier_id || "");
      const foundMeTier = (tiers.value || []).some((t) => String(t.id) === meTierId);
      selectedFileTierId.value = foundMeTier ? meTierId : String(tiers.value?.[0]?.id || "");
    }
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
    const p = res?.data?.product || null;
    product.value = p;
    priceRows.value = normalizePricesToRows(p?.prices || []);
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải bảng giá của sản phẩm.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function downloadMyQuotation() {
  downloading.value = true;
  try {
    const res = await priceQuotationService.downloadMyExport();
    const blob = new Blob([res.data], { type: "text/csv;charset=utf-8;" });
    const fileName = pickDownloadFilename(res.headers, "bao-gia-customer.csv");

    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    const blobMsg = e?.response?.data instanceof Blob ? await extractBlobErrorMessage(e.response.data) : "";
    const msg = blobMsg || e?.response?.data?.message || "Không thể xuất báo giá.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    downloading.value = false;
  }
}

function splitLineWithDelimiter(line, delimiter) {
  const out = [];
  let current = "";
  let inQuotes = false;

  for (let i = 0; i < line.length; i += 1) {
    const ch = line[i];
    if (ch === '"') {
      inQuotes = !inQuotes;
      continue;
    }
    if (ch === delimiter && !inQuotes) {
      out.push(current.trim());
      current = "";
      continue;
    }
    current += ch;
  }

  out.push(current.trim());
  return out;
}

function detectDelimiter(lines) {
  const candidates = [",", ";", "\t"];
  let best = ",";
  let bestScore = -1;

  for (const c of candidates) {
    const parts = splitLineWithDelimiter(lines[0] || "", c).length;
    if (parts > bestScore) {
      bestScore = parts;
      best = c;
    }
  }

  return best;
}

function normalizeHeader(h) {
  return String(h || "").trim().toLowerCase().replace(/\s+/g, "_");
}

function parsePurchaseCsv(text) {
  const lines = String(text || "")
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean);

  if (!lines.length) return [];

  const delimiter = detectDelimiter(lines);
  const headers = splitLineWithDelimiter(lines[0], delimiter).map(normalizeHeader);

  const idIdx = headers.findIndex((h) => ["product_id", "id", "ma_sp", "ma_san_pham"].includes(h));
  const nameIdx = headers.findIndex((h) => ["product_name", "name", "ten_sp", "ten_san_pham", "product"].includes(h));
  const qtyIdx = headers.findIndex((h) => ["quantity", "qty", "so_luong"].includes(h));

  if (qtyIdx < 0 || (idIdx < 0 && nameIdx < 0)) return [];

  const rows = [];
  for (let i = 1; i < lines.length; i += 1) {
    const cols = splitLineWithDelimiter(lines[i], delimiter);
    const quantity = Math.max(0, Number(cols[qtyIdx] || 0));
    if (!quantity) continue;

    rows.push({
      row_no: i,
      product_id: idIdx >= 0 ? Number(cols[idIdx] || 0) || null : null,
      product_name: nameIdx >= 0 ? String(cols[nameIdx] || "").trim() : "",
      quantity,
      unit_price: 0,
      line_total: 0,
      matched: false,
      matched_product_id: null,
    });
  }

  return rows;
}

async function onPurchaseFileChange(event) {
  const file = event?.target?.files?.[0];
  if (!file) return;

  try {
    const text = await file.text();
    const parsed = parsePurchaseCsv(text);

    if (!parsed.length) {
      await Swal.fire(
        "Không đọc được file",
        "Vui lòng dùng file CSV với cột product_id/product_name và quantity.",
        "warning",
      );
      purchaseRows.value = [];
      return;
    }

    purchaseRows.value = parsed;
  } catch {
    await Swal.fire("Lỗi", "Không thể đọc file đã chọn.", "error");
    purchaseRows.value = [];
    if (event?.target) event.target.value = "";
  }
}

function findProductByRow(row) {
  if (row.product_id) {
    const byId = (products.value || []).find((p) => Number(p?.id) === Number(row.product_id));
    if (byId) return byId;
  }

  const nameNeedle = String(row.product_name || "").trim().toLowerCase();
  if (!nameNeedle) return null;
  return (products.value || []).find((p) => String(p?.name || "").trim().toLowerCase() === nameNeedle) || null;
}

async function getProductDetailCached(productId) {
  const res = await ProductService.getCustomerProductDetail(Number(productId), { status: "actived" });
  return res?.data?.product || null;
}

async function calculatePriceFromFile() {
  if (!purchaseRows.value.length) return;

  if (!selectedFileTierId.value) {
    await Swal.fire("Thiếu tier", "Vui lòng chọn tier để tính giá cho file.", "warning");
    return;
  }

  calculatingFilePrice.value = true;
  try {
    const nextRows = [];
    for (const row of purchaseRows.value) {
      const matchedBase = findProductByRow(row);
      if (!matchedBase) {
        nextRows.push({ ...row, unit_price: 0, line_total: 0, matched: false, matched_product_id: null });
        continue;
      }

      const detail = await getProductDetailCached(matchedBase.id);
      const unitPrice = resolveUnitPriceByTierAndQty(
        detail?.prices || [],
        selectedFileTierId.value,
        Number(row.quantity || 1),
      );

      nextRows.push({
        ...row,
        product_name: row.product_name || matchedBase?.name || "",
        product_id: row.product_id || matchedBase?.id || null,
        unit_price: unitPrice,
        line_total: Number(unitPrice || 0) * Number(row.quantity || 0),
        matched: true,
        matched_product_id: Number(matchedBase.id || 0),
      });
    }

    purchaseRows.value = nextRows;
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tính giá từ file.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    calculatingFilePrice.value = false;
  }
}

function clearPurchaseRows() {
  purchaseRows.value = [];
  if (purchaseFileRef.value) purchaseFileRef.value.value = "";
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
</style>