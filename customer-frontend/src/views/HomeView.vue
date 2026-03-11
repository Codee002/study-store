<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" @search="onSearch" />

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
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import HeroBanner from "@/components/home/HeroBanner.vue";
import FeaturedProducts from "@/components/home/FeaturedProducts.vue";
import ProductPurchaseModal from "@/components/product/ProductPurchaseModal.vue";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import checkoutService from "@/services/checkout.service";
import ProductService from "@/services/product.service";
import { getRetailRow, getUserTierRow } from "@/utils/pricing";

const cartCount = ref(0);
const router = useRouter();
const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const keyword = ref("");
const activeCategory = ref("Tất cả");
const products = ref([]);
const showPurchaseModal = ref(false);
const selectedProduct = ref(null);
const purchaseAction = ref("cart");

function mapToHomeCard(p, currentUser) {
  const userRow = getUserTierRow(p?.prices, currentUser);
  const retailRow = getRetailRow(p?.prices, currentUser);

  const retailPrice = retailRow ? Number(retailRow.price) : null;
  const userTierPrice = userRow ? Number(userRow.price) : null;

  const price = userTierPrice != null ? userTierPrice : retailPrice != null ? retailPrice : 0;
  const oldPrice =
    userTierPrice != null && retailPrice != null && userTierPrice !== retailPrice ? retailPrice : null;
  const categoryName = p?.category?.name || p?.category_name || "Kh?c";

  return {
    id: p.id,
    name: p.name,
    category_id: Number(p?.category?.id || p?.category_id || 0),
    category: categoryName,
    price,
    oldPrice,
    rating: Number(p?.rating ?? 0),
    sold: Number(p?.sold ?? 0),
    badge: p?.badge ?? "",
    image: p?.images?.[0]?.url || p?.image || "",
    images: p?.images || [],
    colors: p?.colors || [],
    color_stocks: p?.color_stocks || [],
    prices: p?.prices || [],
    stock_quantity: Number(p?.stock_quantity || 0),
    unit: p?.unit || "",
  };
}

const categories = computed(() => {
  const set = new Set((products.value || []).map((p) => p.category).filter(Boolean));
  return ["Tất cả", ...Array.from(set)];
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
    cartCount.value = cartService.getCountFromItems(res?.cart?.items || []);
    await Swal.fire("Th?nh c?ng!", res?.message || "Th?m v?o gi? h?ng th?nh c?ng!", "success");
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      (purchaseAction.value === "buy-now" ? "??t h?ng th?t b?i." : "Th?m v?o gi? h?ng th?t b?i.");
    await Swal.fire("L?i", msg, "error");
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

async function fetchHomeProducts() {
  try {
    const res = await ProductService.getHomeProducts({ per_page: 16, page: 1, status: "actived" });
    const items = Array.isArray(res?.items) ? res.items : [];

    const activeItems = items.filter((p) => {
      if (p?.status != null) return String(p.status) === "active";
      if (p?.active != null) return Number(p.active) === 1;
      return true;
    });

    products.value = activeItems.slice(0, 16).map((p) => mapToHomeCard(p, user.value));
  } catch {
    products.value = [];
  }
}

onMounted(async () => {
  await fetchMe();
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }
  await fetchHomeProducts();
});
</script>
