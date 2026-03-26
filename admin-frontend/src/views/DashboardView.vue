<template>
  <div class="dashboard-page">
    <div class="card card-soft hero-card">
      <div class="card-body hero-body">
        <div class="hero-left">
          <div class="eyebrow">Bảng điều khiển</div>
          <h2 class="hero-title">Trang chủ quản trị</h2>
          <p class="hero-sub">
            Toàn cảnh đơn hàng, doanh thu, chi phí và tồn kho theo thời gian thực.
          </p>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <RouterLink class="btn btn-main" to="/orders/create">
              <i class="bi bi-plus-circle me-1"></i>
              Tạo đơn mới
            </RouterLink>
            <RouterLink class="btn btn-outline-dark" to="/products/create">
              <i class="bi bi-box-seam me-1"></i>
              Thêm sản phẩm
            </RouterLink>
            <button class="btn btn-soft" @click="load" :disabled="loading">
              <i class="bi bi-arrow-clockwise me-1"></i>
              {{ loading ? "Đang tải..." : "Làm mới" }}
            </button>
          </div>

          <div class="small opacity-75 mt-2">
            Cập nhật: {{ lastUpdated ? formatTime(lastUpdated) : "Đang tải dữ liệu..." }} · Kỳ:
            {{ dateRangeLabel }}
          </div>
        </div>

        <div class="hero-right">
          <div class="kpi-pill">
            <div class="small opacity-75">Doanh thu hôm nay</div>
            <div class="kpi-value">{{ loading ? "..." : money(summary.kpis.revenue_today) }}</div>
          </div>
          <div class="kpi-pill">
            <div class="small opacity-75">Đơn hoàn tất</div>
            <div class="kpi-value">{{ loading ? "..." : summary.kpis.orders_completed_today }}</div>
          </div>
          <div class="kpi-pill subtle">
            <div class="small opacity-75">Giá trị đơn TB</div>
            <div class="kpi-value">{{ loading ? "..." : money(summary.kpis.avg_order_value_today) }}</div>
          </div>
        </div>
      </div>
    </div>

    <section class="section">
      <div class="section-header">
        <div>
          <div class="eyebrow">Chỉ số nhanh</div>
          <h5 class="mb-0">Ảnh chụp hôm nay</h5>
        </div>
        <span class="badge bg-secondary-subtle text-secondary">{{ spotlightCards.length }} chỉ số</span>
      </div>

      <div class="row g-3 g-lg-4">
        <div class="col-12 col-sm-6 col-xl-3" v-for="c in spotlightCards" :key="c.label">
          <StatCard :label="c.label" :value="loading ? '...' : c.value" :icon="c.icon" />
        </div>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <div>
          <div class="eyebrow">Tổng quan</div>
          <h6 class="mb-0">Hoạt động 30 ngày</h6>
        </div>
        <span class="badge bg-secondary-subtle text-secondary">{{ detailCards.length }} chỉ số</span>
      </div>

      <div class="row g-3 g-lg-4">
        <div class="col-6 col-md-4 col-xl-3" v-for="c in detailCards" :key="c.label">
          <StatCard :label="c.label" :value="loading ? '...' : c.value" :icon="c.icon" />
        </div>
      </div>
    </section>

    <div class="row g-3 g-lg-4 align-items-stretch">
      <div class="col-12 col-xl-8">
        <DashboardLineChart
          :labels="financeLabels"
          :datasets="financeDatasets"
          title="Doanh thu · Chi phí · Lợi nhuận"
          subtitle="Chỉ tính đơn đã hoàn thành (30 ngày)"
          :height="360"
          :showLegend="true"
        >
          <template #badge>
            <span class="badge bg-secondary-subtle text-secondary">
              Lợi nhuận 30 ngày: {{ money(summary.profit.last_30_days) }}
            </span>
          </template>
        </DashboardLineChart>
      </div>
      <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <DashboardDonutChart
          title="Cơ cấu trạng thái"
          subtitle="Đơn 30 ngày"
          :labels="statusLabels"
          :data="statusValues"
          :height="250"
        >
          <template #badge>
            <span class="badge bg-secondary-subtle text-secondary">
              {{ totalOrdersRange }} đơn
            </span>
          </template>
        </DashboardDonutChart>
        <DashboardDonutChart
          title="Tỷ trọng danh mục"
          subtitle="Doanh thu 30 ngày"
          :labels="categoryLabels"
          :data="categoryValues"
          :height="220"
          :legend="false"
        >
          <template #badge>
            <span class="badge bg-secondary-subtle text-secondary">
              {{ summary.products.category_share.length }} nhóm
            </span>
          </template>
        </DashboardDonutChart>
      </div>
    </div>

    <div class="row g-3 g-lg-4 align-items-stretch mt-1">
      <div class="col-12 col-xl-8">
        <DashboardLineChart
          :labels="purchaseLabels"
          :datasets="purchaseDatasets"
          title="Nhập hàng 30 ngày"
          subtitle="Giá trị và số lượng nhập (phiếu đã hoàn thành)"
          :height="320"
          :showLegend="true"
        >
          <template #badge>
            <span class="badge bg-secondary-subtle text-secondary">
              Tổng chi phí: {{ money(summary.purchases.last_30_days_value) }}
            </span>
          </template>
        </DashboardLineChart>
      </div>
      <div class="col-12 col-xl-4">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="section-header mb-2">
              <div>
                <div class="eyebrow">Lối tắt</div>
                <div class="fw-semibold">Xử lý nhanh</div>
              </div>
            </div>
            <div class="row g-2">
              <div class="col-12 col-md-6" v-for="link in quickLinks" :key="link.title">
                <RouterLink class="quick-link" :to="link.to">
                  <div class="d-flex align-items-center gap-2">
                    <i :class="link.icon + ' fs-5'"></i>
                    <div>
                      <div class="fw-semibold">{{ link.title }}</div>
                      <div class="small opacity-75">{{ link.desc }}</div>
                    </div>
                  </div>
                  <i class="bi bi-arrow-right-short fs-5"></i>
                </RouterLink>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 g-lg-4 mt-1">
      <div class="col-12">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <div class="eyebrow">Đánh giá</div>
                <div class="fw-semibold">Phân bố sao (toàn hệ thống)</div>
              </div>
              <span class="badge bg-secondary-subtle text-secondary">
                TB {{ (summary.evaluations?.average ?? 0).toFixed(2) }} · {{ summary.evaluations?.total || 0 }} đánh giá
              </span>
            </div>
            <div class="rating-bars">
              <div class="rating-row" v-for="r in ratingBars" :key="r.star">
                <div class="star-label">
                  <i class="fa-solid fa-star text-warning"></i>
                  <span class="ms-1">{{ r.star }} sao</span>
                </div>
                <div class="flex-grow-1 mx-2 progress-wrap">
                  <div class="progress rating-progress">
                    <div
                      class="progress-bar rating-progress-bar"
                      role="progressbar"
                      :style="{ width: r.percent + '%' }"
                    ></div>
                  </div>
                </div>
                <div class="small text-end" style="width: 110px">
                  {{ r.count }} ({{ r.percent.toFixed(1) }}%)
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 g-lg-4 mt-1">
      <div class="col-12">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <div class="eyebrow">Thanh toán</div>
                <div class="fw-semibold">Doanh thu theo phương thức</div>
              </div>
              <span class="badge bg-secondary-subtle text-secondary">
                Tổng: {{ money(summary.payments?.total_amount || 0) }}
              </span>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr class="small opacity-75">
                    <th>Phương thức</th>
                    <th style="width: 120px">Đơn</th>
                    <th style="width: 160px">Tổng tiền</th>
                  </tr>
                </thead>
                <tbody v-if="paymentRows.length">
                  <tr v-for="p in paymentRows" :key="p.payment_id || p.name">
                    <td>{{ p.name }}</td>
                    <td>{{ p.orders }}</td>
                    <td>{{ money(p.total_amount) }}</td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="3" class="text-center py-3 opacity-75">Chưa có dữ liệu</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 g-lg-4 mt-1">
      <div class="col-12">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="section-header mb-2">
              <div>
                <div class="eyebrow">Tồn kho</div>
                <div class="fw-semibold">Hàng sắp hết (dưới 100)</div>
              </div>
              <span class="badge bg-warning-subtle text-warning">{{ lowStockCount }} mục</span>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr class="small opacity-75">
                    <th>Sản phẩm</th>
                    <th style="width: 120px">Tồn kho</th>
                  </tr>
                </thead>
                <tbody v-if="filteredLowStock.length">
                  <tr v-for="p in filteredLowStock" :key="p.product_id">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="thumb thumb-sm">
                          <img v-if="p.image_url" :src="p.image_url" alt="thumb" />
                          <div v-else class="thumb placeholder"><i class="fa-regular fa-image"></i></div>
                        </div>
                        <span :title="p.name">{{ shortName(p.name) }}</span>
                      </div>
                    </td>
                    <td><span class="badge bg-warning-subtle text-warning">{{ p.quantity }}</span></td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="2" class="text-center py-3 opacity-75">Không có sản phẩm dưới 100</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 g-lg-4 mt-1">
      <div class="col-12">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <div class="eyebrow">Hiệu suất</div>
                <div class="fw-semibold">Top sản phẩm bán</div>
              </div>
              <span class="badge bg-secondary-subtle text-secondary">
                {{ summary.products.top_selling_7d.length }} mục
              </span>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr class="small opacity-75">
                    <th>Sản phẩm</th>
                    <th style="width: 100px">SL bán</th>
                    <th style="width: 140px">Doanh thu</th>
                  </tr>
                </thead>
                <tbody v-if="summary.products.top_selling_7d.length">
                  <tr v-for="p in summary.products.top_selling_7d" :key="p.product_id">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="thumb thumb-sm">
                          <img v-if="p.image_url" :src="p.image_url" alt="thumb" />
                          <div v-else class="thumb placeholder"><i class="fa-regular fa-image"></i></div>
                        </div>
                        <span :title="p.name">{{ shortName(p.name) }}</span>
                      </div>
                    </td>
                    <td>{{ p.total_qty }}</td>
                    <td>{{ money(p.revenue) }}</td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="3" class="text-center py-3 opacity-75">Chưa có dữ liệu</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 g-lg-4 mt-1">
      <div class="col-12">
        <div class="card card-soft h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <div class="eyebrow">Khách hàng</div>
                <div class="fw-semibold">Top chi tiêu 30 ngày</div>
              </div>
              <span class="badge bg-secondary-subtle text-secondary">
                {{ summary.customers.top_spenders.length }} khách
              </span>
            </div>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr class="small opacity-75">
                    <th>Khách hàng</th>
                    <th style="width: 80px">Đơn</th>
                    <th style="width: 140px">Chi tiêu</th>
                  </tr>
                </thead>
                <tbody v-if="summary.customers.top_spenders.length">
                  <tr v-for="c in summary.customers.top_spenders" :key="c.user_id">
                    <td>{{ c.name }}</td>
                    <td>{{ c.orders }}</td>
                    <td>{{ money(c.spending) }}</td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="3" class="text-center py-3 opacity-75">Chưa có dữ liệu</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import dayjs from "dayjs";
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import DashboardDonutChart from "../components/dashboard/DashboardDonutChart.vue";
import DashboardLineChart from "../components/dashboard/DashboardLineChart.vue";
import StatCard from "../components/dashboard/StatCard.vue";
import DashboardService from "../services/dashboard.service";

const loading = ref(false);
const lastUpdated = ref(null);
const summary = ref({
  meta: { date: null, range: { from: null, to: null } },
  kpis: {
    revenue_today: 0,
    orders_today: 0,
    orders_completed_today: 0,
    avg_order_value_today: 0,
    products_sold_today: 0,
    receipt_value_today: 0,
  },
  counters: {
    total_products: 0,
    total_users: 0,
    new_customers_last_7d: 0,
    low_stock_count: 0,
  },
  metrics: {
    completion_rate: 0,
    cancel_reject_rate: 0,
    avg_order_value_30d: 0,
    inventory_turnover_30d: 0,
  },
  orders: {
    by_status: {},
    trend_30d: [],
  },
  revenue: {
    last_7_days: 0,
    last_30_days: 0,
    by_day: [],
  },
  purchases: {
    last_7_days_value: 0,
    last_30_days_value: 0,
    last_30_days_qty: 0,
    by_day: [],
  },
  profit: {
    last_30_days: 0,
    by_day: [],
  },
  products: {
    top_selling_7d: [],
    low_stock: [],
    category_share: [],
  },
  customers: {
    top_spenders: [],
  },
  evaluations: {
    counts: {},
    percents: {},
    average: 0,
    total: 0,
  },
  payments: {
    by_method: [],
    total_amount: 0,
  },
});

const quickLinks = [
  { title: "Đơn hàng", desc: "Tạo và xử lý trạng thái đơn", icon: "fa-solid fa-receipt", to: "/orders" },
  { title: "Sản phẩm", desc: "Thêm / chỉnh sửa sản phẩm", icon: "fa-solid fa-box", to: "/products" },
  { title: "Nhập kho", desc: "Theo dõi phiếu nhập gần nhất", icon: "fa-solid fa-file-invoice-dollar", to: "/receipts" },
  { title: "Khuyến mãi", desc: "Thiết lập giảm giá nhanh", icon: "fa-solid fa-tags", to: "/discounts" },
];

const statusConfig = [
  { key: "pending", label: "Đang duyệt" },
  { key: "shipping", label: "Đang giao" },
  { key: "completed", label: "Hoàn tất" },
  { key: "cancelled", label: "Đã hủy" },
  { key: "rejected", label: "Từ chối" },
];

const filteredLowStock = computed(() =>
  summary.value.products.low_stock || []
);
const lowStockCount = computed(() => filteredLowStock.value.length);

const spotlightCards = computed(() => [
  { label: "Doanh thu hôm nay", value: money(summary.value.kpis.revenue_today), icon: "fa-solid fa-sack-dollar" },
  { label: "Đơn hôm nay", value: summary.value.kpis.orders_today, icon: "fa-solid fa-receipt" },
  { label: "Hàng sắp hết", value: lowStockCount.value, icon: "fa-solid fa-battery-quarter" },
  { label: "Khách mới 7 ngày", value: summary.value.counters.new_customers_last_7d, icon: "fa-solid fa-user-plus" },
]);

const detailCards = computed(() => [
  { label: "Doanh thu 7 ngày", value: money(summary.value.revenue.last_7_days), icon: "fa-solid fa-calendar-week" },
  { label: "Doanh thu 30 ngày", value: money(summary.value.revenue.last_30_days), icon: "fa-solid fa-calendar-days" },
  { label: "Lợi nhuận 30 ngày", value: money(summary.value.profit.last_30_days), icon: "fa-solid fa-chart-line" },
  { label: "Chi phí nhập 30 ngày", value: money(summary.value.purchases.last_30_days_value), icon: "fa-solid fa-file-invoice-dollar" },
  { label: "Sản phẩm nhập 30 ngày", value: summary.value.purchases.last_30_days_qty, icon: "fa-solid fa-arrow-down-short-wide" },
  { label: "Sản phẩm bán hôm nay", value: summary.value.kpis.products_sold_today, icon: "fa-solid fa-box-open" },
  { label: "Tổng người dùng", value: summary.value.counters.total_users, icon: "fa-solid fa-users" },
  { label: "Tổng sản phẩm", value: summary.value.counters.total_products, icon: "fa-solid fa-boxes-stacked" },
  { label: "Tỷ lệ hoàn tất", value: summary.value.metrics.completion_rate + " %", icon: "fa-solid fa-circle-check" },
  { label: "Tỷ lệ hủy/từ chối", value: summary.value.metrics.cancel_reject_rate + " %", icon: "fa-solid fa-ban" },
  { label: "Giá trị đơn TB 30 ngày", value: money(summary.value.metrics.avg_order_value_30d), icon: "fa-solid fa-scale-balanced" },
  { label: "Vòng quay tồn kho", value: summary.value.metrics.inventory_turnover_30d, icon: "fa-solid fa-rotate" },
  { label: "Đánh giá TB", value: (summary.value.evaluations.average ?? 0).toFixed(2), icon: "fa-solid fa-star" },
  { label: "Tổng đánh giá", value: summary.value.evaluations.total, icon: "fa-solid fa-clipboard-list" },
]);

const financeLabels = computed(() =>
  (summary.value.profit.by_day || []).map((item) => dayjs(item.date).format("DD/MM"))
);
const financeDatasets = computed(() => [
  {
    label: "Doanh thu",
    data: (summary.value.profit.by_day || []).map((i) => i.revenue || 0),
    color: "#3b82f6",
    yLabel: "money",
  },
  {
    label: "Chi phí nhập",
    data: (summary.value.profit.by_day || []).map((i) => i.cost || 0),
    color: "#f59e0b",
    borderDash: [6, 4],
    yLabel: "money",
  },
  {
    label: "Lợi nhuận",
    data: (summary.value.profit.by_day || []).map((i) => i.profit || 0),
    color: "#10b981",
    yLabel: "money",
    tension: 0.25,
  },
]);

const purchaseLabels = computed(() =>
  (summary.value.purchases.by_day || []).map((item) => dayjs(item.date).format("DD/MM"))
);
const purchaseDatasets = computed(() => [
  {
    label: "Chi phí nhập",
    data: (summary.value.purchases.by_day || []).map((i) => i.cost || 0),
    color: "#f97316",
    yLabel: "money",
  },
  {
    label: "Số lượng nhập",
    data: (summary.value.purchases.by_day || []).map((i) => i.quantity || 0),
    color: "#8b5cf6",
    type: "bar",
    fill: false,
    yAxisID: "y1",
    borderWidth: 0,
  },
]);

const statusLabels = computed(() => statusConfig.map((s) => s.label));
const statusValues = computed(() =>
  statusConfig.map((s) => summary.value.orders.by_status?.[s.key] || 0)
);
const totalOrdersRange = computed(() => statusValues.value.reduce((a, b) => a + b, 0));

const categoryLabels = computed(() =>
  (summary.value.products.category_share || []).map((c) => c.category)
);
const categoryValues = computed(() =>
  (summary.value.products.category_share || []).map((c) => c.revenue)
);

const ratingBars = computed(() => {
  const counts = summary.value.evaluations.counts || {};
  const percents = summary.value.evaluations.percents || {};
  return [5, 4, 3, 2, 1].map((star) => ({
    star,
    count: Number(counts[star] ?? 0),
    percent: Number(percents[star] ?? 0),
  }));
});

const paymentRows = computed(() => summary.value.payments.by_method || []);

const dateRangeLabel = computed(() => {
  const { from, to } = summary.value.meta.range || {};
  if (!from || !to) return "—";
  return `${dayjs(from).format("DD/MM")} - ${dayjs(to).format("DD/MM")}`;
});

function money(v) {
  return Number(v || 0).toLocaleString("vi-VN") + " ₫";
}

function formatTime(date) {
  return dayjs(date).format("HH:mm DD/MM/YYYY");
}

function shortName(name) {
  if (!name) return "—";
  return name.length > 42 ? name.slice(0, 39) + "..." : name;
}

async function load() {
  loading.value = true;
  try {
  const res = await DashboardService.summary();
  summary.value = { ...summary.value, ...(res?.data ?? res ?? {}) };
  lastUpdated.value = new Date();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.dashboard-page {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
  box-shadow: 0 12px 30px -18px rgba(0, 0, 0, 0.25);
}

.hero-card {
  border: none;
  background: color-mix(in srgb, var(--main-color) 35%, var(--main-bg));
}

.hero-body {
  display: flex;
  gap: 2rem;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.hero-left {
  max-width: 560px;
}

.hero-title {
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 0.35rem;
}

.hero-sub {
  margin: 0;
  max-width: 520px;
}

.hero-right {
  display: grid;
  gap: 0.75rem;
  min-width: 260px;
}

.kpi-pill {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 0.75rem 1rem;
  color: var(--font-color);
}

.kpi-pill.subtle {
  background: color-mix(in srgb, var(--main-extra-bg) 70%, transparent);
  border-style: dashed;
}

.kpi-value {
  font-size: 1.4rem;
  font-weight: 700;
}

.btn-main {
  background: var(--extra-color);
  color: #fff;
  border-radius: 0.9rem;
  border: 1px solid var(--extra-color);
}

.btn-main:hover {
  color: #fff;
  background: color-mix(in srgb, var(--extra-color) 88%, #000);
}

.btn-outline-dark {
  border-color: var(--extra-color);
  color: var(--extra-color);
}

.btn-outline-dark:hover,
.btn-outline-dark:focus {
  background: color-mix(in srgb, var(--extra-color) 20%, var(--main-bg));
  border-color: var(--extra-color);
  color: var(--font-color);
}

.btn-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  color: var(--font-color);
  border-radius: 0.9rem;
}

.thumb.thumb-sm {
  width: 7rem;
  height: 7rem;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--hover-border-color);
  background: var(--hover-background-color);
  display: flex;
  align-items: center;
  justify-content: center;
}
.thumb.thumb-sm img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumb.placeholder {
  color: var(--font-color);
}

.rating-progress {
  height: 8px;
  background: var(--hover-background-color);
  border-radius: 999px;
}
.rating-progress-bar {
  background: #f59e0b;
  border-radius: 999px;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.eyebrow {
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-size: 0.78rem;
  color: var(--font-extra-color);
}

.quick-link {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 0.9rem 1rem;
  color: var(--font-color);
  transition: transform 120ms ease, box-shadow 120ms ease;
}

.quick-link:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px -18px rgba(0, 0, 0, 0.35);
}

@media (max-width: 991px) {
  .hero-title {
    font-size: 1.6rem;
  }
}
</style>
