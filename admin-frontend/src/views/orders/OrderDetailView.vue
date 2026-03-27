<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Chi tiết đơn hàng #{{ id }}</h4>
          <div class="small opacity-75">{{ formatDateTime(order?.created_at) }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button
            v-if="order?.status === 'completed'"
            class="btn btn-main"
            type="button"
            @click="printInvoice"
          >
            <i class="fa-solid fa-file-pdf me-1"></i> In hóa đơn (PDF)
          </button>
          <RouterLink class="btn btn-outline-secondary" :to="{ name: 'orders.list' }">
            <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-5 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>Đang tải dữ liệu...
          </div>

          <template v-else-if="order">
            <div class="row g-3">
              <div class="col-12 col-xl-8">
                <div class="panel mb-3">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <div class="small opacity-75">Khách hàng</div>
                      <div class="fw-semibold">{{ order.customer?.name || "-" }}</div>
                      <div class="small">{{ order.customer?.email || "-" }}</div>
                    </div>
                    <div class="col-md-4">
                      <div class="small opacity-75">Giao hàng</div>
                      <div class="fw-semibold">{{ order.delivery_info?.name || "-" }}</div>
                      <div class="small">{{ order.delivery_info?.phone || "-" }}</div>
                      <div class="small opacity-75">{{ order.delivery_info?.address || "-" }}</div>
                    </div>
                    <div class="col-md-4">
                      <div class="small opacity-75">Thanh toán / Trạng thái</div>
                      <div class="fw-semibold">{{ order.payment?.name || "-" }}</div>
                      <span class="badge mt-1" :class="statusClass(order.status)">
                        {{ statusLabel(order.status) }}
                      </span>
                      <div v-if="order.status === 'pending' && order.can_reject === false" class="small text-danger mt-2">
                        Đơn thanh toán VNPay không được từ chối.
                      </div>
                    </div>
                  </div>
                </div>

                <div class="panel">
                  <h6 class="mb-3">Sản phẩm trong đơn</h6>

                  <div v-for="item in order.items" :key="item.id" class="order-item">
                    <div class="d-flex gap-3 align-items-start">
                      <div class="thumb">
                        <img v-if="item.image" :src="item.image" alt="thumb" />
                        <div v-else class="thumb-placeholder">
                          <i class="fa-regular fa-image"></i>
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <div class="fw-semibold">{{ item.name }}</div>
                        <div class="small opacity-75">Phân loại: {{ item.color_name || "Mặc định" }}</div>
                        <div class="small opacity-75">
                          Số lượng: {{ item.quantity }} | Đơn giá: {{ formatMoney(item.unit_price) }}
                        </div>
                        <div class="small text-danger fw-semibold">
                          Thành tiền: {{ formatMoney(item.line_total) }}
                        </div>
                      </div>
                    </div>

                    <div v-if="order.status === 'pending'" class="mt-3 alloc-box">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold">Phân bổ kho</div>
                        <div class="small" :class="allocationMatch(item) ? 'text-success' : 'text-danger'">
                          Đã nhập {{ allocatedTotal(item) }} / {{ item.quantity }}
                        </div>
                      </div>

                      <div v-if="!item.warehouse_options?.length" class="small text-danger">
                        Không có tồn kho phù hợp cho sản phẩm này.
                      </div>

                      <div v-else class="table-responsive">
                        <table class="table align-middle mb-0">
                          <thead>
                            <tr class="small opacity-75">
                              <th>Kho</th>
                              <th class="text-end" style="width: 140px">Tồn khả dụng</th>
                              <th class="text-end" style="width: 160px">Số lượng lấy</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="row in item.warehouse_options" :key="row.warehouse_detail_id">
                              <td>
                                <div class="fw-semibold">{{ row.warehouse_name || `Kho #${row.warehouse_id}` }}</div>
                                <div class="small opacity-75">{{ row.warehouse_address || "-" }}</div>
                              </td>
                              <td class="text-end">{{ row.available_quantity }}</td>
                              <td class="text-end">
                                <input
                                  class="form-control form-control-sm text-end alloc-input"
                                  type="number"
                                  min="0"
                                  :max="row.available_quantity"
                                  :value="getAlloc(item.id, row.warehouse_detail_id)"
                                  @input="onAllocInput(item.id, row.warehouse_detail_id, $event.target.value)"
                                />
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="order.status === 'completed'" class="panel mt-3">
                  <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                    <h6 class="mb-0">Đánh giá của khách hàng</h6>
                    <span class="small opacity-75">
                      {{ reviewedProducts.length }}/{{ order.review_summary?.total_products || 0 }} sản phẩm đã đánh giá
                    </span>
                  </div>

                  <div v-if="!(order.reviewable_products || []).length" class="small opacity-75">
                    Đơn hàng này không có sản phẩm để đánh giá.
                  </div>

                  <div v-else class="review-list-box">
                    <div
                      v-for="review in order.reviewable_products"
                      :key="`review-${review.product_id}`"
                      class="review-product-item"
                    >
                      <div class="review-product-head">
                        <div class="d-flex gap-3 align-items-start">
                          <div class="thumb review-product-thumb">
                            <img v-if="review.image" :src="review.image" alt="review-thumb" />
                            <div v-else class="thumb-placeholder">
                              <i class="fa-regular fa-image"></i>
                            </div>
                          </div>

                          <div>
                            <div class="fw-semibold">{{ review.name }}</div>
                            <div class="small opacity-75">Số lượng: {{ review.total_quantity || 0 }}</div>
                            <div v-if="review.variants?.length" class="small opacity-75">
                              Phân loại: {{ review.variants.join(", ") }}
                            </div>
                          </div>
                        </div>

                        <span
                          class="badge"
                          :class="review.is_evaluated ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary'"
                        >
                          {{ review.is_evaluated ? "Đã đánh giá" : "Chưa đánh giá" }}
                        </span>
                      </div>

                      <template v-if="review.is_evaluated">
                        <div class="small mt-2">
                          <span class="fw-semibold me-2">Số sao:</span>
                          <span class="text-warning">
                            <i
                              v-for="n in 5"
                              :key="`review-star-${review.product_id}-${n}`"
                              class="fa-star"
                              :class="n <= Math.round(Number(review.evaluate?.rating || 0)) ? 'fa-solid' : 'fa-regular'"
                            ></i>
                          </span>
                          <span class="ms-2">{{ Number(review.evaluate?.rating || 0) }}/5</span>
                        </div>
                        <p v-if="review.evaluate?.content" class="small opacity-75 mt-2 mb-2">
                          {{ review.evaluate.content }}
                        </p>
                        <div v-if="review.evaluate?.medias?.length" class="review-media-grid">
                          <template v-for="m in review.evaluate.medias" :key="`admin-review-media-${m.id}`">
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
                          </template>
                        </div>

                        <div class="mt-3">
                          <div v-if="review.evaluate?.reply && openReplyId !== review.evaluate?.id" class="review-reply-box">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                              <div class="small fw-semibold">Phản hồi của admin</div>
                              <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                @click="openReplyForm(review)"
                              >
                                Sửa phản hồi
                              </button>
                            </div>
                            <div class="small opacity-75 mt-1">{{ review.evaluate.reply }}</div>
                          </div>

                          <button
                            v-else-if="openReplyId !== review.evaluate?.id"
                            class="btn btn-sm btn-outline-secondary"
                            type="button"
                            @click="openReplyForm(review)"
                          >
                            Phản hồi
                          </button>

                          <div
                            v-else
                            class="review-reply-box"
                          >
                            <label class="form-label small fw-semibold mb-1">Phản hồi của admin</label>
                            <textarea
                              v-model="replyDrafts[review.evaluate.id]"
                              class="form-control bg-transparent"
                              rows="3"
                              placeholder="Nhập nội dung phản hồi..."
                            ></textarea>
                            <div class="d-flex gap-2 mt-2">
                              <button
                                class="btn btn-sm btn-accent"
                                type="button"
                                :disabled="savingReply[review.evaluate.id]"
                                @click="submitReply(review)"
                              >
                                {{ savingReply[review.evaluate.id] ? "Đang gửi..." : "Gửi phản hồi" }}
                              </button>
                              <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                :disabled="savingReply[review.evaluate.id]"
                                @click="cancelReplyForm()"
                              >
                                Hủy
                              </button>
                            </div>
                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>

                <div class="panel mt-3">
                  <h6 class="mb-3">Khuyến mãi đã áp dụng</h6>
                  <div v-if="order.discounts?.length" class="discount-list">
                    <div
                      v-for="discount in order.discounts"
                      :key="discount.order_discount_id || discount.id"
                      class="discount-item applied"
                    >
                      <div class="flex-grow-1">
                        <div class="fw-semibold small">{{ discountLabel(discount) }}</div>
                        <div class="small opacity-75">
                          Danh mục: {{ discount.category_name || "-" }}
                        </div>
                        <div class="small text-danger">-{{ formatMoney(discount.price) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-else class="small opacity-75">
                    Đơn hàng không áp dụng khuyến mãi.
                  </div>
                </div>

              </div>
              <div class="col-12 col-xl-4">
                <div class="panel sticky-panel">
                  <h6 class="mb-3">Tóm tắt đơn hàng</h6>
                  <div class="summary-row">
                    <span>Tiền sản phẩm</span>
                    <strong>{{ formatMoney(order.product_subtotal) }}</strong>
                  </div>
                  <div class="summary-row">
                    <span>Khuyến mãi</span>
                    <strong class="text-danger">-{{ formatMoney(order.discount_price) }}</strong>
                  </div>
                  <div class="summary-row">
                    <span>Vận chuyển</span>
                    <strong>{{ formatMoney(order.shipping_fee) }}</strong>
                  </div>
                  <div class="summary-row">
                    <span>Tổng thanh toán</span>
                    <strong class="text-danger">{{ formatMoney(order.total_price) }}</strong>
                  </div>

                  <div v-if="order.status === 'pending'" class="d-grid gap-2 mt-3">
                    <button
                      class="btn btn-accent order-action-btn"
                      type="button"
                      :disabled="actionLoading"
                      @click="approveOrder"
                    >
                      {{ actionLoading ? "Đang xử lý..." : "Duyệt đơn" }}
                    </button>

                    <button
                      class="btn btn-outline-danger order-action-btn"
                      type="button"
                      :disabled="actionLoading || order.can_reject === false"
                      @click="rejectOrder"
                    >
                      Từ chối đơn
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import Swal from "sweetalert2";
import OrderService from "@/services/order.service";
import { formatMoney, formatDateTimeVN as formatDateTime } from "@/utils/utils";

const route = useRoute();
const router = useRouter();
const id = computed(() => Number(route.params.id || 0));

const loading = ref(false);
const actionLoading = ref(false);
const order = ref(null);
const allocations = ref({});
const openReplyId = ref(null);
const replyDrafts = ref({});
const savingReply = ref({});
const reviewedProducts = computed(() =>
  (order.value?.reviewable_products || []).filter((item) => Boolean(item?.is_evaluated))
);

function normalizeMediaType(typeValue) {
  return String(typeValue || "").toLowerCase().startsWith("video") ? "video" : "image";
}

function discountLabel(discount) {
  return `${discount?.des || "Khuyen mai"} - ${Number(discount?.percent || 0)}%`;
}

function openReplyForm(review) {
  const evaluateId = Number(review?.evaluate?.id || 0);
  if (!evaluateId) return;
  openReplyId.value = evaluateId;
  replyDrafts.value[evaluateId] = review?.evaluate?.reply || "";
}

function cancelReplyForm() {
  openReplyId.value = null;
}

async function submitReply(review) {
  const evaluateId = Number(review?.evaluate?.id || 0);
  if (!evaluateId) return;

  const reply = String(replyDrafts.value[evaluateId] || "").trim();
  if (!reply) {
    await Swal.fire("Thiếu nội dung", "Vui lòng nhập nội dung phản hồi.", "warning");
    return;
  }

  savingReply.value[evaluateId] = true;
  try {
    const res = await OrderService.replyEvaluate(evaluateId, reply);
    review.evaluate.reply = reply;
    openReplyId.value = null;
    await Swal.fire("Thành công", res?.message || "Đã gửi phản hồi.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể gửi phản hồi.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    savingReply.value[evaluateId] = false;
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
          <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">${formatMoney(item.unit_price)}</td>
          <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">${formatMoney(item.line_total)}</td>
        </tr>`
    )
    .join("");

  const delivery = o.delivery_info || {};
  const payment = o.payment || {};
  const customer = o.customer || {};

  return `
    <html>
      <head>
        <title>Invoice #${id.value}</title>
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
        <div class="muted">Mã đơn: #${id.value} | Ngày: ${formatDateTime(o.created_at)}</div>
        <hr />
        <h3>Khách hàng</h3>
        <div>${customer.name || "-"}</div>
        <div class="muted">${customer.email || "-"}</div>
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
            <td style="width:160px;text-align:right;">${formatMoney(o.product_subtotal)}</td>
          </tr>
          <tr>
            <td style="text-align:right;">Tiền khuyến mãi:</td>
            <td style="text-align:right;">- ${formatMoney(o.discount_price)}</td>
          </tr>
          <tr>
            <td style="text-align:right;">Tiền vận chuyển:</td>
            <td style="text-align:right;">${formatMoney(o.shipping_fee)}</td>
          </tr>
          <tr style="font-weight:700;font-size:16px;">
            <td style="text-align:right;">Tổng tiền:</td>
            <td style="text-align:right;">${formatMoney(o.total_price)}</td>
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


function statusLabel(statusValue) {
  const map = {
    pending: "Đang duyệt",
    shipping: "Đang giao",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    rejected: "Từ chối",
  };
  return map[String(statusValue || "")] || "Không rõ";
}

function statusClass(statusValue) {
  const v = String(statusValue || "");
  if (v === "pending") return "bg-warning-subtle text-warning-emphasis";
  if (v === "shipping") return "bg-info-subtle text-info-emphasis";
  if (v === "completed") return "bg-success-subtle text-success-emphasis";
  if (v === "cancelled" || v === "rejected") return "bg-danger-subtle text-danger-emphasis";
  return "bg-secondary-subtle text-secondary";
}

function resetAllocationsFromOrder() {
  const next = {};
  for (const item of order.value?.items || []) {
    next[item.id] = {};
    for (const row of item.warehouse_options || []) {
      next[item.id][row.warehouse_detail_id] = "";
    }
  }
  allocations.value = next;
}

function getAlloc(itemId, warehouseDetailId) {
  return allocations.value?.[itemId]?.[warehouseDetailId] ?? "";
}

function onAllocInput(itemId, warehouseDetailId, raw) {
  const item = (order.value?.items || []).find((it) => Number(it.id) === Number(itemId));
  const row = item?.warehouse_options?.find(
    (w) => Number(w.warehouse_detail_id) === Number(warehouseDetailId)
  );
  if (!allocations.value[itemId]) allocations.value[itemId] = {};

  if (raw === "" || raw === null || raw === undefined) {
    allocations.value[itemId][warehouseDetailId] = "";
    return;
  }

  const n = Math.max(0, Math.floor(Number(raw) || 0));
  const max = Number(row?.available_quantity || 0);
  allocations.value[itemId][warehouseDetailId] = Math.min(n, max);
}

function allocatedTotal(item) {
  return Object.values(allocations.value?.[item.id] || {}).reduce((sum, v) => {
    const n = Math.floor(Number(v) || 0);
    return sum + (n > 0 ? n : 0);
  }, 0);
}

function allocationMatch(item) {
  return allocatedTotal(item) === Number(item?.quantity || 0);
}

function buildApprovePayload() {
  const payload = [];
  for (const item of order.value?.items || []) {
    const rowMap = allocations.value?.[item.id] || {};
    const sources = [];

    for (const stock of item.warehouse_options || []) {
      const qty = Math.floor(Number(rowMap[stock.warehouse_detail_id]) || 0);
      if (qty <= 0) continue;
      if (qty > Number(stock.available_quantity || 0)) {
        throw new Error(`Số lượng vượt tồn khả dụng ở ${stock.warehouse_name || "kho"}`);
      }
      sources.push({
        warehouse_detail_id: Number(stock.warehouse_detail_id),
        quantity: qty,
      });
    }

    const total = sources.reduce((sum, s) => sum + Number(s.quantity || 0), 0);
    if (total !== Number(item.quantity || 0)) {
      throw new Error(`Phân bổ của "${item.name}" phải đúng ${item.quantity}`);
    }

    payload.push({
      order_detail_id: Number(item.id),
      sources,
    });
  }

  return payload;
}

async function fetchOrder() {
  if (!id.value) return;
  loading.value = true;
  try {
    const res = await OrderService.get(id.value);
    order.value = res?.data || null;
    resetAllocationsFromOrder();
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải chi tiết đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
    router.push({ name: "orders.list" });
  } finally {
    loading.value = false;
  }
}

async function approveOrder() {
  if (!order.value || order.value.status !== "pending") return;

  let payload = [];
  try {
    payload = buildApprovePayload();
  } catch (e) {
    await Swal.fire("Thiếu dữ liệu", e.message || "Phân bổ kho chưa hợp lệ.", "warning");
    return;
  }

  const confirm = await Swal.fire({
    title: "Duyệt đơn hàng?",
    text: "Hệ thống sẽ trừ tồn kho theo phân bổ và chuyển trạng thái sang đang giao.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Duyệt đơn",
    cancelButtonText: "Hủy",
  });
  if (!confirm.isConfirmed) return;

  actionLoading.value = true;
  try {
    const res = await OrderService.approve(id.value, payload);
    order.value = res?.data || order.value;
    resetAllocationsFromOrder();
    await Swal.fire("Thành công", res?.message || "Đã duyệt đơn hàng.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể duyệt đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
    await fetchOrder();
  } finally {
    actionLoading.value = false;
  }
}

async function rejectOrder() {
  if (!order.value || order.value.status !== "pending" || order.value.can_reject === false) return;

  const confirm = await Swal.fire({
    title: "Từ chối đơn hàng?",
    text: "Đơn hàng sẽ chuyển sang trạng thái từ chối.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Từ chối",
    cancelButtonText: "Hủy",
    confirmButtonColor: "#dc3545",
  });
  if (!confirm.isConfirmed) return;

  actionLoading.value = true;
  try {
    const res = await OrderService.reject(id.value);
    order.value = res?.data || order.value;
    await Swal.fire("Thành công", res?.message || "Đã từ chối đơn hàng.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể từ chối đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
    await fetchOrder();
  } finally {
    actionLoading.value = false;
  }
}

onMounted(fetchOrder);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.panel {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border-color);
  border-radius: 0.9rem;
  padding: 1rem;
}

.order-item + .order-item {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px dashed var(--border-color);
}

.thumb {
  width: 84px;
  height: 84px;
  border-radius: 0.6rem;
  overflow: hidden;
  border: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-placeholder {
  opacity: 0.6;
}

.alloc-box {
  border: 1px dashed var(--border-color);
  border-radius: 0.8rem;
  padding: 0.75rem;
}

.alloc-input {
  width: 110px;
  margin-left: auto;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.55rem 0;
  border-bottom: 1px dashed var(--border-color);
}

.summary-row:last-child {
  border-bottom: none;
}

.sticky-panel {
  position: sticky;
  top: 1rem;
}

.btn-accent {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
}

.btn-accent:hover {
  filter: var(--brightness);
}

.order-action-btn {
  font-weight: 700;
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
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.review-product-thumb {
  width: 60px;
  height: 60px;
  border-radius: 10px;
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
  border-radius: 10px;
  border: 1px solid var(--border-color);
  background: #000;
}

.review-reply-box {
  border: 1px dashed var(--border-color);
  border-radius: 10px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.02);
}

.discount-list {
  display: grid;
  gap: 10px;
}

.discount-item {
  display: flex;
  align-items: start;
  gap: 10px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 10px;
}

.discount-item.applied {
  cursor: default;
}
@media (max-width: 1199px) {
  .sticky-panel {
    position: static;
  }
}
</style>
