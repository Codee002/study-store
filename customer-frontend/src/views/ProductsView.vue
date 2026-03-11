<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" @search="onHeaderSearch" />

    <main class="container py-4">
      <section class="mb-3">
        <div class="d-flex flex-column flex-lg-row gap-2">
          <div class="input-group">
            <span class="input-group-text bg-white">
              <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input
              v-model.trim="searchInput"
              type="text"
              class="form-control"
              placeholder="Tìm sản phẩm..."
              @keyup.enter="onSearchSubmit"
            />
            <button class="btn btn-main" type="button" @click="onSearchSubmit">Tìm</button>
          </div>

          <select
            v-model="selectedCategory"
            class="form-select filter-select"
            @change="currentPage = 1"
          >
            <option value="ALL">Tất cả danh mục</option>
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>

          <select
            v-model="sortBy"
            class="form-select filter-select"
            @change="currentPage = 1"
          >
            <option value="default">Mặc định</option>
            <option value="price_asc">Giá tăng dần</option>
            <option value="price_desc">Giá giảm dần</option>
          </select>
        </div>
      </section>

      <section v-if="loading" class="text-center py-5 text-muted">Đang tải...</section>
      <section v-else-if="error" class="text-center py-5 text-danger">{{ error }}</section>
      <section v-else>
        <div class="row g-3">
          <div v-for="p in pagedProducts" :key="p.id" class="col-12 col-sm-6 col-lg-3">
            <ProductCard
              :product="p"
              :show-detail-button="false"
              @add-to-cart="openPurchaseModal($event, 'cart')"
              @buy-now="openPurchaseModal($event, 'buy-now')"
            />
          </div>
        </div>

        <div v-if="!pagedProducts.length" class="text-center text-muted py-5">
          Không có sản phẩm phù hợp.
        </div>

        <nav v-if="totalPages > 1" class="mt-4 d-flex justify-content-center">
          <ul class="pagination mb-0">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
              <button class="page-link" @click="goToPage(currentPage - 1)">Trước</button>
            </li>

            <li
              v-for="page in visiblePages"
              :key="page"
              class="page-item"
              :class="{ active: page === currentPage }"
            >
              <button class="page-link" @click="goToPage(page)">{{ page }}</button>
            </li>

            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
              <button class="page-link" @click="goToPage(currentPage + 1)">Sau</button>
            </li>
          </ul>
        </nav>
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
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import ProductCard from "@/components/home/ProductCard.vue";
import ProductPurchaseModal from "@/components/product/ProductPurchaseModal.vue";
import Swal from "sweetalert2";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import checkoutService from "@/services/checkout.service";
import ProductService from "@/services/product.service";
import { getRetailRow, getUserTierRow } from "@/utils/pricing";

const PAGE_SIZE = 16;
const router = useRouter();

const cartCount = ref(0);
const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const loading = ref(false);
const error = ref("");
const allProducts = ref([]);
const showPurchaseModal = ref(false);
const selectedProduct = ref(null);
const purchaseAction = ref("cart");

const searchInput = ref("");
const searchKeyword = ref("");
const selectedCategory = ref("ALL");
const sortBy = ref("default");
const currentPage = ref(1);

function mapToProductCard(p, currentUser) {
  const userRow = getUserTierRow(p?.prices, currentUser);
  const retailRow = getRetailRow(p?.prices, currentUser);

  const retailPrice = retailRow ? Number(retailRow.price) : null;
  const userTierPrice = userRow ? Number(userRow.price) : null;

  const price =
    userTierPrice != null ? userTierPrice : retailPrice != null ? retailPrice : 0;

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
    rating: Number(p?.rating || 0),
    sold: Number(p?.sold || 0),
    badge: p?.badge || "",
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
  const set = new Set((allProducts.value || []).map((p) => p.category).filter(Boolean));
  return Array.from(set);
});

const processedProducts = computed(() => {
  let list = [...(allProducts.value || [])];

  if (selectedCategory.value !== "ALL") {
    list = list.filter((p) => p.category === selectedCategory.value);
  }

  if (sortBy.value === "price_asc") {
    list.sort((a, b) => Number(a.price || 0) - Number(b.price || 0));
  } else if (sortBy.value === "price_desc") {
    list.sort((a, b) => Number(b.price || 0) - Number(a.price || 0));
  }

  return list;
});

const totalPages = computed(() => {
  const total = processedProducts.value.length;
  return total > 0 ? Math.ceil(total / PAGE_SIZE) : 1;
});

const pagedProducts = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE;
  return processedProducts.value.slice(start, start + PAGE_SIZE);
});

const visiblePages = computed(() => {
  const total = totalPages.value;
  const page = currentPage.value;
  const start = Math.max(1, page - 2);
  const end = Math.min(total, start + 4);
  const pages = [];
  for (let i = start; i <= end; i += 1) pages.push(i);
  return pages;
});

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  window.scrollTo({ top: 0, behavior: "smooth" });
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
    const me = meRes?.data || meRes;
    const meUser = me?.user || {};

    user.value = {
      ...meUser,
      name: meUser?.name || "Guest",
      avatar: meUser?.avatar || "/default-user-avatar.svg",
      tier_id: meUser?.tier_id ?? meUser?.profile?.tier_id ?? null,
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

async function fetchProducts() {
  loading.value = true;
  error.value = "";

  try {
    const res = await ProductService.getHomeProducts({
      per_page: 500,
      page: 1,
      status: "actived",
      q: searchKeyword.value || undefined,
    });

    const items = res?.items || [];
    const activeItems = items.filter((p) => {
      if (p?.status != null) return String(p.status) === "active";
      if (p?.active != null) return Number(p.active) === 1;
      return true;
    });

    allProducts.value = activeItems.map((p) => mapToProductCard(p, user.value));
    if (
      selectedCategory.value !== "ALL" &&
      !allProducts.value.some((p) => p.category === selectedCategory.value)
    ) {
      selectedCategory.value = "ALL";
    }
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
  } catch {
    error.value = "Không thể tải danh sách sản phẩm.";
    allProducts.value = [];
  } finally {
    loading.value = false;
  }
}

function onSearchSubmit() {
  searchKeyword.value = searchInput.value.trim();
  currentPage.value = 1;
  fetchProducts();
}

function onHeaderSearch(value) {
  searchInput.value = String(value || "").trim();
  onSearchSubmit();
}

onMounted(async () => {
  await fetchMe();
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }
  await fetchProducts();
});
</script>

<style scoped>
.filter-select {
  max-width: 240px;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 600;
}

.btn-main:hover {
  filter: var(--brightness);
}
</style>
