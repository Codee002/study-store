<template>
  <div class="row g-3">
    <!-- Header -->
    <div class="col-12 d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row">
      <div>
        <h4 class="mb-1">Chi tiết sản phẩm</h4>
        <div class="small opacity-75">Thông tin, tồn kho và bảng giá theo tier</div>
      </div>

      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'products.list' }">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
        </RouterLink>
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'products.edit', params: { id } }">
          <i class="fa-solid fa-pen-to-square me-1"></i> Chỉnh sửa
        </RouterLink>
      </div>
    </div>

    <!-- Body -->
    <div class="col-12">
      <div class="row g-3">
        <!-- Left: info + prices -->
        <div class="col-12 col-lg-8">
          <div class="card card-soft mb-3">
            <div class="card-body">
              <div class="d-flex gap-3 align-items-start flex-column flex-md-row">
                <div class="gallery-wrap">
                  <div class="thumb thumb-xl">
                    <img v-if="activeImage" :src="activeImage" alt="thumb" />
                    <div v-else class="thumb placeholder">
                      <i class="fa-regular fa-image"></i>
                    </div>
                  </div>

                  <div v-if="imageList.length > 1" class="gallery-grid mt-2">
                    <button
                      v-for="(img, idx) in imageList"
                      :key="`product-image-${idx}`"
                      type="button"
                      class="gallery-thumb"
                      :class="{ active: idx === activeImageIndex }"
                      @click="activeImageIndex = idx"
                    >
                      <img :src="img" :alt="`thumb-${idx + 1}`" />
                    </button>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <div class="fw-semibold fs-5">{{ product?.name || "—" }}</div>
                      <div class="small opacity-75">Mã SP: P{{ product?.id }}</div>
                    </div>
                    <span class="badge unit-badge">{{ product?.unit || "-" }}</span>
                  </div>
                  <div class="mt-2">
                    <div class="small opacity-75">Danh mục</div>
                    <div class="fw-semibold">{{ product?.category?.name || "—" }}</div>
                  </div>
                  <div class="mt-2" v-if="product?.colors?.length">
                    <div class="small opacity-75">Màu sắc</div>
                    <div class="d-flex flex-wrap gap-2">
                      <span class="badge badge-neutral" v-for="c in product.colors" :key="c.id">
                        {{ c.color_name || c.name || ("#" + c.id) }}
                      </span>
                    </div>
                  </div>
                  <div class="mt-3" v-if="product?.des">
                    <div class="small opacity-75">Mô tả</div>
                    <div>{{ product.des }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card card-soft">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Bảng giá theo tier</h5>
                <div class="small opacity-75">Đơn giá & min quantity</div>
              </div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th class="ps-3">Tier</th>
                      <th style="width: 180px">Min quantity</th>
                      <th style="width: 180px">Đơn giá</th>
                    </tr>
                  </thead>
                  <tbody v-if="sortedPrices.length">
                    <tr v-for="p in sortedPrices" :key="p.id">
                      <td class="ps-3">
                        <div class="fw-semibold">{{ p.tier?.name || "—" }}</div>
                        <div class="small opacity-75">{{ p.tier?.code || "" }}</div>
                      </td>
                      <td>{{ p.min_quantity }}</td>
                      <td>{{ money(p.price) }}</td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr>
                      <td colspan="3" class="text-center py-3 opacity-75">Chưa có bảng giá.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: stock -->
        <div class="col-12 col-lg-4">
          <div class="card card-soft">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Tồn kho</h5>
                <span class="badge" :class="stockSummary.total_quantity <= 10 ? 'badge-off' : 'badge-on'">
                  {{ stockSummary.total_quantity }}
                </span>
              </div>

              <div class="stat-line">
                <span class="opacity-75">Đã nhập</span>
                <span class="fw-semibold">{{ stockSummary.purchased_quantity }}</span>
              </div>
              <div class="stat-line">
                <span class="opacity-75">Đã bán</span>
                <span class="fw-semibold">{{ stockSummary.sold_quantity }}</span>
              </div>
              <div class="stat-line">
                <span class="opacity-75">Đang chờ nhập</span>
                <span class="fw-semibold">{{ stockSummary.pending_quantity }}</span>
              </div>

              <hr />

              <div class="small opacity-75 mb-2">Theo kho</div>
              <div v-if="stockSummary.warehouses?.length" class="d-flex flex-column gap-2">
                <div v-for="wh in stockSummary.warehouses" :key="wh.warehouse_id" class="d-flex justify-content-between">
                  <div class="text-truncate" :title="wh.address">{{ wh.address }}</div>
                  <span class="fw-semibold">{{ wh.quantity }}</span>
                </div>
              </div>
              <div v-else class="small opacity-75">Không có dữ liệu kho.</div>

              <hr />

              <div class="small opacity-75 mb-2">Theo màu</div>
              <div v-if="stockSummary.colors?.length" class="d-flex flex-column gap-2">
                <div v-for="c in stockSummary.colors" :key="c.color_id || c.color_name" class="d-flex justify-content-between">
                  <div>{{ c.color_name || "Không màu" }}</div>
                  <span class="fw-semibold">{{ c.quantity }}</span>
                </div>
              </div>
              <div v-else class="small opacity-75">Không có dữ liệu màu.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import Swal from "sweetalert2";
import { useRoute } from "vue-router";
import ProductService from "../../services/product.service";

const props = defineProps({ id: String });
const route = useRoute();
const product = ref(null);
const stockSummary = ref({
  total_quantity: 0,
  purchased_quantity: 0,
  sold_quantity: 0,
  pending_quantity: 0,
  warehouses: [],
  colors: [],
});
const loading = ref(false);
const activeImageIndex = ref(0);

const id = computed(() => props.id || route.params.id);

const imageList = computed(() => {
  const imgs = product.value?.images || [];
  return imgs.map((img) => img?.url).filter(Boolean);
});

const activeImage = computed(() => imageList.value[activeImageIndex.value] || imageList.value[0] || "");

const sortedPrices = computed(() => {
  const prices = product.value?.prices || [];
  return [...prices].sort((a, b) => (a.min_quantity || 0) - (b.min_quantity || 0));
});

function money(v) {
  return Number(v || 0).toLocaleString("vi-VN") + " ₫";
}

async function fetchData() {
  loading.value = true;
  try {
    const res = await ProductService.get(id.value);
    product.value = res.product || null;
    stockSummary.value = res.stock_summary || stockSummary.value;
    activeImageIndex.value = 0;
  } catch (e) {
    console.error(e);
    await Swal.fire("Lỗi", "Không thể tải chi tiết sản phẩm", "error");
  } finally {
    loading.value = false;
  }
}

onMounted(fetchData);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}
.thumb {
  width: 110px;
  height: 110px;
  border-radius: 14px;
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
  object-fit: contain;
}
.thumb.placeholder {
  color: var(--font-color);
}
.thumb.thumb-xl {
  width: 220px;
  height: 220px;
}
.gallery-wrap {
  width: 220px;
  flex: 0 0 auto;
}
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.gallery-thumb {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  overflow: hidden;
  padding: 0;
  background: var(--hover-background-color);
  aspect-ratio: 1 / 1;
}
.gallery-thumb.active {
  border-color: var(--hover-border-color);
}
.gallery-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.badge-on {
  background: color-mix(in srgb, #16a34a 16%, transparent);
  border: 1px solid color-mix(in srgb, #16a34a 40%, transparent);
  color: var(--font-color);
}
.badge-off {
  background: color-mix(in srgb, #ef4444 14%, transparent);
  border: 1px solid color-mix(in srgb, #ef4444 40%, transparent);
  color: var(--font-color);
}
.badge-neutral {
  background: color-mix(in srgb, #94a3b8 16%, transparent);
  border: 1px solid color-mix(in srgb, #94a3b8 40%, transparent);
  color: var(--font-color);
}
.unit-badge {
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  border: 1px solid color-mix(in srgb, #38bdf8 40%, transparent);
  color: var(--font-color);
  font-weight: 600;
}
.stat-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.35rem 0;
}
</style>
