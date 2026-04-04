<template>
  <div>

    <main>
      <HeroBanner />

      <FeaturedProducts
        :products="filteredProducts"
        :categories="categories"
        :active-category="activeCategory"
        @change-category="activeCategory = $event"
        @add-to-cart="openPurchaseModal($event, 'cart')"
        @buy-now="openPurchaseModal($event, 'buy-now')"
      />

      <section class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0 fw-bold mb-1">Sản phẩm gợi ý</h5>
          <button class="btn btn-outline-secondary btn-sm" @click="loadMoreRecommendations">
            Tải thêm
          </button>
        </div>
        <div class="row g-3" v-if="recommendedVisible.length">
          <div v-for="p in recommendedVisible" :key="p.id" class="col-12 col-sm-6 col-lg-3">
            <ProductCard
              :product="p"
              :show-detail-button="false"
              @add-to-cart="openPurchaseModal($event, 'cart')"
              @buy-now="openPurchaseModal($event, 'buy-now')"
            />
          </div>
        </div>
        <div v-else class="text-muted text-center py-3">
          Chưa có dữ liệu gợi ý, hãy xem thêm sản phẩm để chúng tôi đề xuất.
        </div>
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
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";
import AppFooter from "@/components/layout/AppFooter.vue";
import HeroBanner from "@/components/home/HeroBanner.vue";
import FeaturedProducts from "@/components/home/FeaturedProducts.vue";
import ProductCard from "@/components/home/ProductCard.vue";
import ProductPurchaseModal from "@/components/product/ProductPurchaseModal.vue";
import cartService from "@/services/cart.service";
import checkoutService from "@/services/checkout.service";
import ProductService from "@/services/product.service";
import { useCustomerHeaderState } from "@/composables/useCustomerHeaderState";
import { getRetailRow, getUserTierRow } from "@/utils/pricing";

const router = useRouter();
const headerStore = useCustomerHeaderState();
const user = computed(() => headerStore.state.user || headerStore.defaultUser);

const keyword = ref("");
const activeCategory = ref("Tất cả");
const products = ref([]);
const recommendedAll = ref([]);
const recommendedVisible = ref([]);
const recCursor = ref(0);
const showPurchaseModal = ref(false);
const selectedProduct = ref(null);
const purchaseAction = ref("cart");

function mapToHomeCard(p, currentUser) {
  const prices = Array.isArray(p?.prices) ? p.prices : [];
  const images = Array.isArray(p?.images) ? p.images : [];
  const colors = Array.isArray(p?.colors) ? p.colors : [];
  const colorStocks = Array.isArray(p?.color_stocks) ? p.color_stocks : [];
  const userRow = getUserTierRow(prices, currentUser);
  const retailRow = getRetailRow(prices, currentUser);

  const retailPrice = retailRow ? Number(retailRow.price) : null;
  const userTierPrice = userRow ? Number(userRow.price) : null;

  const price = userTierPrice != null ? userTierPrice : retailPrice != null ? retailPrice : 0;
  const oldPrice =
    userTierPrice != null && retailPrice != null && userTierPrice !== retailPrice ? retailPrice : null;
  const categoryName = p?.category?.name || p?.category_name || p?.category || "Khác";

  return {
    id: p.id,
    name: p?.name || p?.title || "",
    category_id: Number(p?.category?.id || p?.category_id || 0),
    category: categoryName,
    price,
    oldPrice,
    rating: Number(p?.rating ?? 0),
    sold: Number(p?.sold ?? 0),
    badge: p?.badge ?? "",
    image: images?.[0]?.url || p?.image || "",
    images,
    colors,
    color_stocks: colorStocks,
    prices,
    stock_quantity: Number(p?.stock_quantity || 0),
    unit: p?.unit || "",
  };
}

const categories = computed(() => {
  const set = new Set((products.value || []).map((p) => p.category).filter(Boolean));
  return ["Tất cả", ...Array.from(set).slice(0, 5)];
});

const filteredProducts = computed(() => {
  const k = keyword.value.toLowerCase();
  return (products.value || []).filter((p) => {
    const matchKeyword = !k || (p.name || "").toLowerCase().includes(k);
    const matchCategory = activeCategory.value === "Tất cả" || p.category === activeCategory.value;
    return matchKeyword && matchCategory;
  });
});

function onSearch(k) {
  keyword.value = String(k || "");
}

function loadMoreRecommendations() {
  if (!recommendedAll.value.length) {
    fetchRecommendations(true);
    return;
  }
  recCursor.value += 8;
  if (recCursor.value >= recommendedAll.value.length) {
    // đã đi hết 24 -> refresh từ server
    fetchRecommendations(true);
    return;
  }
  recommendedVisible.value = recommendedAll.value.slice(recCursor.value, recCursor.value + 8);
}

function openPurchaseModal(product, action = "cart") {
  selectedProduct.value = product || null;
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
    window.dispatchEvent(new CustomEvent("cart-updated", {
      detail: { count: cartService.getCountFromItems(res?.cart?.items || []) },
    }));
    await Swal.fire("Th?nh c?ng!", res?.message || "Th?m v?o gi? h?ng th?nh c?ng!", "success");
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      (purchaseAction.value === "buy-now" ? "??t h?ng th?t b?i." : "Th?m v?o gi? h?ng th?t b?i.");
    await Swal.fire("L?i", msg, "error");
  }
}

async function fetchHomeProducts() {
  try {
    const res = await ProductService.getHomeProducts({ per_page: 8, page: 1, status: "actived" });
    const items = Array.isArray(res?.items) ? res.items : [];
    products.value = items.slice(0, 8).map((p) => mapToHomeCard(p, user.value));
  } catch (error) {
    console.error("[HOME] fetchHomeProducts failed", error);
    products.value = [];
  }
}

async function fetchRecommendations(refresh = false) {
  try {
    // Gửi recent_ids để AI có dữ liệu nếu user chưa có lịch sử
    const recentIds = (products.value || []).slice(0, 3).map((p) => p.id).join(",");
    const res = await ProductService.getRecommendations({
      recent_ids: recentIds,
      refresh: refresh ? 1 : undefined,
    });
    const items = res?.data?.items ?? res?.items ?? [];
    console.log("[HOME] recommendations response", { recentIds, itemCount: items.length, items, res });
    recommendedAll.value = items.map((p) => mapToHomeCard(p, user.value)).slice(0, 24);
    // fallback: nếu rỗng, dùng sản phẩm nổi bật hiện có
    if (!recommendedAll.value.length && products.value.length) {
      recommendedAll.value = products.value.slice(0, 24);
    }
    recCursor.value = 0;
    recommendedVisible.value = recommendedAll.value.slice(0, 8);
  } catch (error) {
    console.error("[HOME] fetchRecommendations failed", error);
    recommendedAll.value = [];
    recommendedVisible.value = [];
  }
}

onMounted(async () => {
  await headerStore.initHeaderState();
  await fetchHomeProducts();
  await fetchRecommendations(true);
});
</script>
