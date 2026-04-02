<template>
  <div class="card product-card h-100 shadow-sm border-0">
    <RouterLink :to="`/products/${product.id}`" class="thumb">
      <img :src="product.image" class="w-100 h-100" :alt="product.name" />
      <span v-if="product.badge" class="badge badge-top">{{ product.badge }}</span>
    </RouterLink>

    <div class="card-body">
      <div class="mb-1">
        <RouterLink :to="`/products/${product.id}`" class="product-link">
          <h6 class="mb-1 fw-bold product-name">{{ product.name }}</h6>
        </RouterLink>
      </div>

      <div class="text-muted small mb-2">{{ product.category }}</div>

      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="rating"><i class="fa-solid fa-star"></i> {{ product.rating }} <span class="text-muted">({{ product.sold }}+)</span></div>
      </div>

      <div class="product-card-footer d-flex align-items-center justify-content-between gap-2">
        <div class="price-block">
          <div class="price">{{ formatVnd(product.price) }}</div>
          <div v-if="product.oldPrice != null" class="old-price">{{ formatVnd(product.oldPrice) }}</div>
        </div>
        <div class="card-actions d-flex align-items-center gap-2">
          <RouterLink
            v-if="showDetailButton"
            :to="`/products/${product.id}`"
            class="btn btn-sm btn-outline-main"
          >
            Chi tiết
          </RouterLink>
          <button class="btn btn-sm btn-outline-main" type="button" @click="emit('add-to-cart', product)">
            <i class="fa-solid fa-cart-plus me-1"></i>{{ addToCartLabel }}
          </button>
          <button class="btn btn-sm btn-main" type="button" @click="emit('buy-now', product)">
            <i class="fa-solid fa-bag-shopping me-1"></i>{{ buyNowLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const emit = defineEmits(["add-to-cart", "buy-now"]);

defineProps({
  product: { type: Object, required: true },
  showDetailButton: { type: Boolean, default: true },
  addToCartLabel: { type: String, default: "Thêm giỏ" },
  buyNowLabel: { type: String, default: "Đặt hàng" },
});

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(n || 0);
}
</script>

<style scoped>
.product-card {
  border-radius: 18px;
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
}
.thumb {
  height: 190px;
  border-radius: 18px 18px 0 0;
  overflow: hidden;
  position: relative;
  background: var(--extra-bg);
  display: block;
}
.thumb img {
  object-fit: cover;
}
.badge-top {
  position: absolute;
  top: 10px;
  left: 10px;
  background: var(--main-color);
  color: var(--dark);
  border: 1px solid var(--hover-border-color);
}
.product-link {
  text-decoration: none;
  color: inherit;
}
.product-name {
  min-height: 2.6em;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.price {
  font-weight: 800;
  color: var(--dark);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.old-price {
  font-size: 0.85rem;
  color: var(--font-extra-color);
  text-decoration: line-through;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.product-card-footer {
  min-width: 0;
}
.price-block {
  flex: 1 1 auto;
  min-width: 0;
}
.card-actions {
  flex: 0 0 auto;
  flex-wrap: nowrap;
}
.rating i {
  color: #f4b400;
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
.btn-outline-main {
  border: 1px solid var(--border-color);
  color: var(--font-color);
  background: transparent;
  font-weight: 600;
}
.card-actions .btn {
  white-space: nowrap;
  flex-shrink: 0;
}
.btn-outline-main:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}
</style>
