<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" />

    <main class="container py-4">
      <section class="cart-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h1 class="cart-title mb-1">Giỏ hàng của bạn</h1>
            <div class="text-muted small">{{ items.length }} sản phẩm</div>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <button
              class="btn btn-outline-secondary"
              type="button"
              :disabled="!items.length || isAllSelected"
              @click="selectAll"
            >
              <i class="fa-regular fa-square-check me-2"></i>Chọn tất cả
            </button>
            <button
              class="btn btn-outline-secondary"
              type="button"
              :disabled="!selectedKeys.length"
              @click="clearSelection"
            >
              <i class="fa-regular fa-circle-xmark me-2"></i>Hủy chọn tất cả
            </button>
            <RouterLink to="/products" class="btn btn-outline-secondary">
              <i class="fa-solid fa-arrow-left me-2"></i>Tiếp tục mua sắm
            </RouterLink>
          </div>
        </div>

        <div v-if="!items.length" class="empty-box text-center">
          <i class="fa-solid fa-cart-shopping empty-icon"></i>
          <h5 class="mb-2">Giỏ hàng đang trống</h5>
          <p class="text-muted mb-3">Hãy thêm sản phẩm để bắt đầu đặt hàng.</p>
          <RouterLink to="/products" class="btn btn-main">Xem sản phẩm</RouterLink>
        </div>

        <div v-else class="row g-3">
          <div class="col-12 col-xl-8">
            <article v-for="item in items" :key="item.key" class="cart-item">
              <div class="select-col">
                <input
                  :id="`select-${item.key}`"
                  v-model="selectedKeys"
                  class="form-check-input"
                  type="checkbox"
                  :value="item.key"
                />
              </div>

              <img :src="item.image" :alt="item.name" class="item-image" />

              <div class="item-info">
                <h5 class="item-name mb-1">{{ item.name }}</h5>
                <div class="small text-muted mb-1">Danh mục: {{ item.category }}</div>
                <div class="small">
                  <span class="chip">Phân loại: {{ item.colorName }}</span>
                  <span class="chip">Số lượng: {{ item.quantity }}</span>
                  <span class="chip">Mốc giá: >= {{ item.minQuantity }}</span>
                </div>
              </div>

              <div class="item-price text-end">
                <div class="small text-muted">Đơn giá</div>
                <div class="unit-price">{{ formatVnd(item.unitPrice) }}</div>
                <div class="small text-muted mt-1">Tạm tính</div>
                <div class="subtotal">{{ formatVnd(item.subtotal) }}</div>
              </div>

              <div class="item-actions">
                <button
                  class="icon-action-btn"
                  type="button"
                  title="Chỉnh sửa"
                  aria-label="Chỉnh sửa"
                  @click="openEditItem(item)"
                >
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button
                  class="icon-action-btn danger"
                  type="button"
                  title="Xóa"
                  aria-label="Xóa"
                  @click="removeItem(item)"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </article>
          </div>

          <div class="col-12 col-xl-4">
            <aside class="summary-card">
              <h5 class="mb-3">Tóm tắt đơn hàng</h5>

              <div class="summary-row">
                <span>Sản phẩm đã chọn</span>
                <strong>{{ selectedCount }}</strong>
              </div>
              <div class="summary-row">
                <span>Tổng số lượng</span>
                <strong>{{ selectedQuantity }}</strong>
              </div>
              <div class="summary-row border-0 pt-2">
                <span>Tổng tạm tính</span>
                <strong class="price-total">{{ formatVnd(selectedSubtotal) }}</strong>
              </div>

              <button
                class="btn btn-main w-100 mt-3"
                type="button"
                :disabled="selectedCount === 0"
                @click="goCheckout"
              >
                Đặt hàng ({{ selectedCount }})
              </button>
            </aside>
          </div>
        </div>
      </section>
    </main>

    <ProductPurchaseModal
      v-model="showEditModal"
      :product="editProduct"
      :user="user"
      :initial-quantity="editingItem?.quantity || 1"
      :initial-color-id="editingItem?.colorId"
      confirm-text="Cập nhật"
      @confirm="handleConfirmEditItem"
    />

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import ProductPurchaseModal from "@/components/product/ProductPurchaseModal.vue";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import productService from "@/services/product.service";
import { getAppliedPriceRow } from "@/utils/pricing";

const cartCount = ref(0);
const router = useRouter();
const rawItems = ref([]);
const selectedKeys = ref([]);
const showEditModal = ref(false);
const editingItem = ref(null);
const editProduct = ref(null);
const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const items = computed(() =>
  (rawItems.value || []).map((it) => {
    const qty = Math.max(1, Number(it?.quantity || 1));
    const appliedRow = getAppliedPriceRow(it?.prices || [], user.value, qty);
    const unitPrice =
      it?.unit_price != null
        ? Number(it.unit_price)
        : appliedRow?.price != null
          ? Number(appliedRow.price)
          : 0;
    const minQuantity =
      it?.price_min_quantity != null
        ? Number(it.price_min_quantity)
        : appliedRow?.min_quantity != null
          ? Number(appliedRow.min_quantity)
          : 1;

    return {
      key: buildKey(it),
      cartId: Number(it?.cart_id || 0),
      cartDetailId: Number(it?.id || 0),
      productId: Number(it?.product_id || 0),
      colorId: it?.color_id == null ? null : Number(it?.color_id),
      name: it?.product_name || "Sản phẩm",
      image: it?.product_image || "https://via.placeholder.com/96x96?text=No+Image",
      category: it?.product_category || "Khác",
      categoryId: Number(it?.product_category_id || 0),
      colorName: it?.color_name || "Mặc định",
      quantity: qty,
      unitPrice,
      subtotal: unitPrice * qty,
      minQuantity,
      prices: Array.isArray(it?.prices) ? it.prices : [],
      unit: it?.unit || "",
      stockQuantity: Number(it?.stock_quantity || 0),
    };
  }),
);

const selectedItems = computed(() => items.value.filter((it) => selectedKeys.value.includes(it.key)));
const selectedCount = computed(() => selectedItems.value.length);
const selectedQuantity = computed(() =>
  selectedItems.value.reduce((sum, it) => sum + Number(it.quantity || 0), 0),
);
const selectedSubtotal = computed(() =>
  selectedItems.value.reduce((sum, it) => sum + Number(it.subtotal || 0), 0),
);
const allItemKeys = computed(() => items.value.map((it) => it.key));
const isAllSelected = computed(
  () => allItemKeys.value.length > 0 && selectedKeys.value.length === allItemKeys.value.length,
);

function buildKey(item) {
  if (item?.id) return `detail-${String(item.id)}`;
  const safeColor = item?.color_id == null ? "default" : String(item.color_id);
  return `fallback-${String(item?.product_id || 0)}-${safeColor}`;
}

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(Number(n || 0));
}

async function loadCart({ silent = true } = {}) {
  try {
    const cart = await cartService.getCart();
    rawItems.value = cart?.items || [];
    cartCount.value = cartService.getCountFromItems(rawItems.value);
  } catch (e) {
    rawItems.value = [];
    cartCount.value = 0;
    if (!silent) {
      const msg = e?.response?.data?.message || e?.response?.data?.error || "Không thể tải giỏ hàng.";
      await Swal.fire("Lỗi", msg, "error");
    }
  }

  const existingKeys = new Set((rawItems.value || []).map((it) => buildKey(it)));
  selectedKeys.value = selectedKeys.value.filter((key) => existingKeys.has(key));

  if (selectedKeys.value.length === 0 && existingKeys.size > 0) {
    selectedKeys.value = Array.from(existingKeys);
  }
}

function selectAll() {
  selectedKeys.value = [...allItemKeys.value];
}

function clearSelection() {
  selectedKeys.value = [];
}

function mapDetailToEditProduct(raw, currentItem) {
  const product = raw?.data?.product || raw?.product || raw;
  if (!product) return null;

  return {
    id: Number(product?.id || currentItem?.productId || 0),
    name: product?.name || currentItem?.name || "",
    image: product?.images?.[0]?.url || currentItem?.image || "",
    images: Array.isArray(product?.images) ? product.images : [],
    category: product?.category?.name || currentItem?.category || "Khác",
    category_id: Number(product?.category?.id || currentItem?.categoryId || 0),
    colors: Array.isArray(product?.colors) ? product.colors : [],
    color_stocks: Array.isArray(product?.color_stocks) ? product.color_stocks : [],
    prices: Array.isArray(product?.prices) ? product.prices : currentItem?.prices || [],
    stock_quantity: Number(product?.stock_quantity || currentItem?.stockQuantity || 0),
    unit: product?.unit || currentItem?.unit || "",
  };
}

async function openEditItem(item) {
  try {
    editingItem.value = item;
    const res = await productService.getCustomerProductDetail(item.productId, { status: "actived" });
    editProduct.value = mapDetailToEditProduct(res, item);

    if (!editProduct.value) {
      await Swal.fire("Lỗi", "Không thể tải thông tin sản phẩm để chỉnh sửa.", "error");
      return;
    }

    showEditModal.value = true;
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải thông tin sản phẩm để chỉnh sửa.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function handleConfirmEditItem(payload) {
  const current = editingItem.value;
  if (!current) return;

  const nextQty = Math.max(1, Number(payload?.quantity || 1));
  const sameColor = String(current.colorId ?? "") === String(payload?.color_id ?? "");
  const sameQty = Number(current.quantity || 0) === nextQty;

  try {
    if (sameColor && sameQty) return;

    if (sameColor) {
      await cartService.updateQuantity(current.cartId, current.cartDetailId, nextQty);
    } else {
      await cartService.addItem({
        product_id: Number(current.productId),
        color_id: payload?.color_id ?? null,
        quantity: nextQty,
      });
      await cartService.removeItem(current.cartId, current.cartDetailId);
    }

    await loadCart();
    await Swal.fire("Thành công!", "Đã cập nhật sản phẩm trong giỏ hàng.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.error || "Cập nhật giỏ hàng thất bại.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    editingItem.value = null;
    editProduct.value = null;
  }
}

async function removeItem(item) {
  try {
    const res = await cartService.removeItem(item.cartId, item.cartDetailId);
    await loadCart();
    await Swal.fire("Thành công!", res?.message || "Xóa sản phẩm khỏi giỏ hàng thành công!", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.error || "Xóa sản phẩm thất bại.";
    await Swal.fire("Lỗi", msg, "error");
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
  } catch {
    user.value = {
      name: "Guest",
      avatar: "/default-user-avatar.svg",
      tier_id: null,
      profile: null,
    };
  }
}

function goCheckout() {
  const ids = selectedItems.value
    .map((item) => Number(item?.cartDetailId || 0))
    .filter((id) => id > 0);

  if (!ids.length) return;

  router.push({
    name: "checkout",
    query: { ids: ids.join(",") },
  });
}

onMounted(async () => {
  await fetchMe();
  await loadCart({ silent: true });
});
</script>

<style scoped>
.cart-shell {
  min-height: 60vh;
}

.cart-title {
  font-size: 1.55rem;
  font-weight: 800;
}

.empty-box,
.summary-card,
.cart-item {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
}

.empty-box {
  padding: 40px 16px;
}

.empty-icon {
  font-size: 2rem;
  color: var(--font-extra-color);
  margin-bottom: 12px;
}

.cart-item {
  display: grid;
  grid-template-columns: auto 96px 1fr 150px auto;
  gap: 14px;
  padding: 12px;
  align-items: center;
}

.select-col {
  padding-left: 2px;
}

.item-image {
  width: 96px;
  height: 96px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.item-name {
  font-size: 1rem;
  font-weight: 700;
}

.chip {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  border: 1px solid var(--border-color);
  margin-right: 0.4rem;
  background: var(--hover-background-color);
  font-size: 0.8rem;
}

.item-price {
  min-width: 150px;
}

.unit-price {
  font-weight: 700;
}

.subtotal {
  color: #d32f2f;
  font-size: 1.1rem;
  font-weight: 800;
}

.item-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.icon-action-btn {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  color: var(--font-color);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon-action-btn:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}

.icon-action-btn.danger {
  color: #dc3545;
}

.icon-action-btn.danger:hover {
  background: rgba(220, 53, 69, 0.08);
  border-color: rgba(220, 53, 69, 0.25);
}

.summary-card {
  padding: 16px;
  position: sticky;
  top: calc(var(--header-heigh) + 16px);
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px dashed var(--border-color);
}

.price-total {
  color: #d32f2f;
  font-size: 1.2rem;
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

@media (max-width: 1199px) {
  .summary-card {
    position: static;
  }
}

@media (max-width: 991px) {
  .cart-item {
    grid-template-columns: auto 82px 1fr;
  }

  .item-image {
    width: 82px;
    height: 82px;
  }

  .item-price,
  .item-actions {
    grid-column: 3 / 4;
  }

  .item-actions {
    flex-direction: row;
    justify-content: flex-end;
    align-items: center;
  }
}

@media (max-width: 575px) {
  .cart-item {
    grid-template-columns: auto 72px 1fr;
    gap: 10px;
  }

  .item-image {
    width: 72px;
    height: 72px;
  }
}
</style>
