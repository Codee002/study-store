<template>
  <div class="row g-3">
    <div class="col-12 d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row">
      <div>
        <h4 class="mb-1">Thống kê giá & tồn kho</h4>
        <div class="small opacity-75">Giá nhập trung bình, giá bán trung bình, số lượng còn lại theo sản phẩm</div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" @click="fetchData" :disabled="loading">
          <i class="fa-solid fa-rotate me-1"></i> Làm mới
        </button>
        <button class="btn btn-success" @click="onExport" :disabled="loading || !items.length">
          <i class="fa-solid fa-file-export me-1"></i> Xuất Excel
        </button>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-lg-5">
              <div class="input-group">
                <span class="input-group-text bg-transparent">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  v-model="keyword"
                  type="text"
                  class="form-control bg-transparent"
                  placeholder="Tìm theo tên hoặc mã sản phẩm..."
                />
                <button class="btn btn-outline-secondary" @click="keyword = ''" v-if="keyword">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-7 d-flex justify-content-md-end gap-2">
              <span class="badge bg-secondary-subtle text-secondary align-self-center">
                Tổng: {{ meta.total }}
              </span>
            </div>
          </div>
        </div>
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
                  <th>Danh mục</th>
                  <th style="width: 160px">Giá nhập TB</th>
                  <th style="width: 160px">Giá bán TB</th>
                  <th style="width: 120px">Đã nhập</th>
                  <th style="width: 120px">Đã bán</th>
                  <th style="width: 120px">Tồn kho</th>
                </tr>
              </thead>
              <tbody v-if="items.length">
                <tr v-for="p in items" :key="p.id" class="row-click" @click="goDetail(p.id)">
                  <td class="ps-3">
                    <div class="d-flex align-items-center gap-3">
                      <div class="thumb thumb-lg">
                        <img v-if="p.image_url" :src="p.image_url" alt="thumb" />
                        <div class="thumb placeholder" v-else>
                          <i class="fa-regular fa-image"></i>
                        </div>
                      </div>
                      <div class="d-flex flex-column gap-1">
                        <div class="fw-semibold text-truncate name-ellipsis" :title="p.name">
                          {{ p.name }}
                        </div>
                        <div class="small opacity-75">{{ p.category_name || "--" }}</div>
                      </div>
                    </div>
                  </td>
                  <td>{{ p.category_name || "--" }}</td>
                  <td>{{ money(p.avg_purchase_price) }}</td>
                  <td>{{ money(p.avg_selling_price) }}</td>
                  <td><span class="badge badge-neutral">{{ p.purchased_qty }}</span></td>
                  <td><span class="badge badge-neutral">{{ p.sold_qty }}</span></td>
                  <td><span class="badge" :class="p.stock_qty <= 10 ? 'badge-off' : 'badge-on'">{{ p.stock_qty }}</span></td>
                </tr>
              </tbody>
              <tbody v-else>
                <tr>
                  <td colspan="7" class="text-center py-4 opacity-75">Không có dữ liệu.</td>
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
import Swal from "sweetalert2";
import { useRouter } from "vue-router";
import ProductStatsService from "../../services/product-stats.service";

const keyword = ref("");
const page = ref(1);
const perPage = 10;
const meta = ref({ current_page: 1, per_page: perPage, total: 0, last_page: 1 });
const items = ref([]);
const loading = ref(false);
const router = useRouter();

function money(v) {
  return Number(v || 0).toLocaleString("vi-VN") + " ₫";
}

async function fetchData() {
  loading.value = true;
  try {
    const res = await ProductStatsService.getAll({
      q: keyword.value.trim() || undefined,
      page: page.value,
      per_page: perPage,
    });
    const list = res?.data?.items ?? res?.items ?? [];
    items.value = Array.isArray(list) ? list : [];
    meta.value =
      res?.data?.meta ??
      res?.meta ?? {
        current_page: 1,
        per_page: perPage,
        total: items.value.length,
        last_page: 1,
      };
  } catch (e) {
    await Swal.fire("Lỗi", "Không thể tải thống kê sản phẩm", "error");
  } finally {
    loading.value = false;
  }
}

async function onExport() {
  loading.value = true;
  try {
    const res = await ProductStatsService.export({
      q: keyword.value.trim() || undefined,
    });
    const blob = new Blob([res.data], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "product-stats.xlsx");
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    await Swal.fire("Lỗi", "Không thể xuất file", "error");
  } finally {
    loading.value = false;
  }
}

onMounted(fetchData);

watch(keyword, async () => {
  page.value = 1;
  await fetchData();
});

watch(page, async () => {
  await fetchData();
});

function goDetail(id) {
  router.push({ name: "products.detail", params: { id } });
}
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}
.thumb {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--hover-border-color);
  background: var(--hover-background-color);
  display: flex;
  align-items: center;
  justify-content: center;
}
.thumb.thumb-lg {
  width: 80px;
  height: 80px;
}
.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumb.placeholder {
  color: var(--font-color);
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
.name-ellipsis {
  max-width: 260px;
}
.row-click {
  cursor: pointer;
}
.row-click:hover {
  background: var(--hover-background-color);
}
</style>
