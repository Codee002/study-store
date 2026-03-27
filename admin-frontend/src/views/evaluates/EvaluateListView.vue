<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Đánh giá</h4>
          <div class="small opacity-75">Tất cả đánh giá của khách hàng trong hệ thống</div>
        </div>

        <button class="btn btn-outline-secondary" type="button" @click="fetchEvaluates">
          <i class="fa-solid fa-rotate me-1"></i> Tải lại
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
                  placeholder="Tìm theo sản phẩm, khách hàng, nội dung..."
                />
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-7 d-flex justify-content-md-end">
              <span class="badge bg-secondary-subtle text-secondary">Tổng: {{ meta.total }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-5 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>Đang tải đánh giá...
          </div>

          <div v-else-if="!items.length" class="py-5 text-center opacity-75">
            <i class="fa-regular fa-message fs-4 d-block mb-2"></i>
            Không có đánh giá phù hợp.
          </div>

          <div v-else class="d-flex flex-column gap-3">
            <article v-for="item in items" :key="item.id" class="evaluate-card">
              <div class="d-flex gap-3 align-items-start flex-column flex-md-row">
                <div class="media-box">
                  <img v-if="item.image_url" :src="item.image_url" alt="review-media" />
                  <div v-else class="media-placeholder">
                    <i class="fa-regular fa-image"></i>
                  </div>
                </div>

                <div class="flex-grow-1 min-w-0">
                  <div class="evaluate-header">
                    <div class="evaluate-meta min-w-0">
                      <div class="fw-semibold product-name" :title="item.product_name">{{ item.product_name }}</div>
                      <div class="small opacity-75">
                       Khách hàng: {{ item.customer_name }} | Đơn hàng #{{ item.order_id }}
                      </div>
                      <div class="small opacity-75">{{ formatDateTime(item.created_at) }}</div>
                    </div>

                    <div class="evaluate-actions">
                      <span class="rating-chip">
                        <i class="fa-solid fa-star me-1"></i>{{ Number(item.rating || 0).toFixed(1) }}
                      </span>
                      <RouterLink
                        class="btn btn-sm btn-outline-secondary"
                        :to="{ name: 'orders.detail', params: { id: item.order_id } }"
                      >
                        Đơn hàng
                      </RouterLink>
                      <button class="btn btn-sm btn-accent" type="button" @click="openReplyModal(item)">
                        {{ item.reply ? "Sửa phản hồi" : "Phản hồi" }}
                      </button>
                    </div>
                  </div>

                  <div class="mt-2 review-content">{{ item.content || "Không có nội dung." }}</div>

                  <div v-if="item.reply" class="reply-box mt-3">
                    <div class="small fw-semibold">Phản hồi của admin</div>
                    <div class="small opacity-75 mt-1">{{ item.reply }}</div>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>

        <div
          class="d-flex justify-content-between align-items-center p-3 border-top"
          v-if="meta.total"
        >
          <div class="small opacity-75">
            Hiển thị
            {{ (meta.current_page - 1) * meta.per_page + 1 }}
            -
            {{ Math.min(meta.current_page * meta.per_page, meta.total) }}
            /
            {{ meta.total }}
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

    <div v-if="replyModal.open" class="reply-modal-backdrop" @click.self="closeReplyModal">
      <div class="reply-modal">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
          <div>
            <h5 class="mb-1">{{ replyModal.item?.reply ? "Sua phan hoi" : "Phan hoi danh gia" }}</h5>
            <div class="small opacity-75">{{ replyModal.item?.product_name || "" }}</div>
          </div>
          <button class="btn btn-sm btn-outline-secondary" type="button" @click="closeReplyModal">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="small opacity-75 mb-2">Nội dung đánh giá</div>
        <div class="review-content mb-3">{{ replyModal.item?.content || "Không có nội dung." }}</div>

        <label class="form-label small fw-semibold">Phản hồi của admin</label>
        <textarea
          v-model="replyModal.reply"
          class="form-control bg-transparent"
          rows="5"
          placeholder="Nhập nội dung phản hồi..."
        ></textarea>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button class="btn btn-outline-secondary" type="button" :disabled="replyModal.saving" @click="closeReplyModal">
            Hủy
          </button>
          <button class="btn btn-accent" type="button" :disabled="replyModal.saving" @click="submitReply">
            {{ replyModal.saving ? "Đang lưu..." : "Lưu phản hồi" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import Swal from "sweetalert2";
import EvaluateService from "@/services/evaluate.service";

const keyword = ref("");
const page = ref(1);
const perPage = 10;
const loading = ref(false);
const items = ref([]);
const meta = ref({ current_page: 1, per_page: perPage, total: 0, last_page: 1 });

const replyModal = ref({
  open: false,
  saving: false,
  item: null,
  reply: "",
});

function formatDateTime(v) {
  if (!v) return "";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleString("vi-VN");
}

async function fetchEvaluates() {
  loading.value = true;
  try {
    const res = await EvaluateService.getAll({
      q: keyword.value.trim() || undefined,
      page: page.value,
      per_page: perPage,
    });

    items.value = res?.data?.items || [];
    meta.value = res?.data?.meta || meta.value;
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể tải đánh giá.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    loading.value = false;
  }
}

function openReplyModal(item) {
  replyModal.value = {
    open: true,
    saving: false,
    item,
    reply: item?.reply || "",
  };
}

function closeReplyModal() {
  replyModal.value = {
    open: false,
    saving: false,
    item: null,
    reply: "",
  };
}

async function submitReply() {
  const evaluateId = Number(replyModal.value.item?.id || 0);
  const reply = String(replyModal.value.reply || "").trim();
  if (!evaluateId || !reply) {
    await Swal.fire("Thiếu nội dung", "Vui lòng nhập nội dung phản hồi.", "warning");
    return;
  }

  replyModal.value.saving = true;
  try {
    const res = await EvaluateService.reply(evaluateId, reply);
    const index = items.value.findIndex((item) => Number(item.id) === evaluateId);
    if (index !== -1) {
      items.value[index] = { ...items.value[index], reply };
    }
    closeReplyModal();
    await Swal.fire("Thành công", res?.message || "Đã lưu phản hồi.", "success");
  } catch (e) {
    const msg = e?.response?.data?.message || "Không thể lưu phản hồi.";
    await Swal.fire("Lỗi", msg, "error");
  } finally {
    replyModal.value.saving = false;
  }
}

onMounted(fetchEvaluates);

watch(keyword, async () => {
  page.value = 1;
  await fetchEvaluates();
});

watch(page, fetchEvaluates);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.evaluate-card {
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.02);
}

.evaluate-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.evaluate-meta {
  flex: 1 1 auto;
  min-width: 0;
}

.product-name {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.evaluate-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  flex: 0 0 auto;
  flex-wrap: nowrap;
}

.media-box {
  width: 96px;
  height: 96px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: var(--hover-background-color);
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.media-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-placeholder {
  opacity: 0.6;
}

.rating-chip {
  border-radius: 999px;
  padding: 0.4rem 0.7rem;
  background: rgba(255, 193, 7, 0.14);
  border: 1px solid rgba(255, 193, 7, 0.35);
  color: #f59e0b;
  font-weight: 700;
}

.review-content {
  white-space: pre-line;
}

.reply-box {
  border-left: 3px solid var(--main-color);
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.03);
}

.btn-accent {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
}

.btn-accent:hover {
  filter: var(--brightness);
}

.reply-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 1050;
}

.reply-modal {
  width: min(680px, 100%);
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 1rem;
  color: var(--font-color);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

@media (max-width: 991.98px) {
  .evaluate-header {
    flex-direction: column;
  }

  .evaluate-actions {
    width: 100%;
    justify-content: flex-start;
    flex-wrap: wrap;
  }
}
</style>
