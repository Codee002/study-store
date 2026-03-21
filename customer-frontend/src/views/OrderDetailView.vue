<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="user" />

    <main class="container py-4">
      <section class="order-detail-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h1 class="detail-title mb-1">Chi tiết đơn #{{ orderId }}</h1>
            <div class="text-muted small">{{ formatDateTime(order?.created_at) }}</div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button
              v-if="order?.status === 'completed'"
              class="btn btn-main"
              type="button"
              @click="printInvoice"
            >
              <i class="fa-solid fa-file-pdf me-2"></i>In hóa đơn (PDF)
            </button>
            <RouterLink to="/orders" class="btn btn-outline-secondary">
              <i class="fa-solid fa-arrow-left me-2"></i>Quay lại danh sách
            </RouterLink>
          </div>
        </div>

        <div v-if="loading" class="empty-box text-center">
          <i class="fa-solid fa-spinner fa-spin empty-icon"></i>
          <p class="mb-0 text-muted">Đang tải chi tiết đơn hàng...</p>
        </div>

        <div v-else-if="!order" class="empty-box text-center">
          <i class="fa-solid fa-box-open empty-icon"></i>
          <p class="mb-0 text-muted">Không tìm thấy đơn hàng.</p>
        </div>

        <template v-else>
          <article class="panel mb-3">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
              <h5 class="mb-0">Trạng thái đơn hàng</h5>
              <span class="status-badge" :class="`status-${order.status}`">
                {{ statusLabel(order.status) }}
              </span>
            </div>
          </article>

          <div class="row g-3">
            <div class="col-12 col-xl-8">
              <article class="panel mb-3">
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="small text-muted">Khách hàng</div>
                    <div class="fw-semibold">{{ user?.name || "-" }}</div>
                    <div class="small text-muted">{{ user?.email || "-" }}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="small text-muted">Giao hàng</div>
                    <div class="fw-semibold">{{ order.delivery_info?.name || "-" }}</div>
                    <div class="small text-muted">{{ order.delivery_info?.phone || "-" }}</div>
                    <div class="small text-muted">{{ order.delivery_info?.address || "-" }}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="small text-muted">Thanh toán / Trạng thái</div>
                    <div class="fw-semibold">{{ order.payment?.name || "-" }}</div>
                    <span class="status-badge mt-1" :class="`status-${order.status}`">
                      {{ statusLabel(order.status) }}
                    </span>
                  </div>
                </div>
              </article>

              <article class="panel mb-3">
                <h5 class="mb-3">Địa chỉ giao hàng</h5>
                <div v-if="order.delivery_info" class="selected-address">
                  <div class="fw-semibold">{{ order.delivery_info.name }} - {{ order.delivery_info.phone }}</div>
                  <div class="text-muted small mt-1">{{ order.delivery_info.address }}</div>
                </div>
                <p v-else class="text-muted mb-0">Không có thông tin địa chỉ.</p>
              </article>

              <article class="panel mb-3">
                <h5 class="mb-3">Sản phẩm trong đơn hàng</h5>
                <div v-for="item in order.items" :key="item.id" class="checkout-item">
                  <img :src="item.image || fallbackImage" :alt="item.name" class="item-image" />
                  <div class="item-info">
                    <h6 class="item-name mb-1">{{ item.name }}</h6>
                    <div class="small text-muted">Phân loại: {{ item.color_name }}</div>
                    <div class="small text-muted">Số lượng: {{ item.quantity }}</div>
                  </div>
                  <div class="text-end item-price">
                    <div class="small text-muted">Đơn giá</div>
                    <div class="fw-semibold">{{ formatVnd(item.unit_price) }}</div>
                    <div class="small text-muted mt-1">Thành tiền</div>
                    <div class="line-total">{{ formatVnd(item.line_total) }}</div>
                  </div>
                </div>
              </article>

              <article v-if="order.status === 'completed'" class="panel" id="order-review-section">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                  <h5 class="mb-0">Đánh giá sản phẩm</h5>
                  <span class="small text-muted">
                    {{ reviewedProducts.length }}/{{ order.review_summary?.total_products || 0 }} sản phẩm đã đánh giá
                  </span>
                </div>

                <p v-if="!reviewDrafts.length" class="small text-muted mb-0">Không có sản phẩm để đánh giá.</p>

                <template v-else>
                  <div class="review-list-box">
                    <div v-for="draft in reviewDrafts" :key="`review-${draft.product_id}`" class="review-product-item">
                      <div class="review-product-head">
                        <div class="d-flex align-items-center gap-2">
                          <img :src="draft.image || fallbackImage" :alt="draft.name" class="review-product-thumb" />
                          <div>
                            <div class="fw-semibold">{{ draft.name }}</div>
                            <div class="small text-muted">
                              Số lượng: {{ draft.total_quantity }}
                              <span v-if="draft.variants?.length"> | Phân loại: {{ draft.variants.join(", ") }}</span>
                            </div>
                          </div>
                        </div>
                        <span
                          class="badge"
                          :class="draft.is_evaluated ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary'"
                        >
                          {{ draft.is_evaluated ? "Đã đánh giá" : "Chưa đánh giá" }}
                        </span>
                      </div>

                      <div class="mt-2">
                        <div class="small fw-semibold mb-2">Số sao</div>
                        <div class="d-flex gap-1 text-warning fs-5">
                          <button
                            v-for="n in 5"
                            :key="`star-${draft.product_id}-${n}`"
                            class="btn btn-link p-0 star-btn"
                            type="button"
                            @click="draft.rating = n"
                          >
                            <i class="fa-star" :class="n <= Number(draft.rating || 0) ? 'fa-solid' : 'fa-regular'"></i>
                          </button>
                        </div>
                      </div>

                      <div class="mt-2">
                        <label class="form-label small fw-semibold">Mô tả</label>
                        <textarea
                          v-model.trim="draft.content"
                          rows="3"
                          class="form-control"
                          placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..."
                        ></textarea>
                      </div>

                      <div v-if="draft.existingMedias?.length" class="mt-2">
                        <label class="form-label small fw-semibold">Media đã tải</label>
                        <div class="review-media-grid">
                          <template v-for="m in draft.existingMedias" :key="`existing-review-media-${draft.product_id}-${m.id}`">
                            <div class="review-media-wrapper">
                              <button class="remove-media-btn" type="button" @click="removeExistingMedia(draft, m)">
                                <i class="fa-solid fa-xmark"></i>
                              </button>
                              <img
                                v-if="normalizeMediaType(m.type) === 'image'"
                                :src="m.url"
                                alt="review-media"
                                class="review-media-item"
                              />
                              <video
                                v-else
                                class="review-media-item"
                                :src="m.url"
                                controls
                                preload="metadata"
                              ></video>
                            </div>
                          </template>
                        </div>
                      </div>

                      <div v-if="draft.deletedMedias?.length" class="mt-2">
                        <label class="form-label small fw-semibold text-danger">Media sẽ xóa</label>
                        <div class="review-media-grid">
                          <div
                            v-for="m in draft.deletedMedias"
                            :key="`deleted-review-media-${draft.product_id}-${m.id}`"
                            class="review-media-wrapper"
                          >
                            <button class="restore-media-btn" type="button" @click="restoreDeletedMedia(draft, m)">Hoàn tác</button>
                            <img
                              v-if="normalizeMediaType(m.type) === 'image'"
                              :src="m.url"
                              alt="review-media-to-delete"
                              class="review-media-item"
                            />
                            <video
                              v-else
                              class="review-media-item"
                              :src="m.url"
                              controls
                              preload="metadata"
                            ></video>
                          </div>
                        </div>
                      </div>

                      <div class="mt-2">
                        <label class="form-label small fw-semibold">Ảnh / Video mới</label>
                        <input
                          class="form-control"
                          type="file"
                          multiple
                          accept="image/*,video/*"
                          @change="onReviewFilesChange($event, draft)"
                        />
                        <div v-if="draft.mediaPreviews?.length" class="review-media-grid mt-2">
                          <div
                            v-for="(preview, idx) in draft.mediaPreviews"
                            :key="`pending-media-${draft.product_id}-${idx}`"
                            class="review-media-wrapper"
                          >
                            <button class="remove-media-btn" type="button" @click="removePendingMedia(draft, idx)">
                              <i class="fa-solid fa-xmark"></i>
                            </button>
                            <img
                              v-if="preview.type === 'image'"
                              :src="preview.url"
                              alt="preview"
                              class="review-media-item"
                            />
                            <video v-else class="review-media-item" :src="preview.url" controls preload="metadata"></video>
                          </div>
                        </div>
                        <div v-else class="small text-muted mt-1">Chưa chọn tệp mới</div>
                      </div>
                      <div v-if="draft.is_evaluated" class="small text-muted mt-2">
                        Có thể chỉnh sửa đánh giá và bấm lưu để cập nhật.
                      </div>
                    </div>
                  </div>

                  <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-main" type="button" :disabled="submittingReview" @click="submitOrderReview">
                      {{ submittingReview ? "Đang lưu đánh giá..." : "Lưu / cập nhật đánh giá" }}
                    </button>
                  </div>
                </template>
              </article>

              <article class="panel mb-3">
                <h5 class="mb-3">Khuyến mãi đã áp dụng</h5>
                <div v-if="order.discounts?.length" class="discount-list">
                  <div
                    v-for="discount in order.discounts"
                    :key="discount.order_discount_id || discount.id"
                    class="discount-item applied"
                  >
                    <div class="flex-grow-1">
                      <div class="fw-semibold small">{{ discountLabel(discount) }}</div>
                      <div class="small text-muted">Danh mục: {{ discount.category_name || "-" }}</div>
                      <div class="small text-danger">-{{ formatVnd(discount.price) }}</div>
                    </div>
                  </div>
                </div>
                <p v-else class="text-muted small mb-0">Đơn hàng không áp dụng khuyến mãi.</p>
              </article>
            </div>

            <div class="col-12 col-xl-4">
              <aside class="panel mb-3">
                <h5 class="mb-3">Số tiền đơn hàng</h5>

                <div class="summary-row">
                  <span>Tiền sản phẩm</span>
                  <strong>{{ formatVnd(order.product_subtotal) }}</strong>
                </div>
                <div class="summary-row">
                  <span>Tiền khuyến mãi</span>
                  <strong class="text-danger">- {{ formatVnd(order.discount_price) }}</strong>
                </div>
                <div class="summary-row">
                  <span>Tiền vận chuyển</span>
                  <strong>{{ formatVnd(order.shipping_fee) }}</strong>
                </div>
                <div class="summary-row">
                  <span>Thanh toán</span>
                  <strong>{{ order.payment?.name || "-" }}</strong>
                </div>
                <div class="summary-row border-0 pt-2">
                  <span>Tổng tiền</span>
                  <strong class="price-total">{{ formatVnd(order.total_price) }}</strong>
                </div>

                <div class="d-grid gap-2 mt-3">
                  <button
                    v-if="canCancel"
                    class="btn btn-outline-danger"
                    type="button"
                    :disabled="cancelling"
                    @click="cancelOrder"
                  >
                    {{ cancelling ? "Đang hủy..." : "Hủy đơn hàng" }}
                  </button>
                  <button
                    v-if="canComplete"
                    class="btn btn-main"
                    type="button"
                    :disabled="confirming"
                    @click="confirmReceivedOrder"
                  >
                    {{ confirming ? "Đang xác nhận..." : "Đã nhận hàng" }}
                  </button>
                </div>
              </aside>
            </div>
          </div>
        </template>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import Swal from "sweetalert2";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import authService from "@/services/auth.service";
import cartService from "@/services/cart.service";
import orderService from "@/services/order.service";

const route = useRoute();
const orderId = computed(() => Number(route.params?.id || 0));
const fallbackImage = "https://via.placeholder.com/96x96?text=No+Image";

const cartCount = ref(0);
const loading = ref(false);
const order = ref(null);
const cancelling = ref(false);
const confirming = ref(false);
const submittingReview = ref(false);
const reviewDrafts = ref([]);

const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const canCancel = computed(() => String(order.value?.status || "") === "pending");
const canComplete = computed(() => String(order.value?.status || "") === "shipping");
const reviewedProducts = computed(() =>
  (reviewDrafts.value || []).filter((item) => Number(item?.rating || 0) > 0)
);

function formatDateTime(v) {
  if (!v) return "";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString("vi-VN");
}

function formatVnd(n) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(Number(n || 0));
}

function statusLabel(status) {
  const map = {
    pending: "Đang duyệt",
    shipping: "Đang giao",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    rejected: "Từ chối",
  };
  return map[String(status)] || "Không xác định";
}

function normalizeMediaType(typeValue) {
  return String(typeValue || "").toLowerCase().startsWith("video") ? "video" : "image";
}

function discountLabel(discount) {
  return `${discount?.des || "Khuyến mãi"} - ${Number(discount?.percent || 0)}%`;
}

function initReviewDrafts() {
  const rows = Array.isArray(order.value?.reviewable_products) ? order.value.reviewable_products : [];
  reviewDrafts.value = rows.map((row) => ({
    product_id: Number(row?.product_id || 0),
    name: String(row?.name || ""),
    image: String(row?.image || ""),
    total_quantity: Number(row?.total_quantity || 0),
    variants: Array.isArray(row?.variants) ? row.variants.map((v) => String(v || "")) : [],
    is_evaluated: Boolean(row?.is_evaluated),
    can_review: Boolean(row?.can_review),
    rating: Number(row?.evaluate?.rating || 0),
    content: row?.evaluate?.content ? String(row.evaluate.content) : "",
    existingMedias: Array.isArray(row?.evaluate?.medias) ? row.evaluate.medias : [],
    deletedMediaIds: [],
    deletedMedias: [],
    mediaFiles: [],
    mediaPreviews: [],
  }));
}

function onReviewFilesChange(event, draft) {
  revokePreviewUrls(draft.mediaPreviews);
  const files = Array.from(event?.target?.files || []);
  draft.mediaFiles = files;
  draft.mediaPreviews = files.map((file) => ({
    url: URL.createObjectURL(file),
    type: normalizeMediaType(file.type),
  }));
}

function removePendingMedia(draft, index) {
  const previews = Array.isArray(draft.mediaPreviews) ? [...draft.mediaPreviews] : [];
  const files = Array.isArray(draft.mediaFiles) ? [...draft.mediaFiles] : [];
  const preview = previews[index];
  if (preview?.url) URL.revokeObjectURL(preview.url);
  previews.splice(index, 1);
  files.splice(index, 1);
  draft.mediaPreviews = previews;
  draft.mediaFiles = files;
}

function removeExistingMedia(draft, media) {
  const mediaId = Number(media?.id || 0);
  if (!mediaId) return;
  const existingIds = Array.isArray(draft.deletedMediaIds) ? draft.deletedMediaIds : [];
  draft.deletedMediaIds = Array.from(new Set([...existingIds, mediaId]));
  draft.deletedMedias = Array.isArray(draft.deletedMedias) ? [...draft.deletedMedias, media] : [media];
  draft.existingMedias = (draft.existingMedias || []).filter((m) => Number(m?.id || 0) !== mediaId);
}

function restoreDeletedMedia(draft, media) {
  const mediaId = Number(media?.id || 0);
  if (!mediaId) return;
  draft.deletedMediaIds = (draft.deletedMediaIds || []).filter((id) => Number(id) !== mediaId);
  draft.deletedMedias = (draft.deletedMedias || []).filter((m) => Number(m?.id || 0) !== mediaId);
  draft.existingMedias = Array.isArray(draft.existingMedias) ? [...draft.existingMedias, media] : [media];
}

function revokePreviewUrls(previews = []) {
  previews.forEach((p) => {
    if (p?.url) URL.revokeObjectURL(p.url);
  });
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

async function loadCartCount() {
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }
}

async function loadOrder() {
  if (!orderId.value) return;
  loading.value = true;
  try {
    order.value = await orderService.getMyOrderDetail(orderId.value);
    initReviewDrafts();
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải chi tiết đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
    order.value = null;
    reviewDrafts.value = [];
  } finally {
    loading.value = false;
  }
}

async function cancelOrder() {
  const ask = await Swal.fire({
    title: "Xác nhận hủy đơn?",
    text: "Bạn chỉ có thể hủy đơn khi đang duyệt.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Hủy đơn",
    cancelButtonText: "Giữ lại",
  });
  if (!ask.isConfirmed) return;

  try {
    cancelling.value = true;
    const res = await orderService.cancelMyOrder(orderId.value);
    order.value = res?.order || order.value;
    initReviewDrafts();
    await Swal.fire("Thành công", res?.message || "Hủy đơn thành công.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể hủy đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    cancelling.value = false;
  }
}

async function confirmReceivedOrder() {
  const ask = await Swal.fire({
    title: "Xác nhận đã nhận hàng?",
    text: "Đơn hàng sẽ chuyển sang trạng thái hoàn thành.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Đã nhận",
    cancelButtonText: "Chưa",
  });
  if (!ask.isConfirmed) return;

  try {
    confirming.value = true;
    const res = await orderService.completeMyOrder(orderId.value);
    order.value = res?.order || order.value;
    initReviewDrafts();
    await Swal.fire("Thành công", res?.message || "Xác nhận nhận hàng thành công.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể xác nhận nhận hàng.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    confirming.value = false;
  }
}

async function submitOrderReview() {
  const selectedRows = reviewDrafts.value.filter((r) => Number(r.rating || 0) > 0);
  if (!selectedRows.length) {
    await Swal.fire("Thông báo", "Vui lòng chọn số sao cho ít nhất 1 sản phẩm.", "info");
    return;
  }

  const invalidRow = selectedRows.find((r) => Number(r.rating || 0) <= 0);
  if (invalidRow) {
    await Swal.fire("Thiếu số sao", `Vui lòng chọn số sao cho sản phẩm: ${invalidRow.name}`, "warning");
    return;
  }

  try {
    submittingReview.value = true;
    const payload = selectedRows.map((r) => ({
      product_id: Number(r.product_id),
      rating: Number(r.rating || 0),
      content: String(r.content || "").trim(),
      media_files: Array.isArray(r.mediaFiles) ? r.mediaFiles : [],
      delete_media_ids: Array.isArray(r.deletedMediaIds) ? r.deletedMediaIds : [],
    }));

    const res = await orderService.submitMyOrderEvaluate(orderId.value, payload);
    order.value = res?.order || order.value;
    initReviewDrafts();
    await Swal.fire("Thành công", res?.message || "Đánh giá sản phẩm thành công.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể lưu đánh giá sản phẩm.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    submittingReview.value = false;
  }
}

function buildInvoiceHtml() {
  if (!order.value) return "";
  const o = order.value;
  const rows = (o.items || [])
    .map(
      (item, idx) => `
        <tr>
          <td style="padding:6px 8px;border:1px solid #ddd;">${idx + 1}</td>
          <td style="padding:6px 8px;border:1px solid #ddd;">${item.name}</td>
          <td style="padding:6px 8px;border:1px solid #ddd;">${item.color_name || ""}</td>
          <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">${item.quantity}</td>
          <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">${formatVnd(item.unit_price)}</td>
          <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">${formatVnd(item.line_total)}</td>
        </tr>`
    )
    .join("");

  const delivery = o.delivery_info || {};
  const payment = o.payment || {};

  return `
    <html>
      <head>
        <title>Hóa đơn #${orderId.value}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 24px; color: #111; }
          h1 { margin: 0 0 8px; }
          .muted { color: #666; font-size: 13px; }
          table { border-collapse: collapse; width: 100%; margin-top: 12px; }
          .totals td { padding: 6px 8px; }
        </style>
      </head>
      <body>
        <h1>Hóa đơn bán hàng</h1>
        <div class="muted">Mã đơn: #${orderId.value} | Ngày: ${formatDateTime(o.created_at)}</div>
        <hr />
        <h3>Khách hàng</h3>
        <div>${user.value?.name || "-"}</div>
        <div class="muted">${user.value?.email || "-"}</div>
        <h3 style="margin-top:12px;">Giao hàng</h3>
        <div>${delivery.name || "-"}</div>
        <div>${delivery.phone || "-"}</div>
        <div class="muted">${delivery.address || "-"}</div>
        <h3 style="margin-top:12px;">Thanh toán</h3>
        <div>${payment.name || "-"}</div>

        <h3 style="margin-top:16px;">Chi tiết đơn hàng</h3>
        <table>
          <thead>
            <tr style="background:#f4f4f4;">
              <th style="padding:6px 8px;border:1px solid #ddd;text-align:left;">#</th>
              <th style="padding:6px 8px;border:1px solid #ddd;text-align:left;">Sản phẩm</th>
              <th style="padding:6px 8px;border:1px solid #ddd;text-align:left;">Phân loại</th>
              <th style="padding:6px 8px;border:1px solid #ddd;text-align:right;">SL</th>
              <th style="padding:6px 8px;border:1px solid #ddd;text-align:right;">Đơn giá</th>
              <th style="padding:6px 8px;border:1px solid #ddd;text-align:right;">Thành tiền</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>

        <table class="totals" style="width:100%; margin-top:12px;">
          <tr>
            <td style="text-align:right;">Tiền sản phẩm:</td>
            <td style="width:160px;text-align:right;">${formatVnd(o.product_subtotal)}</td>
          </tr>
          <tr>
            <td style="text-align:right;">Tiền khuyến mãi:</td>
            <td style="text-align:right;">- ${formatVnd(o.discount_price)}</td>
          </tr>
          <tr>
            <td style="text-align:right;">Tiền vận chuyển:</td>
            <td style="text-align:right;">${formatVnd(o.shipping_fee)}</td>
          </tr>
          <tr style="font-weight:700;font-size:16px;">
            <td style="text-align:right;">Tổng tiền:</td>
            <td style="text-align:right;">${formatVnd(o.total_price)}</td>
          </tr>
        </table>

        <p class="muted" style="margin-top:20px;">Hóa đơn không kèm hình ảnh hay đánh giá sản phẩm.</p>
      </body>
    </html>
  `;
}

function printInvoice() {
  if (!order.value) return;
  const html = buildInvoiceHtml();
  const w = window.open("", "_blank");
  if (!w) {
    Swal.fire("Không thể mở cửa sổ in", "Vui lòng cho phép popup/print và thử lại.", "warning");
    return;
  }
  w.document.open();
  w.document.write(html);
  w.document.close();
  w.focus();
  setTimeout(() => {
    try {
      w.print();
    } catch {
      // ignore
    }
  }, 100);
}

onMounted(async () => {
  await fetchMe();
  await Promise.all([loadCartCount(), loadOrder()]);
});

onBeforeUnmount(() => {
  reviewDrafts.value.forEach((draft) => revokePreviewUrls(draft.mediaPreviews || []));
});
</script>

<style scoped>
.order-detail-shell {
  min-height: 60vh;
}

.detail-title {
  font-size: 1.55rem;
  font-weight: 800;
}

.panel,
.empty-box {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 16px;
}

.empty-box {
  padding: 40px 16px;
}

.empty-icon {
  font-size: 2rem;
  color: var(--font-extra-color);
  margin-bottom: 12px;
}

.selected-address {
  border: 1px dashed var(--border-color);
  border-radius: 12px;
  padding: 10px 12px;
}

.checkout-item {
  display: grid;
  grid-template-columns: 96px 1fr auto;
  gap: 12px;
  align-items: center;
  padding: 10px 0;
  border-top: 1px dashed var(--border-color);
}

.checkout-item:first-of-type {
  border-top: none;
  padding-top: 0;
}

.item-image {
  width: 96px;
  height: 96px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.item-name {
  font-weight: 700;
}

.item-price {
  min-width: 140px;
}

.line-total {
  color: #d32f2f;
  font-size: 1.05rem;
  font-weight: 800;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px dashed var(--border-color);
}

.price-total {
  color: #d32f2f;
  font-size: 1.2rem;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
}

.status-pending {
  background: rgba(255, 193, 7, 0.18);
  color: #916400;
}

.status-shipping {
  background: rgba(13, 110, 253, 0.15);
  color: #0a58ca;
}

.status-completed {
  background: rgba(25, 135, 84, 0.15);
  color: #0f5132;
}

.status-cancelled,
.status-rejected {
  background: rgba(220, 53, 69, 0.14);
  color: #842029;
}

.review-list-box {
  display: grid;
  gap: 12px;
}

.review-product-item {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 12px;
}

.review-product-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.review-product-thumb {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid var(--border-color);
}

.discount-list {
  display: grid;
  gap: 10px;
}

.discount-item {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 10px 12px;
}

.review-media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 8px;
}

.review-media-item {
  width: 100%;
  height: 110px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: #000;
}

.review-media-wrapper {
  position: relative;
}

.restore-media-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  padding: 4px 10px;
  border: none;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.65);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.remove-media-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.65);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.star-btn {
  color: inherit;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}

@media (max-width: 767px) {
  .checkout-item {
    grid-template-columns: 72px 1fr;
  }

  .item-image {
    width: 72px;
    height: 72px;
  }

  .item-price {
    grid-column: 2 / 3;
    text-align: left !important;
  }
}
</style>
