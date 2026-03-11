<template>
  <div>
    <AppHeader :cart-count="0" />

    <main class="container py-4">
      <div class="chat-shell card card-soft shadow-sm">
        <header class="chat-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <img
              v-if="conversation?.admin?.avatar"
              :src="conversation.admin.avatar"
              class="avatar"
              alt="avatar"
            />
            <div v-else class="avatar placeholder">
              <i class="fa-regular fa-user"></i>
            </div>
            <div>
              <h5 class="mb-0">
                {{ conversation?.admin?.name || "Admin" }}
              </h5>
              <div class="small text-muted">
                Hỗ trợ khách hàng
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <RouterLink to="/contact" class="btn btn-outline-secondary btn-sm">
              <i class="fa-solid fa-list me-1"></i> Liên hệ
            </RouterLink>
          </div>
        </header>

        <div class="chat-body" ref="scrollBody">
          <div v-if="loading" class="text-center text-muted py-4">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            Đang tải cuộc trò chuyện...
          </div>
          <div v-else-if="error" class="alert alert-danger m-3">
            {{ error }}
          </div>
          <div v-else-if="!conversation" class="empty text-center text-muted">
            Không tìm thấy cuộc trò chuyện.
          </div>
          <template v-else>
            <div v-if="!orderedMessages.length" class="text-center text-muted py-3">
              Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện!
            </div>
            <div v-for="(message, index) in orderedMessages" :key="message.id">
              <div v-if="shouldShowTime(index)" class="time-chip">
                {{ formatGapTime(message.time) }}
              </div>

              <div class="chat-row" :class="message.sender === 'me' ? 'from-me' : 'from-them'">
                <div class="bubble">
                  <div class="bubble-text" v-if="message.text">{{ message.text }}</div>

                  <div v-if="message.attachments?.length" class="attach-grid">
                    <a
                      v-for="file in message.attachments"
                      :key="file.id"
                      class="attach-card"
                      :href="file.url"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <div v-if="file.type === 'image'" class="attach-media">
                        <img :src="file.url" :alt="file.name" class="attach-image" loading="lazy" />
                      </div>
                      <div v-else-if="file.type === 'video'" class="attach-media">
                        <video :src="file.url" class="attach-video" controls preload="metadata"></video>
                      </div>
                      <div v-else class="attach-thumb" :class="file.type">
                        <i v-if="file.type === 'pdf'" class="fa-regular fa-file-pdf"></i>
                        <i v-else class="fa-regular fa-file-lines"></i>
                      </div>
                      <div class="attach-name text-truncate" :title="file.name">
                        {{ file.name }}
                      </div>
                    </a>
                  </div>

                  <div class="meta small opacity-75 d-flex gap-2 align-items-center">
                    <span>{{ formatTime(message.time) }}</span>
                    <span v-if="message.sender === 'me'">
                      <i class="fa-solid fa-check-double text-primary"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <footer class="chat-input" :class="{ disabled: !conversation }">
          <div class="input-group">
            <button class="btn btn-outline-secondary" type="button" @click="openFilePicker">
              <i class="fa-solid fa-paperclip me-1"></i> Thêm file
            </button>
            <input
              ref="filePicker"
              type="file"
              class="d-none"
              multiple
              accept="image/*,video/*,.pdf,.xlsx,.doc,.docx"
              @change="onFilesSelected"
            />
            <textarea
              v-model="draft"
              class="form-control"
              rows="1"
              placeholder="Viết tin nhắn..."
              @keydown.enter.exact.prevent="send"
              :disabled="!conversation"
            ></textarea>
            <button
              class="btn btn-main"
              type="button"
              :disabled="!canSend || !conversation || sending"
              @click="send"
            >
              {{ sending ? "Đang gửi..." : "Gửi" }}
            </button>
          </div>

          <div v-if="pendingFiles.length" class="pending-files mt-2">
            <div
              v-for="file in pendingFiles"
              :key="file.id"
              class="pending-pill"
            >
              <div v-if="file.type === 'image'" class="pending-thumb">
                <img :src="file.previewUrl" :alt="file.name" />
              </div>
              <div v-else-if="file.type === 'video'" class="pending-thumb video">
                <video :src="file.previewUrl" muted></video>
              </div>
              <i
                v-else
                class="me-1"
                :class="{
                  'fa-regular fa-file-pdf': file.type === 'pdf',
                  'fa-regular fa-file-lines': file.type === 'file'
                }"
              ></i>
              <span class="text-truncate">{{ file.name }}</span>
              <button class="btn btn-sm btn-link text-danger px-1" @click="removePending(file.id)">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
          </div>
        </footer>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import dayjs from "dayjs";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import MessageService from "@/services/message.service";

const route = useRoute();
const router = useRouter();
const draft = ref("");
const messages = ref([]);
const pendingFiles = ref([]);
const filePicker = ref(null);
const scrollBody = ref(null);
const conversation = ref(null);
const loading = ref(true);
const error = ref("");
const sending = ref(false);
const currentUserId = ref(getCurrentUserId());

const orderedMessages = computed(() =>
  [...messages.value].sort((a, b) => new Date(a.time) - new Date(b.time))
);
const canSend = computed(() => draft.value.trim() || pendingFiles.value.length);

function getCurrentUserId() {
  try {
    const raw = localStorage.getItem("currentUser");
    return raw ? JSON.parse(raw)?.id : null;
  } catch (e) {
    return null;
  }
}

function mapMedia(media) {
  const type = media.type || detectTypeByName(media.name) || detectTypeByUrl(media.url);
  return {
    id: media.id,
    name: media.name || "T?p tin",
    url: media.url,
    type: type || "file",
  };
}

function mapMessage(raw) {
  return {
    id: raw.id,
    sender: raw.user_id === currentUserId.value ? "me" : "them",
    text: raw.content,
    time: raw.created_at,
    attachments: (raw.medias || []).map(mapMedia),
  };
}

async function loadMessages(conversationId) {
  const res = await MessageService.fetchMessages(conversationId);
  messages.value = (res?.messages || []).map(mapMessage);
  await nextTick();
  scrollToBottom();
}

async function bootstrap() {
  loading.value = true;
  error.value = "";
  try {
    const res = await MessageService.ensureConversation();
    conversation.value = res?.conversation || null;
    const convId = conversation.value?.id;

    if (convId && route.params.id !== String(convId)) {
      router.replace({ name: "contact.chat", params: { id: convId } });
    }

    if (convId) {
      await loadMessages(convId);
    } else {
      error.value = "Không tìm thấy cuộc trò chuyện với admin.";
    }
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      "Không mở được cuộc trò chuyện. Vui lòng thử lại.";
  } finally {
    loading.value = false;
  }
}

function formatTime(t) {
  return dayjs(t).format("HH:mm");
}

function formatGapTime(t) {
  return dayjs(t).format("DD/MM/YYYY HH:mm");
}

function shouldShowTime(index) {
  if (index === 0) return true;
  const prev = orderedMessages.value[index - 1];
  const current = orderedMessages.value[index];
  return dayjs(current.time).diff(dayjs(prev.time), "minute") >= 30;
}

function openFilePicker() {
  if (!conversation.value) {
    router.push({ name: "contact.list" });
    return;
  }
  filePicker.value?.click();
}

function detectType(file) {
  if (file.type.startsWith("image")) return "image";
  if (file.type.startsWith("video")) return "video";
  if (file.name.toLowerCase().endsWith(".pdf")) return "pdf";
  return "file";
}

function detectTypeByName(name = "") {
  const lower = name.toLowerCase();
  if (lower.match(/\.(png|jpe?g|gif|webp|bmp|svg)$/)) return "image";
  if (lower.match(/\.(mp4|webm|ogg|mov)$/)) return "video";
  if (lower.endsWith(".pdf")) return "pdf";
  return null;
}

function detectTypeByUrl(url = "") {
  try {
    const path = new URL(url).pathname || "";
    return detectTypeByName(path);
  } catch (e) {
    return null;
  }
}

function onFilesSelected(event) {
  const files = Array.from(event.target.files || []);
  const mapped = files.map((file, idx) => ({
    id: `${Date.now()}-${idx}`,
    name: file.name,
    type: detectType(file),
    file,
    previewUrl: URL.createObjectURL(file),
  }));
  pendingFiles.value = [...pendingFiles.value, ...mapped];
  event.target.value = "";
}

async function send() {
  if (!canSend.value || !conversation.value || sending.value) return;
  sending.value = true;
  try {
    const optimisticId = `local-${Date.now()}`;
    const optimisticMessage = {
      id: optimisticId,
      sender: "me",
      text: draft.value.trim(),
      time: new Date().toISOString(),
      attachments: pendingFiles.value.map((f) => ({
        id: f.id,
        name: f.name,
        url: f.previewUrl,
        type: f.type,
      })),
      status: "sending",
    };
    messages.value.push(optimisticMessage);

    const res = await MessageService.sendMessage(conversation.value.id, {
      content: draft.value.trim(),
      files: pendingFiles.value.map((f) => f.file),
    });

    if (res?.data) {
      const idx = messages.value.findIndex((m) => m.id === optimisticId);
      if (idx !== -1) {
        messages.value[idx] = mapMessage(res.data);
      } else {
        messages.value.push(mapMessage(res.data));
      }
    }

    draft.value = "";
    pendingFiles.value.forEach((f) => f.previewUrl && URL.revokeObjectURL(f.previewUrl));
    pendingFiles.value = [];
    await nextTick();
    scrollToBottom();
  } catch (e) {
    messages.value = messages.value.filter((m) => m.status !== "sending");
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      "Khong gui duoc tin nhan. Vui long thu lai.";
  } finally {
    sending.value = false;
  }
}

function removePending(id) {
  const target = pendingFiles.value.find((f) => f.id === id);
  if (target?.previewUrl) {
    URL.revokeObjectURL(target.previewUrl);
  }
  pendingFiles.value = pendingFiles.value.filter((f) => f.id !== id);
}

function scrollToBottom() {
  const el = scrollBody.value;
  if (!el) return;
  el.scrollTop = el.scrollHeight;
}

onMounted(bootstrap);
watch(
  () => route.params.id,
  () => bootstrap()
);
</script>

<style scoped>
.chat-shell {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  min-height: 70vh;
  max-width: 960px;
  margin: 0 auto;
  height: calc(100vh - 160px);
  display: flex;
  flex-direction: column;
}
.chat-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-color);
}
.avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--border-color);
}
.avatar.placeholder {
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--border-color) 30%, transparent);
  color: var(--font-color);
}
.chat-body {
  flex: 1;
  padding: 14px 16px;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--primary) 4%, transparent), transparent 30%),
    radial-gradient(circle at 80% 40%, color-mix(in srgb, var(--success) 4%, transparent), transparent 28%),
    var(--hover-background-color);
}
.time-chip {
  margin: 8px auto;
  padding: 6px 12px;
  border-radius: 20px;
  background: color-mix(in srgb, var(--border-color) 30%, transparent);
  width: fit-content;
  font-size: 0.8rem;
  color: var(--font-color);
}
.chat-row {
  display: flex;
}
.from-me {
  justify-content: flex-end;
}
.from-them {
  justify-content: flex-start;
}
.bubble {
  max-width: min(640px, 80%);
  background: var(--hover-background-color);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 10px 12px;
  box-shadow: 0 8px 24px color-mix(in srgb, #000 6%, transparent);
}
.from-me .bubble {
  background: color-mix(in srgb, var(--primary) 18%, transparent);
  border-color: color-mix(in srgb, var(--primary) 30%, transparent);
}
.bubble-text {
  white-space: pre-line;
  line-height: 1.45;
  margin-bottom: 6px;
}
.attach-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 8px;
  margin-bottom: 6px;
}
.attach-card {
  border: 1px dashed var(--border-color);
  border-radius: 12px;
  padding: 8px 10px;
  background: color-mix(in srgb, var(--main-extra-bg) 80%, transparent);
  display: flex;
  align-items: center;
  gap: 8px;
  color: inherit;
  text-decoration: none;
}
.attach-media {
  width: 72px;
  height: 72px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
  background: color-mix(in srgb, var(--border-color) 40%, transparent);
  display: grid;
  place-items: center;
}
.attach-image,
.attach-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.attach-thumb {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  color: var(--font-color);
}
.attach-thumb.image {
  background: color-mix(in srgb, var(--primary) 10%, transparent);
}
.attach-thumb.video {
  background: color-mix(in srgb, var(--warning) 14%, transparent);
}
.attach-thumb.pdf {
  background: color-mix(in srgb, #ef4444 14%, transparent);
}
.attach-thumb.file {
  background: color-mix(in srgb, var(--border-color) 35%, transparent);
}
.attach-name {
  flex: 1;
}
.meta {
  text-align: right;
}
.from-them .meta {
  justify-content: flex-start;
}
.chat-input {
  padding: 12px 14px;
  border-top: 1px solid var(--border-color);
  background: var(--main-extra-bg);
}
.chat-input .form-control {
  background: var(--hover-background-color);
  border: 1px solid var(--border-color);
  resize: none;
}
.chat-input.disabled {
  opacity: 0.6;
  pointer-events: none;
}
.pending-files {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.pending-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 12px;
  background: color-mix(in srgb, var(--border-color) 35%, transparent);
  border: 1px solid var(--border-color);
  max-width: 240px;
}
.pending-thumb {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
}
.pending-thumb img,
.pending-thumb video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.pending-thumb.video {
  background: color-mix(in srgb, var(--border-color) 40%, transparent);
}
.pending-pill .btn-link {
  line-height: 1;
}
@media (max-width: 768px) {
  .bubble {
    max-width: 100%;
  }
  .attach-grid {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }
  .chat-shell {
    height: auto;
    min-height: 75vh;
    max-width: 100%;
  }
}
</style>
