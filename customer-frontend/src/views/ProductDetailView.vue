<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" />

    <main class="container py-4">
      <section v-if="loading" class="text-center py-5 text-muted">Đang tải...</section>
      <section v-else-if="error" class="text-center py-5 text-danger">{{ error }}</section>

      <section v-else class="product-shell">
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="gallery-box">
              <img v-if="activeImage" :src="activeImage" alt="product" class="main-image" />
              <div v-else class="main-image fallback">Không có ảnh</div>

              <div class="thumb-grid mt-3">
                <button
                  v-for="(img, idx) in images"
                  :key="`thumb-${idx}`"
                  type="button"
                  class="thumb-btn"
                  :class="{ active: idx === activeImageIndex }"
                  @click="activeImageIndex = idx"
                >
                  <img :src="img" alt="thumb" />
                </button>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-6">
            <div class="info-box">
              <div class="small text-muted mb-1">{{ product.category || "Khác" }}</div>
              <h1 class="product-title">{{ product.name }}</h1>

              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="text-warning">{{ avgRatingText }}</span>
                <span class="text-muted">{{ reviewSummary.total_reviews }} đánh giá |</span>
                <span class="text-muted">{{ product.sold }} đã bán</span>
              </div>

              <div class="price-box mb-3">
                <div class="current-price">{{ formatVnd(product.price) }}</div>
                <div v-if="product.oldPrice != null" class="old-price">{{ formatVnd(product.oldPrice) }}</div>
              </div>

              <div class="d-flex flex-column gap-1 small mb-3">
                <div><span class="fw-semibold">Đơn vị:</span> {{ product.unit || "-" }}</div>
                <div><span class="fw-semibold">Còn lại:</span> {{ product.stock_quantity }}</div>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-main" type="button" @click="openPurchaseModal(product, 'cart')">
                  Thêm vào giỏ hàng
                </button>
                <button class="btn btn-buy" type="button" @click="openPurchaseModal(product, 'buy-now')">
                  Đặt hàng ngay
                </button>
              </div>
            </div>
          </div>
        </div>

        <article class="panel mt-3">
          <h4 class="section-title">Mô tả sản phẩm</h4>
          <p class="mb-0 text-muted">{{ product.des || "Đang cập nhật mô tả sản phẩm." }}</p>
        </article>

        <article class="panel mt-3">
          <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <h4 class="section-title mb-0">Đánh giá</h4>
            <div class="review-summary-chip">
              <span class="small text-muted">({{ reviewSummary.total_reviews }} đánh giá)</span>
            </div>
          </div>

          <div v-if="reviewsLoading" class="text-muted small">Đang tải đánh giá...</div>
          <div v-else-if="!reviews.length" class="text-muted">Chưa có đánh giá nào cho sản phẩm này.</div>

          <div v-else class="review-list">
            <article v-for="rv in reviews" :key="rv.id" class="review-item">
              <div class="review-head">
                <img :src="rv.reviewer.avatar || defaultReviewerAvatar" alt="avatar" class="review-avatar" />
                <div>
                  <div class="fw-semibold">{{ rv.reviewer.name }}</div>
                  <div class="small text-muted">{{ formatDateTime(rv.created_at) }}</div>
                </div>
              </div>
              <div class="text-warning mt-1">{{ "★".repeat(Math.max(0, Math.min(5, Number(rv.rating || 0)))) }}</div>
              <div class="mt-1">{{ rv.content || "" }}</div>
              <div v-if="reviewImageMedias(rv).length" class="review-media-grid mt-2">
                <img
                  v-for="m in reviewImageMedias(rv)"
                  :key="`review-media-${rv.id}-${m.id}`"
                  :src="m.url"
                  alt="review-media"
                  class="review-media-item"
                />
              </div>
            </article>
          </div>
        </article>

        <article class="panel mt-3">
          <h4 class="section-title mb-3">Sản phẩm đề xuất</h4>
          <div class="row g-3">
            <div v-for="p in relatedProducts" :key="p.id" class="col-12 col-sm-6 col-lg-3">
              <ProductCard
                :product="p"
                :show-detail-button="false"
                @add-to-cart="openPurchaseModal($event, 'cart')"
                @buy-now="openPurchaseModal($event, 'buy-now')"
              />
            </div>
          </div>
        </article>
      </section>
    </main>

    <ProductPurchaseModal
      v-model="showPurchaseModal"
      :product="selectedProduct"
      :user="user"
      :confirm-text="purchaseAction === 'buy-now' ? 'Đặt hàng' : 'Thêm vào giỏ hàng'"
      @confirm="handleConfirmPurchase"
    />

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import Swal from "sweetalert2";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import ProductCard from "@/components/home/ProductCard.vue";
import ProductPurchaseModal from "@/components/product/ProductPurchaseModal.vue";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import checkoutService from "@/services/checkout.service";
import ProductService from "@/services/product.service";
import { getRetailRow, getUserTierRow } from "@/utils/pricing";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const reviewsLoading = ref(false);
const error = ref("");
const cartCount = ref(0);
const product = ref({});
const relatedProducts = ref([]);
const reviews = ref([]);
const reviewSummary = ref({ avg_rating: 0, total_reviews: 0 });
const activeImageIndex = ref(0);

const showPurchaseModal = ref(false);
const selectedProduct = ref(null);
const purchaseAction = ref("cart");

const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const defaultReviewerAvatar = "/default-user-avatar.svg";
const productId = computed(() => Number(route.params?.id || 0));

const images = computed(() =>
  Array.isArray(product.value?.images) ? product.value.images.map((i) => i?.url).filter(Boolean) : [],
);
const activeImage = computed(() => images.value[activeImageIndex.value] || images.value[0] || "");
const avgRatingText = computed(() => Number(reviewSummary.value?.avg_rating || product.value?.rating || 0).toFixed(1));

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(Number(n || 0));
}

function formatDateTime(v) {
  if (!v) return "";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString("vi-VN");
}

function mapToDisplayProduct(raw, currentUser) {
  const userRow = getUserTierRow(raw?.prices, currentUser);
  const retailRow = getRetailRow(raw?.prices, currentUser);

  const retailPrice = retailRow ? Number(retailRow.price) : null;
  const userTierPrice = userRow ? Number(userRow.price) : null;

  const price = userTierPrice != null ? userTierPrice : retailPrice != null ? retailPrice : 0;
  const oldPrice = userTierPrice != null && retailPrice != null && userTierPrice !== retailPrice ? retailPrice : null;

  return {
    ...raw,
    category_id: Number(raw?.category?.id || raw?.category_id || 0),
    category: raw?.category?.name || "Khác",
    price,
    oldPrice,
    rating: Number(raw?.rating || 0),
    sold: Number(raw?.sold || 0),
    image: raw?.images?.[0]?.url || "",
    color_stocks: raw?.color_stocks || [],
    colors: raw?.colors || [],
    prices: raw?.prices || [],
    stock_quantity: Number(raw?.stock_quantity || 0),
    unit: raw?.unit || "",
  };
}

function mapToCard(raw, currentUser) {
  const mapped = mapToDisplayProduct(raw, currentUser);
  return {
    id: mapped.id,
    name: mapped.name,
    category_id: Number(mapped?.category_id || 0),
    category: mapped.category,
    price: mapped.price,
    oldPrice: mapped.oldPrice,
    rating: mapped.rating,
    sold: mapped.sold,
    badge: mapped.badge || "",
    image: mapped?.images?.[0]?.url || "",
    images: mapped?.images || [],
    colors: mapped?.colors || [],
    color_stocks: mapped?.color_stocks || [],
    prices: mapped?.prices || [],
    stock_quantity: Number(mapped?.stock_quantity || 0),
    unit: mapped?.unit || "",
  };
}

function mapReviewItem(raw = {}) {
  const medias = Array.isArray(raw?.medias)
    ? raw.medias
    : Array.isArray(raw?.media)
      ? raw.media
      : Array.isArray(raw?.images)
        ? raw.images
        : [];

  return {
    id: Number(raw?.id || 0),
    rating: Number(raw?.rating || 0),
    content: raw?.content == null ? "" : String(raw.content),
    created_at: raw?.created_at || null,
    reviewer: {
      name: String(raw?.reviewer?.name || "Khách hàng"),
      avatar: String(raw?.reviewer?.avatar || ""),
    },
    medias: medias
      .map((m, idx) => ({
        id: Number(m?.id || idx),
        type: String(m?.type || "image"),
        url: String(m?.url || m?.image || ""),
      }))
      .filter((m) => Boolean(m.url)),
  };
}

function normalizeMediaType(typeValue) {
  return String(typeValue || "").toLowerCase().startsWith("video") ? "video" : "image";
}

function reviewImageMedias(review) {
  return (Array.isArray(review?.medias) ? review.medias : []).filter((m) => normalizeMediaType(m?.type) === "image");
}

function openPurchaseModal(item, action = "cart") {
  selectedProduct.value = item || null;
  purchaseAction.value = action;
  showPurchaseModal.value = true;
}

async function handleConfirmPurchase(payload) {
  try {
    if (purchaseAction.value === "buy-now") {
      checkoutService.saveBuyNowItem(payload);
      await router.push({ name: "checkout", query: { mode: "buy-now" } });
      return;
    }

    const res = await cartService.addItem(payload);
    cartCount.value = cartService.getCountFromItems(res?.cart?.items || []);
    await Swal.fire("Thành công!", res?.message || "Thêm vào giỏ hàng thành công!", "success");
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      (purchaseAction.value === "buy-now" ? "Đặt hàng thất bại." : "Thêm vào giỏ hàng thất bại.");
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
    user.value = { name: "Guest", avatar: "/default-user-avatar.svg", tier_id: null, profile: null };
  }
}

async function fetchProductDetail() {
  if (!productId.value) return;

  loading.value = true;
  reviewsLoading.value = true;
  error.value = "";

  try {
    const res = await ProductService.getCustomerProductDetail(productId.value, { status: "actived" });
    const rawProduct = res?.data?.product || null;
    const rawRelated = Array.isArray(res?.data?.related_products) ? res.data.related_products : [];
    const rawReviewSummary = res?.data?.review_summary || {};
    const rawPreviewReviews = Array.isArray(res?.data?.reviews_preview) ? res.data.reviews_preview : [];

    if (!rawProduct) {
      error.value = "Không tìm thấy sản phẩm.";
      product.value = {};
      relatedProducts.value = [];
      reviews.value = [];
      return;
    }

    product.value = mapToDisplayProduct(rawProduct, user.value);
    relatedProducts.value = rawRelated.map((p) => mapToCard(p, user.value));
    reviews.value = rawPreviewReviews.map(mapReviewItem);
    reviewSummary.value = {
      avg_rating: Number(rawReviewSummary?.avg_rating || rawProduct?.rating || 0),
      total_reviews: Number(rawReviewSummary?.total_reviews || rawProduct?.reviews_count || 0),
    };

    activeImageIndex.value = 0;
  } catch {
    error.value = "Không thể tải chi tiết sản phẩm.";
  } finally {
    loading.value = false;
    reviewsLoading.value = false;
  }
}

onMounted(async () => {
  await fetchMe();
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }
  await fetchProductDetail();
});

watch(
  () => productId.value,
  async () => {
    await fetchProductDetail();
  },
);
</script>

<style scoped>
.product-shell {
  min-height: 60vh;
}

.gallery-box,
.info-box,
.panel {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 14px;
}

.main-image {
  width: 100%;
  height: 420px;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.main-image.fallback {
  display: grid;
  place-items: center;
  color: var(--font-extra-color);
}

.thumb-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
}

.thumb-btn {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
  padding: 0;
  background: var(--main-extra-bg);
}

.thumb-btn.active {
  border-color: var(--hover-border-color);
}

.thumb-btn img {
  width: 100%;
  height: 72px;
  object-fit: cover;
}

.product-title {
  font-size: 1.5rem;
  font-weight: 800;
}

.price-box {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.current-price {
  font-size: 1.55rem;
  color: #d32f2f;
  font-weight: 800;
}

.old-price {
  color: var(--font-extra-color);
  text-decoration: line-through;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}

.btn-buy {
  background: #212121;
  border: 1px solid #212121;
  color: #fff;
  font-weight: 700;
}

.review-item {
  border-top: 1px dashed var(--border-color);
  padding-top: 10px;
  margin-top: 10px;
}

.review-item:first-child {
  border-top: 0;
  padding-top: 0;
  margin-top: 0;
}

.review-head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.review-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.review-media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 8px;
}

.review-media-item {
  width: 100%;
  height: 96px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

@media (max-width: 991px) {
  .main-image {
    height: 340px;
  }
}

@media (max-width: 575px) {
  .thumb-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .main-image {
    height: 280px;
  }
}
</style>
