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
                <div class="bubble" :class="{ recalled: message.type === 'recalled' }">
                  <div v-if="message.sender === 'me' && message.type !== 'recalled'" class="msg-actions left">
                    <button class="icon-btn" @click.stop="toggleMenu(message.id)">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div v-if="openMenuId === message.id" class="dropdown-menu show">
                      <button class="dropdown-item" @click.stop="recall(message)">Thu hồi</button>
                    </div>
                  </div>
                  <div class="bubble-text" v-if="message.type === 'recalled'">
                    <i class="fa-regular fa-circle-xmark me-1"></i> Tin nhắn đã bị thu hồi
                  </div>
                  <div class="bubble-text" v-else-if="message.text">{{ message.text }}</div>

                  <div v-if="message.attachments?.length && message.type !== 'recalled'" class="attach-grid">
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
                    </a>
                  </div>

                  <div class="meta small opacity-75 d-flex gap-2 align-items-center">
                    <span>{{ formatTime(message.time) }}</span>
                    <span
                      v-if="message.sender === 'me' && message.id === lastOwnMessageId && message.is_read_by_partner"
                    >
                      Đã xem
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
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import dayjs from "dayjs";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import MessageService from "@/services/message.service";

const route = useRoute();
const router = useRouter();
const CHAT_SEEN_KEY = "customer_chat_seen_at";
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
const channelName = computed(() =>
  currentUserId.value ? `user.${currentUserId.value}` : null
);
let echoChannel = null;
const openMenuId = ref(null);

const orderedMessages = computed(() =>
  [...messages.value].sort((a, b) => new Date(a.time) - new Date(b.time))
);
const canSend = computed(() => draft.value.trim() || pendingFiles.value.length);
const lastOwnMessageId = computed(() => {
  for (let i = orderedMessages.value.length - 1; i >= 0; i -= 1) {
    const m = orderedMessages.value[i];
    if (m.sender === "me") return m.id;
  }
  return null;
});
// Tự cuộn khi tổng số tin thay đổi
watch(
  () => messages.value.length,
  () => nextTick(() => scrollToBottom()),
);

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
  const readIds = raw.read_by_user_ids || [];
  const partnerId = conversation.value?.admin?.id;
  return {
    id: raw.id,
    sender: raw.user_id === currentUserId.value ? "me" : "them",
    text: raw.content,
    time: raw.created_at,
    type: raw.type,
    is_read: raw.is_read ?? readIds.includes(currentUserId.value),
    is_read_by_partner: partnerId ? readIds.includes(partnerId) : false,
    attachments: (raw.medias || []).map(mapMedia),
  };
}

function mapIncoming(raw) {
  const readIds = raw.read_by_user_ids || [];
  const partnerId = conversation.value?.admin?.id;
  return {
    id: raw.id,
    sender: raw.user_id === currentUserId.value ? "me" : "them",
    text: raw.content,
    time: raw.created_at,
    type: raw.type,
    is_read: raw.is_read ?? readIds.includes(currentUserId.value) ?? (raw.user_id === currentUserId.value),
    is_read_by_partner: partnerId ? readIds.includes(partnerId) : false,
    attachments: (raw.medias || []).map(mapMedia),
  };
}

function upsertMessage(msg) {
  const idx = messages.value.findIndex((m) => m.id === msg.id);
  if (idx !== -1) {
    messages.value[idx] = msg;
  } else {
    messages.value.push(msg);
  }
}

async function loadMessages(conversationId) {
  const res = await MessageService.fetchMessages(conversationId);
  messages.value = (res?.messages || []).map(mapMessage);
  await nextTick();
  scrollToBottom();
  markMessagesSeen();
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
       startRealtime();
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

async function recall(message) {
  if (!conversation.value || message.sender !== "me" || message.type === "recalled") return;
  openMenuId.value = null;
  // Update UI ngay lập tức
  upsertMessage({
    ...message,
    text: null,
    attachments: [],
    type: "recalled",
  });
  try {
    await MessageService.recallMessage(conversation.value.id, message.id);
  } catch (e) {
    error.value =
      e?.response?.data?.message || e?.message || "Không thu hồi được tin nhắn.";
  }
}

function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

function removePending(id) {
  const target = pendingFiles.value.find((f) => f.id === id);
  if (target?.previewUrl) {
    URL.revokeObjectURL(target.previewUrl);
  }
  pendingFiles.value = pendingFiles.value.filter((f) => f.id !== id);
}

function stopRealtime() {
  if (channelName.value && window.Echo) {
    window.Echo.leave(channelName.value);
  }
  echoChannel = null;
}

function handleIncomingMessage(event) {
  if (!event?.conversation_id) return;
  if (event.user_id === currentUserId.value) {
    messages.value = messages.value.filter((m) => m.status !== "sending");
    return;
  }
  if (String(event.conversation_id) !== String(conversation.value?.id)) return;
  const normalized = mapIncoming(event);
  upsertMessage(normalized);
  nextTick(scrollToBottom);
  markMessagesSeen();
}

function startRealtime() {
  console.log(channelName.value)
  if (!channelName.value || !window.Echo) return;
  stopRealtime();
  echoChannel = window.Echo.private(channelName.value).listen(
    ".MessageSent",
    handleIncomingMessage
  );
}

function scrollToBottom() {
  const el = scrollBody.value;
  if (!el) return;
  requestAnimationFrame(() => {
    el.scrollTop = el.scrollHeight;
  });
}

function markMessagesSeen() {
  localStorage.setItem(CHAT_SEEN_KEY, new Date().toISOString());
  window.dispatchEvent(new CustomEvent("customer-messages-read"));
}

onMounted(bootstrap);
onBeforeUnmount(stopRealtime);
watch(
  () => route.params.id,
  () => bootstrap()
);
</script>

<style scoped>
main.container {
  max-width: 100%;
  padding: 0 14px 22px;
}
.chat-shell {
  width: 100%;
  height: calc(100vh - 140px);
  border-radius: 18px;
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  box-shadow: 0 12px 28px color-mix(in srgb, #000 10%, transparent);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.chat-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-color);
  background: color-mix(in srgb, var(--primary) 12%, var(--main-extra-bg));
  color: var(--font-color);
}
.avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid color-mix(in srgb, var(--primary) 25%, var(--border-color));
}
.avatar.placeholder {
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--primary) 10%, transparent);
  color: var(--primary);
}
.chat-body {
  flex: 1;
  overflow: auto;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #ffffff;
}
.empty {
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
  position: relative;
  max-width: 78%;
  background: #ffffff;
  border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent);
  border-radius: 18px;
  padding: 12px 14px 10px;
  box-shadow: 0 6px 18px color-mix(in srgb, #000 8%, transparent);
}
.from-me .bubble {
  background: #ffddba;
  border-color: color-mix(in srgb, #f59e0b 35%, #ffddba);
}
.bubble.recalled {
  font-style: italic;
  color: #9ca3af;
  background: #f9fafb;
  border-style: dashed;
}
.bubble-text {
  white-space: pre-line;
  line-height: 1.45;
  margin-bottom: 8px;
}
.attach-grid {
  display: flex;
  gap: 10px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}
.attach-card {
  border: 1px dashed var(--border-color);
  border-radius: 12px;
  padding: 8px 10px;
  background: var(--hover-background-color);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: inherit;
  text-decoration: none;
  min-width: 120px;
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
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--border-color) 20%, transparent);
  font-size: 18px;
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
  background: #ffffff;
  border: 1px solid color-mix(in srgb, var(--border-color) 70%, transparent);
  resize: none;
  color: var(--font-color);
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
.msg-actions {
  position: absolute;
  top: 8px;
  left: -38px;
}
.msg-actions.left .dropdown-menu {
  left: 0;
  top: 28px;
}
.icon-btn {
  border: 1px solid color-mix(in srgb, var(--border-color) 50%, transparent);
  background: #ffddba;
  border-radius: 10px;
  padding: 4px 6px;
  color: var(--font-color);
}
.dropdown-menu {
  position: absolute;
  min-width: 120px;
  background: #ffffff;
  border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent);
  border-radius: 10px;
  padding: 6px 0;
  box-shadow: 0 10px 24px color-mix(in srgb, #000 10%, transparent);
  z-index: 5;
}
.dropdown-item {
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  background: none;
  border: none;
  color: var(--font-color);
}
.dropdown-item:hover {
  background: var(--hover-background-color);
}
@media (max-width: 768px) {
  main.container {
    padding: 0 10px 18px;
  }
  .chat-shell {
    height: calc(100vh - 120px);
    border-radius: 16px;
  }
  .bubble {
    max-width: 100%;
  }
  .attach-grid {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  }
}
</style>
