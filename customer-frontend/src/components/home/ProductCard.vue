<template>
  <div class="card product-card h-100 shadow-sm border-0">
    <div class="thumb">
      <img :src="product.image" class="w-100 h-100" :alt="product.name" />
      <span v-if="product.badge" class="badge badge-top">{{
        product.badge
      }}</span>
    </div>

    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <h6 class="mb-1 fw-bold">{{ product.name }}</h6>
        <button class="btn btn-sm btn-like" type="button" title="Yêu thích">
          <i class="fa-regular fa-heart"></i>
        </button>
      </div>

      <div class="text-muted small mb-2">{{ product.category }}</div>

      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="rating">
          <i class="fa-solid fa-star"></i> {{ product.rating }}
          <span class="text-muted">({{ product.sold }}+)</span>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="price">{{ formatVnd(product.price) }}</div>
          <div v-if="product.oldPrice" class="old-price">
            {{ formatVnd(product.oldPrice) }}
          </div>
        </div>
        <button class="btn btn-sm btn-main" type="button">
          <i class="fa-solid fa-cart-plus me-1"></i>Thêm
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  product: { type: Object, required: true },
});

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(n);
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
.btn-like {
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
}
.btn-like:hover {
  background: var(--hover-color);
}

.price {
  font-weight: 800;
  color: var(--dark);
}
.old-price {
  font-size: 0.85rem;
  color: var(--font-extra-color);
  text-decoration: line-through;
}
.rating i {
  color: #f4b400;
} /* sao vàng nhẹ, nếu bạn muốn tuyệt đối không set màu thì bỏ dòng này */
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
