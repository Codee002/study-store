<template>
  <div class="chat-page">
    <div class="chat-shell card card-soft shadow-sm">
      <header class="chat-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <div class="avatar" v-if="currentContact?.avatar">
            <img :src="currentContact.avatar" alt="avatar" />
          </div>
          <div class="avatar" v-else>
            <i class="fa-solid fa-user"></i>
          </div>
          <div>
            <h5 class="mb-0">
              {{ currentContact?.name || "Chọn liên hệ để nhắn tin" }}
            </h5>
            <div class="small opacity-75">
              {{ currentContact ? "Trao đổi nhanh với khách hàng" : "Hãy chọn một liên hệ ở danh sách" }}
            </div>
          </div>
        </div>
        <RouterLink class="badge bg-primary-subtle text-primary text-decoration-none" to="/messages">
          <i class="fa-solid fa-list me-1"></i> Danh sách
        </RouterLink>
      </header>

      <div class="chat-body" ref="scrollBody">
        <div v-if="loadingMessages" class="text-center text-muted py-4">
          <div class="spinner-border text-primary mb-3" role="status"></div>
          Đang tải cuộc trò chuyện...
        </div>
        <div v-else-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <div v-else-if="!currentContact" class="empty-state text-center text-muted">
          <i class="fa-regular fa-comments fa-2x mb-2"></i>
          <p class="mb-1">Chưa chọn người cần hỗ trợ</p>
          <p class="small opacity-75">Vào danh sách liên hệ để bắt đầu cuộc trò chuyện.</p>
          <RouterLink to="/messages" class="btn btn-outline-secondary btn-sm">Mở danh sách</RouterLink>
        </div>

        <template v-else>
          <div v-if="!orderedMessages.length" class="text-center text-muted py-3">
            Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện!
          </div>
          <div v-for="(message, index) in orderedMessages" :key="message.id">
            <div
              v-if="shouldShowTime(index)"
              class="time-chip text-center small text-secondary opacity-75"
            >
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
                      <i v-else class="fa-regular fa-file-excel"></i>
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

      <footer class="chat-input" :class="{ disabled: !currentContact }">
        <div class="input-group">
          <button class="btn btn-outline-secondary" type="button" @click="openFilePicker">
            <i class="fa-solid fa-paperclip me-1"></i> Thêm phương tiện
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
            placeholder="Nhập tin nhắn..."
            @keydown.enter.exact.prevent="send"
            :disabled="!currentContact"
          ></textarea>
          <button class="btn btn-primary" type="button" :disabled="!canSend || !currentContact || sending" @click="send">
            {{ sending ? "Đang gửi..." : "Gửi" }}
          </button>
        </div>

        <div v-if="pendingFiles.length" class="pending-files mt-2">
          <div v-for="file in pendingFiles" :key="file.id" class="pending-pill">
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
                'fa-regular fa-file-excel': file.type === 'file'
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
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import dayjs from "dayjs";
import MessageService from "@/services/message.service";

const route = useRoute();
const router = useRouter();
const filePicker = ref(null);
const draft = ref("");
const messages = ref([]);
const pendingFiles = ref([]);
const scrollBody = ref(null);
const contacts = ref([]);
const loadingMessages = ref(false);
const sending = ref(false);
const error = ref("");
const currentAdminId = ref(getCurrentUserId());

const currentContact = computed(() =>
  contacts.value.find((c) => String(c.conversation_id) === String(route.params.id)) || null
);
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

function formatTime(time) {
  return dayjs(time).format("HH:mm");
}

function formatGapTime(time) {
  return dayjs(time).format("DD/MM/YYYY HH:mm");
}

function shouldShowTime(index) {
  if (index === 0) return true;
  const prev = orderedMessages.value[index - 1];
  const current = orderedMessages.value[index];
  const diff = dayjs(current.time).diff(dayjs(prev.time), "minute");
  return diff >= 30;
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

function mapMedia(media) {
  const type = media.type || detectTypeByName(media.name) || detectTypeByUrl(media.url);
  return {
    id: media.id,
    name: media.name || "Tập tin",
    url: media.url,
    type: type || "file",
  };
}

function mapMessage(raw) {
  return {
    id: raw.id,
    sender: raw.user_id === currentAdminId.value ? "me" : "them",
    text: raw.content,
    time: raw.created_at,
    attachments: (raw.medias || []).map(mapMedia),
  };
}

async function fetchContacts() {
  try {
    const res = await MessageService.fetchContacts();
    contacts.value = res?.contacts || [];
  } catch (e) {
    // swallow; shown in chat area when needed
  }
}

async function loadMessages(conversationId) {
  if (!conversationId) return;
  loadingMessages.value = true;
  error.value = "";
  try {
    const res = await MessageService.fetchMessages(conversationId);
    messages.value = (res?.messages || []).map(mapMessage);
    await nextTick();
    scrollToBottom();
  } catch (e) {
    error.value =
      e?.response?.data?.message || e?.message || "Không tải được cuộc trò chuyện.";
  } finally {
    loadingMessages.value = false;
  }
}

function openFilePicker() {
  if (!currentContact.value) {
    router.push({ name: "messages.list" });
    return;
  }
  filePicker.value?.click();
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
  if (!canSend.value || !currentContact.value || sending.value) return;
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

    const res = await MessageService.sendMessage(route.params.id, {
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
      e?.response?.data?.message || e?.message || "Không gửi được tin nhắn. Vui lòng thử lại.";
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

async function bootstrap() {
  await fetchContacts();
  await loadMessages(route.params.id);
}

onMounted(bootstrap);

watch(
  () => route.params.id,
  async () => {
    await fetchContacts();
    await loadMessages(route.params.id);
  }
);
</script>

<style scoped>
.chat-page {
  padding: 16px;
  color: var(--font-color);
}
.chat-shell {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
}
.chat-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
}
.avatar {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--primary) 12%, transparent);
  color: var(--primary);
  border: 1px solid color-mix(in srgb, var(--primary) 30%, transparent);
  overflow: hidden;
}
.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.chat-body {
  flex: 1;
  overflow: auto;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--primary) 4%, transparent), transparent 30%),
    radial-gradient(circle at 80% 40%, color-mix(in srgb, var(--success) 4%, transparent), transparent 28%),
    var(--hover-background-color);
}
.empty-state {
  margin-top: 40px;
}
.time-chip {
  margin: 8px auto;
  padding: 6px 12px;
  border-radius: 20px;
  background: color-mix(in srgb, var(--border-color) 30%, transparent);
  width: fit-content;
}
.chat-row {
  display: flex;
}
.chat-row.from-me {
  justify-content: flex-end;
}
.chat-row.from-them {
  justify-content: flex-start;
}
.bubble {
  max-width: min(640px, 80%);
  background: var(--hover-background-color);
  border: 1px solid var(--border-color);
  border-radius: 18px;
  padding: 12px 14px 10px;
  box-shadow: 0 8px 24px color-mix(in srgb, #000 6%, transparent);
}
.from-me .bubble {
  background: color-mix(in srgb, var(--primary) 18%, transparent);
  border-color: color-mix(in srgb, var(--primary) 30%, transparent);
}
.bubble-text {
  white-space: pre-line;
  line-height: 1.45;
  margin-bottom: 8px;
}
.attach-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
  width: 34px;
  height: 34px;
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
.attach-thumb.excel {
  background: color-mix(in srgb, #16a34a 14%, transparent);
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
  padding: 14px 16px;
  border-top: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  position: sticky;
  bottom: 0;
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
  .chat-shell {
    height: calc(100vh - 80px);
  }
  .bubble {
    max-width: 100%;
  }
  .attach-grid {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }
}
</style>
