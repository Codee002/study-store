<template>
  <div class="row g-3">
    <div class="col-12 d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row">
      <div>
        <h4 class="mb-1">Danh mục: {{ category?.name || "—" }}</h4>
        <div class="small opacity-75">
          ID: {{ id }} · Sản phẩm: {{ meta.total }} · Dữ liệu thống kê theo danh mục
        </div>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary" to="/categories">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
        </RouterLink>
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'categories.edit', params: { id } }">
          <i class="fa-solid fa-pen-to-square me-1"></i> Chỉnh sửa
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3">Sản phẩm</th>
                  <th>Giá bán TB</th>
                  <th>Giá nhập TB</th>
                  <th style="width: 120px">Tồn kho</th>
                  <th style="width: 140px">Đã bán</th>
                </tr>
              </thead>
              <tbody v-if="products.length">
                <tr v-for="p in products" :key="p.id" class="row-click" @click="goProduct(p.id)">
                  <td class="ps-3">
                    <div class="d-flex align-items-center gap-3">
                      <div class="thumb">
                        <img v-if="p.image_url" :src="p.image_url" alt="thumb" />
                        <div class="thumb placeholder" v-else><i class="fa-regular fa-image"></i></div>
                      </div>
                      <div>
                        <div class="fw-semibold text-truncate" :title="p.name">{{ shortName(p.name) }}</div>
                        <div class="small opacity-75">Mã: P{{ p.id }}</div>
                      </div>
                    </div>
                  </td>
                  <td>{{ money(p.avg_selling_price) }}</td>
                  <td>{{ money(p.avg_purchase_price) }}</td>
                  <td><span class="badge badge-neutral">{{ p.stock_qty ?? 0 }}</span></td>
                  <td><span class="badge badge-neutral">{{ p.sold_qty ?? 0 }}</span></td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr>
                  <td colspan="5" class="text-center py-4 opacity-75">Danh mục chưa có sản phẩm.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center p-3 border-top" v-if="meta.total">
            <div class="small opacity-75">
              Hiển thị
              {{ (meta.current_page - 1) * meta.per_page + 1 }} -
              {{ Math.min(meta.current_page * meta.per_page, meta.total) }} / {{ meta.total }}
            </div>
            <div class="btn-group">
              <button class="btn btn-outline-secondary btn-sm" :disabled="page === 1" @click="page--">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>Trang {{ page }}</button>
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="meta.current_page >= meta.last_page"
                @click="page++"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { useRoute, useRouter, RouterLink } from "vue-router";
import CategoryService from "../../services/category.service";
import ProductStatsService from "../../services/product-stats.service";

const route = useRoute();
const router = useRouter();
const id = route.params.id;
const category = ref(null);
const products = ref([]);
const page = ref(1);
const perPage = 10;
const meta = ref({ current_page: 1, per_page: perPage, total: 0, last_page: 1 });

function money(v) {
  return Number(v || 0).toLocaleString("vi-VN") + " ₫";
}

function shortName(name) {
  if (!name) return "—";
  return name.length > 42 ? name.slice(0, 39) + "..." : name;
}

async function loadCategory() {
  const res = await CategoryService.get(id);
  category.value = res?.data ?? res ?? null;
}

async function loadProducts() {
  const res = await ProductStatsService.getAll({
    category_id: id,
    per_page: perPage,
    page: page.value,
    sort_by: "sold_qty",
    sort_dir: "desc",
  });
  const data = res?.data ?? res ?? {};
  products.value = data.items ?? data ?? [];
  meta.value = data.meta ?? meta.value;
}

function goProduct(pid) {
  router.push({ name: "products.detail", params: { id: pid } });
}

onMounted(async () => {
  await Promise.all([loadCategory(), loadProducts()]);
});

watch(page, loadProducts);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}
.thumb {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--hover-border-color);
  background: var(--hover-background-color);
  display: flex;
  align-items: center;
  justify-content: center;
}
.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumb.placeholder {
  color: var(--font-color);
}
.badge-neutral {
  background: color-mix(in srgb, #94a3b8 16%, transparent);
  border: 1px solid color-mix(in srgb, #94a3b8 40%, transparent);
  color: var(--font-color);
}
.row-click {
  cursor: pointer;
}
.row-click:hover {
  background: var(--hover-background-color);
}
</style>
