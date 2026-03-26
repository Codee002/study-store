<template>
  <div>

    <main class="container py-4">
      <section class="checkout-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h1 class="checkout-title mb-1">Xác nhận đơn hàng</h1>
            <div class="text-muted small">{{ checkoutItems.length }} sản phẩm được chọn</div>
          </div>
          <RouterLink to="/cart" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-2"></i>Quay lại giỏ hàng
          </RouterLink>
        </div>

        <div v-if="loading" class="empty-box text-center">
          <i class="fa-solid fa-spinner fa-spin empty-icon"></i>
          <p class="mb-0 text-muted">Đang tải dữ liệu thanh toán...</p>
        </div>

        <div v-else-if="!checkoutItems.length" class="empty-box text-center">
          <i class="fa-solid fa-box-open empty-icon"></i>
          <h5 class="mb-2">Chưa có sản phẩm để đặt</h5>
          <p class="text-muted mb-3">Hãy chọn sản phẩm trong giỏ hàng trước khi đặt.</p>
          <RouterLink to="/cart" class="btn btn-main">Về giỏ hàng</RouterLink>
        </div>

        <div v-else class="row g-3">
          <div class="col-12 col-xl-8">
            <article v-if="checkoutNotice" class="panel mb-3 border-danger">
              <div class="text-danger fw-semibold">{{ checkoutNotice }}</div>
            </article>

            <article class="panel mb-3">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <h5 class="mb-0">Địa chỉ giao hàng</h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" @click="openAddressList = !openAddressList">
                  Chọn địa chỉ
                </button>
              </div>

              <div v-if="selectedAddress" class="selected-address">
                <div class="fw-semibold">{{ selectedAddress.name }} - {{ selectedAddress.phone }}</div>
                <div class="text-muted small mt-1">{{ selectedAddress.address }}</div>
              </div>
              <p v-else class="text-danger small mb-0">Bạn chưa chọn địa chỉ giao hàng.</p>

              <div v-if="openAddressList" class="address-list mt-3">
                <label
                  v-for="addr in addresses"
                  :key="addr.id"
                  class="address-item"
                  :for="`addr-${addr.id}`"
                >
                  <input
                    :id="`addr-${addr.id}`"
                    v-model.number="selectedAddressId"
                    class="form-check-input mt-0"
                    type="radio"
                    name="delivery-address"
                    :value="Number(addr.id)"
                  />
                  <div>
                    <div class="fw-semibold">{{ addr.name }} - {{ addr.phone }}</div>
                    <div class="small text-muted">{{ addr.address }}</div>
                  </div>
                </label>
                <p v-if="!addresses.length" class="small text-muted mb-0">
                  Chưa có địa chỉ nào. Vui lòng thêm địa chỉ trong phần tài khoản.
                </p>
              </div>
            </article>

            <article class="panel mb-3">
              <h5 class="mb-3">Sản phẩm đặt hàng</h5>

              <div v-for="item in checkoutItems" :key="item.key" class="checkout-item">
                <img :src="item.image" :alt="item.name" class="item-image" />

                <div class="item-info">
                  <h6 class="item-name mb-1" :title="item.name">{{ item.name }}</h6>
                  <div class="small text-muted">Phân loại: {{ item.colorName }}</div>
                  <div class="small text-muted">Số lượng: {{ item.quantity }}</div>
                  <div class="small text-muted">Mốc giá áp dụng: >= {{ item.minQuantity }}</div>
                </div>

                <div class="text-end item-price">
                  <div class="small text-muted">Đơn giá</div>
                  <div class="fw-semibold">{{ formatVnd(item.unitPrice) }}</div>
                  <div class="small text-muted mt-1">Thành tiền</div>
                  <div class="line-total">{{ formatVnd(item.subtotal) }}</div>
                </div>
              </div>
            </article>

            <article class="panel mb-3">
              <h5 class="mb-3">Khuyến mãi</h5>
              <div v-if="availableDiscounts.length" class="discount-list">
                <label
                  v-for="d in availableDiscounts"
                  :key="d.id"
                  class="discount-item"
                  :class="{ disabled: isDiscountDisabled(d) }"
                  :for="`discount-${d.id}`"
                >
                  <input
                    :id="`discount-${d.id}`"
                    class="form-check-input mt-0"
                    type="checkbox"
                    :checked="isDiscountChecked(d.id)"
                    :disabled="isDiscountDisabled(d)"
                    @change="toggleDiscount(d)"
                  />
                  <div class="flex-grow-1">
                    <div class="fw-semibold">{{ discountLabel(d) }}</div>
                    <div class="small text-muted">
                      Danh mục: {{ d.category_name || "Không rõ" }} | Áp dụng trên {{ formatVnd(d.eligible_subtotal) }}
                    </div>
                    <div class="small text-danger">Giảm dự kiến: -{{ formatVnd(discountPreviewAmount(d)) }}</div>
                  </div>
                </label>
              </div>
              <p v-else class="small text-muted mb-0">Không có khuyến mãi phù hợp cho đơn hàng này.</p>
            </article>

            <article class="panel">
              <h5 class="mb-3">Phương thức thanh toán</h5>
              <div v-if="payments.length" class="payment-list">
                <label
                  v-for="payment in payments"
                  :key="payment.id"
                  class="payment-item"
                  :for="`payment-${payment.id}`"
                >
                  <input
                    :id="`payment-${payment.id}`"
                    v-model.number="selectedPaymentId"
                    class="form-check-input mt-0"
                    type="radio"
                    name="payment-method"
                    :value="Number(payment.id)"
                  />
                  <span class="fw-semibold">{{ payment.name }}</span>
                </label>
              </div>
              <p v-else class="small text-danger mb-0">Hiện chưa có phương thức thanh toán đang bật.</p>
            </article>
          </div>

          <div class="col-12 col-xl-4">
            <aside class="summary-card">
              <h5 class="mb-3">Số tiền đặt hàng</h5>

              <div class="summary-row">
                <span>Tiền sản phẩm</span>
                <strong>{{ formatVnd(productSubtotal) }}</strong>
              </div>
              <div class="summary-row">
                <span>Tiền khuyến mãi</span>
                <strong class="text-danger">- {{ formatVnd(discountAmount) }}</strong>
              </div>
              <div class="summary-row">
                <span>Tiền vận chuyển</span>
                <strong>{{ formatVnd(shippingFee) }}</strong>
              </div>
              <div class="summary-row">
                <span>Thanh toán</span>
                <strong>{{ selectedPaymentName }}</strong>
              </div>
              <div class="summary-row border-0 pt-2">
                <span>Tổng tiền</span>
                <strong class="price-total">{{ formatVnd(totalAmount) }}</strong>
              </div>

              <button class="btn btn-main w-100 mt-3" type="button" :disabled="placingOrder || !canPlaceOrder" @click="placeOrder">
                {{ placingOrder ? "Đang đặt hàng..." : "Đặt hàng" }}
              </button>
            </aside>
          </div>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import Swal from "sweetalert2";
import AppFooter from "@/components/layout/AppFooter.vue";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import checkoutService from "@/services/checkout.service";
import { getAppliedPriceRow } from "@/utils/pricing";

const route = useRoute();
const router = useRouter();

const shippingFee = 30000;
const cartCount = ref(0);
const loading = ref(true);
const rawItems = ref([]);
const buyNowDraft = ref(null);
const selectedDetailIds = ref([]);
const addresses = ref([]);
const selectedAddressId = ref(0);
const discounts = ref([]);
const selectedDiscountIds = ref([]);
const payments = ref([]);
const selectedPaymentId = ref(0);
const openAddressList = ref(false);
const placingOrder = ref(false);
const checkoutErrorMessage = ref("");
const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const isBuyNowMode = computed(() => String(route.query?.mode || "") === "buy-now");

const items = computed(() => {
  const sourceItems =
    isBuyNowMode.value && buyNowDraft.value
      ? [
          {
            id: 0,
            product_id: Number(buyNowDraft.value?.product_id || 0),
            product_category_id: Number(buyNowDraft.value?.product_category_id || 0),
            product_name: buyNowDraft.value?.product_name || "",
            product_image: buyNowDraft.value?.product_image || "",
            color_id: buyNowDraft.value?.color_id ?? null,
            color_name: buyNowDraft.value?.color_name || "",
            quantity: Number(buyNowDraft.value?.quantity || 1),
            unit_price: Number(buyNowDraft.value?.unit_price || 0),
            price_min_quantity: Number(buyNowDraft.value?.price_min_quantity || 1),
            prices: Array.isArray(buyNowDraft.value?.prices) ? buyNowDraft.value.prices : [],
          },
        ]
      : rawItems.value || [];

  return sourceItems.map((it, index) => {
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
      key: isBuyNowMode.value ? `buy-now-${index}` : `detail-${String(it?.id || 0)}`,
      cartDetailId: Number(it?.id || 0),
      productId: Number(it?.product_id || 0),
      categoryId: Number(it?.product_category_id || 0),
      colorId: it?.color_id == null ? null : Number(it.color_id),
      name: it?.product_name || "Sản phẩm",
      image: it?.product_image || "https://via.placeholder.com/96x96?text=No+Image",
      colorName: it?.color_name || "Mặc định",
      quantity: qty,
      unitPrice,
      subtotal: unitPrice * qty,
      minQuantity,
      availabilityStatus: String(it?.availability_status || "available"),
      availabilityMessage: getAvailabilityText(it),
      canCheckout: Boolean(it?.can_checkout ?? true),
    };
  });
});

const checkoutItems = computed(() => {
  if (isBuyNowMode.value) return items.value;
  const selectedSet = new Set(selectedDetailIds.value.map((id) => Number(id)));
  return items.value.filter((it) => selectedSet.has(Number(it.cartDetailId)));
});

const selectedAddress = computed(() =>
  addresses.value.find((a) => Number(a?.id) === Number(selectedAddressId.value)) || null,
);
const availableDiscounts = computed(() => (Array.isArray(discounts.value) ? discounts.value : []));

const selectedDiscounts = computed(() => {
  const selectedSet = new Set(selectedDiscountIds.value.map((id) => Number(id)));
  return availableDiscounts.value.filter((d) => selectedSet.has(Number(d?.id || 0)));
});

const productSubtotal = computed(() =>
  checkoutItems.value.reduce((sum, it) => sum + Number(it.subtotal || 0), 0),
);

const categorySubtotalMap = computed(() => {
  const map = {};
  for (const item of checkoutItems.value) {
    const categoryId = Number(item?.categoryId || 0);
    if (!categoryId) continue;
    map[categoryId] = Number(map[categoryId] || 0) + Number(item?.subtotal || 0);
  }
  return map;
});

const selectedPayment = computed(
  () => payments.value.find((p) => Number(p?.id) === Number(selectedPaymentId.value)) || null,
);
const invalidCheckoutItems = computed(() => checkoutItems.value.filter((item) => !item.canCheckout));
const checkoutNotice = computed(() => {
  if (checkoutErrorMessage.value) return checkoutErrorMessage.value;
  if (invalidCheckoutItems.value.some((item) => item.availabilityStatus === "unavailable")) {
    return "Có sản phẩm trong giỏ hiện không khả dụng.";
  }
  if (invalidCheckoutItems.value.length > 0) {
    return "Có sản phẩm đã hết hàng. Vui lòng quay lại giỏ hàng để cập nhật.";
  }
  return "";
});
const canPlaceOrder = computed(() => !checkoutNotice.value);

const selectedPaymentName = computed(() => selectedPayment.value?.name || "Chưa chọn");

const discountAmount = computed(() => {
  const appliedCategories = new Set();
  return selectedDiscounts.value.reduce((sum, discount) => {
    const categoryId = Number(discount?.category_id || 0);
    if (!categoryId || appliedCategories.has(categoryId)) return sum;

    appliedCategories.add(categoryId);
    const eligibleSubtotal = Number(categorySubtotalMap.value?.[categoryId] || 0);
    const percent = Number(discount?.percent || 0);
    const value = Math.round((eligibleSubtotal * percent) / 100);
    return sum + value;
  }, 0);
});

const totalAmount = computed(() =>
  Math.max(0, Number(productSubtotal.value || 0) - Number(discountAmount.value || 0) + shippingFee),
);

function parseSelectedIds() {
  const idsRaw = route.query?.ids;
  if (!idsRaw) return [];

  const values = String(idsRaw)
    .split(",")
    .map((part) => Number(part.trim()))
    .filter((n) => Number.isInteger(n) && n > 0);

  return Array.from(new Set(values));
}

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(Number(n || 0));
}

function getAvailabilityText(item = {}) {
  const status = String(item?.availability_status || "available");
  if (status === "unavailable") return "Sản phẩm không khả dụng";
  if (status === "out_of_stock" || status === "insufficient_stock") return "Sản phẩm đã hết hàng";
  return "";
}

function discountLabel(d) {
  return `${d?.des || "Khuyến mãi"} - ${Number(d?.percent || 0)}%`;
}

function discountPreviewAmount(d) {
  const categoryId = Number(d?.category_id || 0);
  const subtotal = Number(categorySubtotalMap.value?.[categoryId] || d?.eligible_subtotal || 0);
  const percent = Number(d?.percent || 0);
  return Math.round((subtotal * percent) / 100);
}

function isDiscountChecked(discountId) {
  return selectedDiscountIds.value.includes(Number(discountId));
}

function isDiscountDisabled(discount) {
  const currentCategoryId = Number(discount?.category_id || 0);
  if (!currentCategoryId) return false;
  if (isDiscountChecked(discount?.id)) return false;

  return selectedDiscounts.value.some(
    (d) => Number(d?.category_id || 0) === currentCategoryId && Number(d?.id) !== Number(discount?.id),
  );
}

function toggleDiscount(discount) {
  const discountId = Number(discount?.id || 0);
  if (!discountId) return;

  if (isDiscountChecked(discountId)) {
    selectedDiscountIds.value = selectedDiscountIds.value.filter((id) => Number(id) !== discountId);
    return;
  }

  if (isDiscountDisabled(discount)) return;
  selectedDiscountIds.value = [...selectedDiscountIds.value, discountId];
}

function buildCheckoutScopePayload() {
  return isBuyNowMode.value
    ? {
        buy_now_item: {
          product_id: Number(checkoutItems.value[0]?.productId || 0),
          color_id: checkoutItems.value[0]?.colorId ?? null,
          quantity: Number(checkoutItems.value[0]?.quantity || 1),
        },
      }
    : {
        cart_detail_ids: checkoutItems.value.map((it) => Number(it.cartDetailId)).filter((id) => id > 0),
      };
}

async function fetchCheckoutOptions() {
  if (!checkoutItems.value.length) {
    discounts.value = [];
    payments.value = [];
    selectedDiscountIds.value = [];
    selectedPaymentId.value = 0;
    checkoutErrorMessage.value = "";
    return;
  }

  try {
    const options = await checkoutService.getCheckoutOptions(buildCheckoutScopePayload());
    checkoutErrorMessage.value = "";
    discounts.value = Array.isArray(options?.discounts) ? options.discounts : [];
    payments.value = Array.isArray(options?.payments) ? options.payments : [];

    const validDiscountIds = new Set(discounts.value.map((d) => Number(d?.id || 0)));
    selectedDiscountIds.value = selectedDiscountIds.value.filter((id) => validDiscountIds.has(Number(id)));

    const paymentIds = new Set(payments.value.map((p) => Number(p?.id || 0)));
    if (!paymentIds.has(Number(selectedPaymentId.value))) {
      selectedPaymentId.value = Number(payments.value?.[0]?.id || 0);
    }
  } catch (e) {
    discounts.value = [];
    payments.value = [];
    selectedDiscountIds.value = [];
    selectedPaymentId.value = 0;
    checkoutErrorMessage.value =
      e?.response?.data?.message || e?.response?.data?.error || "Có sản phẩm đã hết hàng.";
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

async function loadData() {
  loading.value = true;
  try {
    checkoutErrorMessage.value = "";
    const selectedIds = parseSelectedIds();
    selectedDetailIds.value = selectedIds;

    if (isBuyNowMode.value) {
      buyNowDraft.value = checkoutService.getBuyNowItem();
      rawItems.value = [];
      try {
        cartCount.value = await cartService.getCount();
        window.dispatchEvent(new Event("cart-updated"));
      } catch {
        cartCount.value = 0;
      }
    } else {
      checkoutService.clearBuyNowItem();
      buyNowDraft.value = null;

      try {
        const cart = await cartService.getCart();
        rawItems.value = cart?.items || [];
        cartCount.value = cartService.getCountFromItems(rawItems.value);
        window.dispatchEvent(new Event("cart-updated"));
      } catch {
        rawItems.value = [];
        cartCount.value = 0;
      }

      if (!selectedDetailIds.value.length && rawItems.value.length) {
        selectedDetailIds.value = rawItems.value
          .map((it) => Number(it?.id || 0))
          .filter((id) => id > 0);
      }
    }

    try {
      addresses.value = await checkoutService.getDeliveryInfos();
      const defaultAddress = addresses.value.find((a) => Boolean(a?.default));
      selectedAddressId.value = Number(defaultAddress?.id || addresses.value?.[0]?.id || 0);
    } catch {
      addresses.value = [];
      selectedAddressId.value = 0;
    }

    await fetchCheckoutOptions();
  } finally {
    loading.value = false;
  }
}

async function placeOrder() {
  if (!checkoutItems.value.length) {
    await Swal.fire("Lỗi", "Không có sản phẩm để đặt.", "error");
    return;
  }

  if (checkoutNotice.value) {
    await Swal.fire("Lỗi", checkoutNotice.value, "error");
    return;
  }

  if (!selectedAddressId.value) {
    await Swal.fire("Lỗi", "Vui lòng chọn địa chỉ giao hàng.", "error");
    return;
  }

  if (!selectedPaymentId.value) {
    await Swal.fire("Lỗi", "Vui lòng chọn phương thức thanh toán.", "error");
    return;
  }

  const payload = {
    delivery_info_id: Number(selectedAddressId.value),
    payment_id: Number(selectedPaymentId.value),
    discount_ids: selectedDiscountIds.value.map((id) => Number(id)).filter((id) => id > 0),
    ...(isBuyNowMode.value
      ? {
          buy_now_item: {
            product_id: Number(checkoutItems.value[0]?.productId || 0),
            color_id: checkoutItems.value[0]?.colorId ?? null,
            quantity: Number(checkoutItems.value[0]?.quantity || 1),
          },
        }
      : {
          cart_detail_ids: checkoutItems.value.map((it) => Number(it.cartDetailId)),
        }),
  };

  try {
    placingOrder.value = true;
    const paymentName = String(selectedPayment.value?.name || "").toLowerCase().replaceAll(" ", "");
    const isVNPay = paymentName.includes("vnpay");

    if (isVNPay) {
      const res = await checkoutService.createVNPayPayment(payload);
      const paymentUrl = String(res?.data?.payment_url || "");
      if (!paymentUrl) throw new Error("Không tạo được link thanh toán VNPay");
      window.location.href = paymentUrl;
      return;
    }

    const res = await checkoutService.placeOrder(payload);
    if (isBuyNowMode.value) checkoutService.clearBuyNowItem();

    await Swal.fire("Thành công!", res?.message || "Đặt hàng thành công!", "success");

    const orderId = Number(res?.data?.order_id || 0);
    if (orderId > 0) {
      router.push({ name: "order-detail", params: { id: orderId } });
      return;
    }
    router.push({ name: "orders" });
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.error || "Đặt hàng thất bại.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    placingOrder.value = false;
  }
}

onMounted(async () => {
  await fetchMe();
  await loadData();
});
</script>

<style scoped>
.checkout-shell {
  min-height: 60vh;
}

.checkout-title {
  font-size: 1.55rem;
  font-weight: 800;
}

.panel,
.summary-card,
.empty-box {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 16px;
}

.empty-box {
  padding: 40px 16px;
}

.empty-icon {
  font-size: 2rem;
  color: var(--font-extra-color);
  margin-bottom: 12px;
}

.selected-address {
  border: 1px dashed var(--border-color);
  border-radius: 12px;
  padding: 10px 12px;
}

.address-list {
  display: grid;
  gap: 10px;
}

.address-item {
  display: flex;
  align-items: start;
  gap: 10px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 10px;
  cursor: pointer;
}

.discount-list,
.payment-list {
  display: grid;
  gap: 10px;
}

.discount-item,
.payment-item {
  display: flex;
  align-items: start;
  gap: 10px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 10px;
  cursor: pointer;
}

.discount-item.disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.checkout-item {
  display: grid;
  grid-template-columns: 96px 1fr auto;
  gap: 12px;
  align-items: center;
  padding: 10px 0;
  border-top: 1px dashed var(--border-color);
}

.checkout-item:first-of-type {
  border-top: none;
  padding-top: 0;
}

.item-image {
  width: 96px;
  height: 96px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.item-name {
  max-width: 460px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 700;
}

.item-price {
  min-width: 140px;
}

.line-total {
  color: #d32f2f;
  font-size: 1.05rem;
  font-weight: 800;
}

.summary-card {
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

@media (max-width: 767px) {
  .checkout-item {
    grid-template-columns: 72px 1fr;
  }

  .item-image {
    width: 72px;
    height: 72px;
  }

  .item-price {
    grid-column: 2 / 3;
    text-align: left !important;
  }
}
</style>
