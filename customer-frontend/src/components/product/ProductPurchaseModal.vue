<template>
  <Teleport to="body">
    <div v-if="modelValue" class="purchase-modal-backdrop" @click.self="closeModal">
      <div class="purchase-modal-card">
        <button class="btn-close modal-close" type="button" aria-label="Close" @click="closeModal"></button>

        <div class="d-flex gap-3">
          <img :src="imageUrl" :alt="product?.name || 'product'" class="product-thumb" />

          <div class="flex-grow-1">
            <h5 class="mb-1">{{ product?.name || "-" }}</h5>
            <div class="text-muted small">Tồn kho: {{ maxQty }}</div>
            <div :class="availabilityClass">{{ availabilityText }}</div>
            <div class="price-highlight mt-2">{{ formatVnd(unitPrice) }}/{{ product?.unit || "sp" }}</div>
            <div class="small text-muted" v-if="appliedPriceRow">
              Áp dụng mốc giá từ số lượng: {{ Number(appliedPriceRow.min_quantity || 1) }}
            </div>
          </div>
        </div>

        <div class="mt-3">
          <div class="fw-semibold mb-2">Phân loại</div>
          <div class="d-flex flex-wrap gap-2">
            <button
              v-for="color in colorOptions"
              :key="`color-${String(color.color_id)}`"
              class="btn btn-sm color-btn"
              :class="{
                active: String(selectedColorId) === String(color.color_id),
                disabled: Number(color.stock_quantity || 0) <= 0,
              }"
              type="button"
              :disabled="Number(color.stock_quantity || 0) <= 0"
              @click="selectColor(color.color_id)"
            >
              {{ color.color_name || "Mặc định" }} ({{ Number(color.stock_quantity || 0) }})
            </button>
          </div>
        </div>

        <div class="mt-3">
          <div class="fw-semibold mb-2">Số lượng</div>
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary qty-btn" type="button" @click="decreaseQty">-</button>
            <input v-model.number="quantity" type="number" class="form-control qty-input" :min="1" :max="maxQty" />
            <button class="btn btn-outline-secondary qty-btn" type="button" @click="increaseQty">+</button>
          </div>
          <div class="small text-muted mt-1">Có thể chọn tối đa: {{ maxQty }}</div>
        </div>

        <div class="total-box mt-3">
          <span>Tạm tính</span>
          <strong>{{ formatVnd(totalPrice) }}</strong>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button class="btn btn-outline-secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn btn-main" type="button" :disabled="maxQty <= 0" @click="confirmAddToCart">
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { getAppliedPriceRow } from "@/utils/pricing";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  product: { type: Object, default: null },
  user: { type: Object, default: null },
  confirmText: { type: String, default: "Thêm vào giỏ hàng" },
  initialQuantity: { type: Number, default: 1 },
  initialColorId: { default: undefined },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const selectedColorId = ref(null);
const quantity = ref(1);

const imageUrl = computed(
  () =>
    props.product?.images?.[0]?.url ||
    props.product?.image ||
    "https://via.placeholder.com/120x120?text=No+Image",
);

const colorOptions = computed(() => {
  const byStocks = Array.isArray(props.product?.color_stocks) ? props.product.color_stocks : [];
  if (byStocks.length > 0) return byStocks;

  const colors = Array.isArray(props.product?.colors) ? props.product.colors : [];
  if (colors.length > 0) {
    return colors.map((it) => ({
      color_id: it?.id ?? null,
      color_name: it?.color_name || "Mặc định",
      stock_quantity: 0,
    }));
  }

  return [
    {
      color_id: null,
      color_name: "Mặc định",
      stock_quantity: Number(props.product?.stock_quantity || 0),
    },
  ];
});

const selectedColorOption = computed(() => {
  return colorOptions.value.find((it) => String(it.color_id) === String(selectedColorId.value)) || null;
});

const maxQty = computed(() => {
  const qtyByColor = Number(selectedColorOption.value?.stock_quantity || 0);
  if (qtyByColor > 0) return qtyByColor;
  return Number(props.product?.stock_quantity || 0);
});

const appliedPriceRow = computed(() => {
  return getAppliedPriceRow(props.product?.prices || [], props.user, Number(quantity.value || 1));
});

const unitPrice = computed(() => Number(appliedPriceRow.value?.price || 0));
const totalPrice = computed(() => Number(unitPrice.value) * Number(quantity.value || 0));
const availabilityText = computed(() => {
  const source = selectedColorOption.value || props.product || {};
  const status = String(source?.availability_status || props.product?.availability_status || "available");
  if (status === "unavailable") return "Sản phẩm không khả dụng";
  if (status === "out_of_stock" || status === "insufficient_stock") return "Sản phẩm đã hết hàng";
  return "Sản phẩm đang khả dụng";
});
const availabilityClass = computed(() => {
  const source = selectedColorOption.value || props.product || {};
  return String(source?.availability_status || props.product?.availability_status || "available") === "available"
    ? "small text-success mt-1"
    : "small text-danger mt-1";
});

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(Number(n || 0));
}

function initializeState() {
  const firstAvailable =
    colorOptions.value.find((it) => Number(it?.stock_quantity || 0) > 0) || colorOptions.value[0] || null;
  const preferredColor =
    colorOptions.value.find((it) => String(it?.color_id) === String(props.initialColorId)) || firstAvailable;

  selectedColorId.value = preferredColor?.color_id ?? null;

  if (maxQty.value <= 0) {
    quantity.value = 0;
    return;
  }

  const initialQty = Math.max(1, Number(props.initialQuantity || 1));
  quantity.value = Math.min(initialQty, maxQty.value);
}

function closeModal() {
  emit("update:modelValue", false);
}

function selectColor(colorId) {
  selectedColorId.value = colorId;
  if (maxQty.value <= 0) {
    quantity.value = 0;
    return;
  }
  quantity.value = Math.min(Math.max(1, Number(quantity.value || 1)), maxQty.value);
}

function decreaseQty() {
  if (maxQty.value <= 0) return;
  quantity.value = Math.max(1, Number(quantity.value || 1) - 1);
}

function increaseQty() {
  if (maxQty.value <= 0) return;
  quantity.value = Math.min(maxQty.value, Number(quantity.value || 1) + 1);
}

function confirmAddToCart() {
  if (!props.product || maxQty.value <= 0) return;

  const safeQty = Math.min(Math.max(1, Number(quantity.value || 1)), maxQty.value);
  const color = selectedColorOption.value;

  emit("confirm", {
    product_id: Number(props.product.id),
    product_name: props.product.name,
    product_image: imageUrl.value,
    product_category: props.product?.category || "Khac",
    product_category_id: Number(props.product?.category_id || 0),
    color_id: color?.color_id ?? null,
    color_name: color?.color_name || "Mặc định",
    quantity: safeQty,
    unit_price: Number(unitPrice.value || 0),
    total_price: Number(unitPrice.value || 0) * safeQty,
    price_min_quantity: Number(appliedPriceRow.value?.min_quantity || 1),
    prices: Array.isArray(props.product?.prices) ? props.product.prices : [],
    unit: props.product?.unit || "",
  });

  closeModal();
}

watch(
  () => [props.modelValue, props.product?.id],
  ([visible]) => {
    if (visible) initializeState();
  },
  { immediate: true },
);

watch(
  () => quantity.value,
  (nextVal) => {
    if (maxQty.value <= 0) {
      quantity.value = 0;
      return;
    }
    const safe = Math.min(Math.max(1, Number(nextVal || 1)), maxQty.value);
    if (safe !== Number(nextVal)) quantity.value = safe;
  },
);
</script>

<style scoped>
.purchase-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.purchase-modal-card {
  width: 100%;
  max-width: 560px;
  background: #fff;
  border-radius: 16px;
  padding: 16px;
  position: relative;
}

.modal-close {
  position: absolute;
  top: 10px;
  right: 10px;
}

.product-thumb {
  width: 120px;
  height: 120px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.price-highlight {
  font-weight: 800;
  color: #d32f2f;
}

.color-btn {
  border: 1px solid var(--border-color);
  background: #fff;
}

.color-btn.active {
  border-color: #d32f2f;
  color: #d32f2f;
  font-weight: 700;
}

.color-btn.disabled {
  opacity: 0.45;
  text-decoration: line-through;
}

.qty-btn {
  width: 38px;
}

.qty-input {
  width: 96px;
  text-align: center;
}

.total-box {
  border: 1px dashed var(--border-color);
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
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
</style>
