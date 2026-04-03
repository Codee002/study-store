<template>
  <div class="page-wrap">
    <div class="inbox-grid">
      <aside class="card card-soft shadow-sm inbox-sidebar">
        <div class="p-3 border-bottom">
          <h5 class="mb-1">Hộp thư</h5>
          <div class="small text-muted">Chọn khách hàng để mở cuộc trò chuyện.</div>
        </div>
        <div class="p-3 border-bottom">
          <div class="input-group">
            <span class="input-group-text bg-transparent"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input
              v-model.trim="keyword"
              type="search"
              class="form-control bg-transparent"
              placeholder="Tìm theo tên khách hàng..."
              @keydown.enter.prevent="fetchContacts"
            />
            <button v-if="keyword" class="btn btn-outline-secondary" type="button" @click="clearKeyword">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
        </div>

        <div v-if="loadingContacts" class="p-4 text-center text-muted">Đang tải hội thoại...</div>
        <div v-else-if="!filteredContacts.length" class="p-4 text-center text-muted">Chưa có liên hệ.</div>

        <button
          v-for="contact in filteredContacts"
          :key="contact.id"
          class="conversation-item"
          :class="{ active: String(contact.conversation_id || '') === String(activeConversationId || '') }"
          @click="selectConversation(contact.conversation_id, contact)"
        >
          <div v-if="contact.avatar" class="avatar-box">
            <img :src="contact.avatar" :alt="contact.name" />
          </div>
          <div v-else class="avatar-box"><i class="fa-regular fa-user"></i></div>
          <div class="conversation-copy">
            <div class="d-flex justify-content-between gap-2">
              <strong class="text-truncate">{{ contact.name }}</strong>
              <small class="text-muted">{{ formatShortTime(contact.updated_at) }}</small>
            </div>
            <div class="small text-truncate text-muted">{{ truncateMessage(contact.last_message || "Chưa có tin nhắn") }}</div>
          </div>
          <span v-if="contact.unread" class="badge rounded-pill bg-danger">{{ contact.unread }}</span>
        </button>
      </aside>

      <section class="card card-soft shadow-sm chat-shell">
        <header class="chat-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div v-if="currentContact?.avatar" class="header-avatar">
              <img :src="currentContact.avatar" :alt="currentContact.name" />
            </div>
            <div v-else class="header-avatar"><i class="fa-regular fa-user"></i></div>
            <div>
              <h5 class="mb-0">{{ currentContact?.name || "Hộp chat" }}</h5>
              <div class="small text-muted">
                {{ currentContact ? "Trao đổi nhanh với khách hàng" : "Hãy chọn một liên hệ ở danh sách" }}
              </div>
            </div>
          </div>
          <RouterLink to="/messages" class="btn btn-outline-secondary btn-sm">Danh sách</RouterLink>
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
            <p class="small opacity-75">Hãy chọn một liên hệ ở danh sách bên trái.</p>
          </div>

          <template v-else>
            <div v-if="!orderedMessages.length" class="text-center text-muted py-3">
              Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện!
            </div>
            <div v-for="(message, index) in orderedMessages" :key="message.id">
              <div v-if="shouldShowTime(index)" class="time-chip">{{ formatGapTime(message.time) }}</div>
              <div class="message-row" :class="{ mine: message.sender === 'me' }">
                <div class="message-bubble" :class="{ recalled: message.type === 'recalled' }">
                  <div v-if="message.sender === 'me' && message.type !== 'recalled'" class="msg-actions left">
                    <button class="icon-btn" @click.stop="toggleMenu(message.id)">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div v-if="openMenuId === message.id" class="dropdown-menu show">
                      <button class="dropdown-item" @click.stop="recall(message)">Thu hồi</button>
                    </div>
                  </div>

                  <div v-if="message.type === 'recalled'" class="fst-italic text-muted">Tin nhắn đã bị thu hồi</div>
                  <div v-else-if="message.text" class="bubble-text">{{ message.text }}</div>

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

                  <div v-if="message.products?.length && message.type !== 'recalled'" class="suggested-products">
                    <div v-for="product in message.products" :key="product.id" class="suggested-card">
                      <RouterLink class="suggested-thumb" :to="product.url">
                        <img v-if="product.image" :src="product.image" :alt="product.name" class="suggested-image" />
                        <div v-else class="suggested-fallback"><i class="fa-regular fa-image"></i></div>
                      </RouterLink>
                      <div class="suggested-copy">
                        <RouterLink class="suggested-name" :to="product.url">{{ product.name }}</RouterLink>
                        <div class="small text-muted">{{ product.category }}</div>
                        <div v-if="product.price != null" class="suggested-price">{{ Number(product.price).toLocaleString("vi-VN") }} đ</div>
                        <div v-if="product.unit" class="small text-muted mt-1">Đơn vị: {{ product.unit }}</div>
                      </div>
                    </div>
                  </div>

                  <div class="small opacity-75 mt-2 d-flex gap-2 align-items-center" :class="message.sender === 'me' ? 'justify-content-end' : 'justify-content-start'">
                    <span>{{ formatTime(message.time) }}</span>
                    <span v-if="message.sender === 'me' && message.id === lastOwnMessageId && message.is_read_by_partner">Đã xem</span>
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
              <div v-if="file.type === 'image'" class="pending-thumb"><img :src="file.previewUrl" :alt="file.name" /></div>
              <div v-else-if="file.type === 'video'" class="pending-thumb video"><video :src="file.previewUrl" muted></video></div>
              <i
                v-else
                class="me-1"
                :class="{ 'fa-regular fa-file-pdf': file.type === 'pdf', 'fa-regular fa-file-excel': file.type === 'file' }"
              ></i>
              <span class="text-truncate">{{ file.name }}</span>
              <button class="btn btn-sm btn-link text-danger px-1" @click="removePending(file.id)">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
          </div>
        </footer>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
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
const keyword = ref("");
const loadingContacts = ref(false);
const loadingMessages = ref(false);
const sending = ref(false);
const error = ref("");
const syncingRead = ref(false);
const currentAdminId = ref(getCurrentUserId());
const channelName = computed(() => (currentAdminId.value ? `user.${currentAdminId.value}` : null));
const activeConversationId = ref(null);
let echoChannel = null;
const openMenuId = ref(null);

const filteredContacts = computed(() => {
  const q = keyword.value.toLowerCase().trim();
  if (!q) return contacts.value;
  return contacts.value.filter((contact) => String(contact.name || "").toLowerCase().includes(q));
});
const currentContact = computed(() =>
  contacts.value.find((contact) => String(contact.conversation_id || "") === String(activeConversationId.value || "")) || null
);
const orderedMessages = computed(() => [...messages.value].sort((a, b) => new Date(a.time) - new Date(b.time)));
const canSend = computed(() => draft.value.trim() || pendingFiles.value.length);
const lastOwnMessageId = computed(() => [...orderedMessages.value].reverse().find((message) => message.sender === "me")?.id || null);

watch(() => messages.value.length, () => nextTick(() => scrollToBottom()));

function getCurrentUserId() {
  try {
    const raw = localStorage.getItem("currentUser");
    return raw ? JSON.parse(raw)?.id : null;
  } catch {
    return null;
  }
}

function formatTime(time) { return dayjs(time).format("HH:mm"); }
function formatShortTime(time) { return time ? (dayjs(time).isSame(dayjs(), "day") ? dayjs(time).format("HH:mm") : dayjs(time).format("DD/MM")) : ""; }
function formatGapTime(time) { return dayjs(time).format("DD/MM/YYYY HH:mm"); }
function shouldShowTime(index) {
  if (index === 0) return true;
  const prev = orderedMessages.value[index - 1];
  const current = orderedMessages.value[index];
  return dayjs(current.time).diff(dayjs(prev.time), "minute") >= 30;
}
function truncateMessage(value, max = 70) {
  const text = String(value || "").trim();
  if (!text) return "Chưa có tin nhắn";
  return text.length <= max ? text : `${text.slice(0, max).trimEnd()}...`;
}
function clearKeyword() { keyword.value = ""; }
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
    return detectTypeByName(new URL(url).pathname || "");
  } catch {
    return null;
  }
}
function mapMedia(media) {
  const type = media.type || detectTypeByName(media.name) || detectTypeByUrl(media.url);
  return { id: media.id, name: media.name || "Tập tin", url: media.url, type: type || "file" };
}
function mapMessage(raw) {
  const readIds = raw.read_by_user_ids || [];
  const otherId = currentContact.value?.id;
  return {
    id: raw.id,
    sender: raw.user_id === currentAdminId.value ? "me" : "them",
    text: raw.content,
    time: raw.created_at,
    type: raw.type,
    is_read: raw.is_read ?? readIds.includes(currentAdminId.value),
    is_read_by_partner: otherId ? readIds.includes(otherId) : false,
    attachments: (raw.medias || []).map(mapMedia),
    products: raw.products || [],
  };
}
function mapIncoming(payload) {
  const readIds = payload.read_by_user_ids || [];
  const otherId = currentContact.value?.id;
  return {
    id: payload.id,
    sender: payload.user_id === currentAdminId.value ? "me" : "them",
    text: payload.content,
    time: payload.created_at,
    type: payload.type,
    is_read: payload.is_read ?? readIds.includes(currentAdminId.value) ?? (payload.user_id === currentAdminId.value),
    is_read_by_partner: otherId ? readIds.includes(otherId) : false,
    attachments: (payload.medias || []).map(mapMedia),
    products: payload.products || [],
  };
}
function upsertMessage(msg) {
  const idx = messages.value.findIndex((m) => m.id === msg.id);
  if (idx !== -1) messages.value[idx] = msg;
  else messages.value.push(msg);
}
function sortContacts() {
  contacts.value = [...contacts.value].sort((a, b) => new Date(b?.updated_at || 0) - new Date(a?.updated_at || 0));
}
function updateContactFromEvent(payload) {
  const idx = contacts.value.findIndex((contact) => String(contact.conversation_id || "") === String(payload.conversation_id || ""));
  if (idx === -1) return;
  const current = contacts.value[idx];
  const preview = payload.type === "media" ? "Đã gửi phương tiện" : payload.type === "recalled" ? "Tin nhắn đã bị thu hồi" : (payload.content || current.last_message || "Chưa có tin nhắn");
  contacts.value[idx] = {
    ...current,
    last_message: preview,
    updated_at: payload.created_at || current.updated_at,
    unread: String(activeConversationId.value || "") === String(current.conversation_id || "") || Number(payload.user_id) === Number(currentAdminId.value) ? 0 : Number(current.unread || 0) + 1,
  };
  sortContacts();
}
function applyReadUpdates(event) {
  if (!event?.conversation_id || String(event.conversation_id) !== String(activeConversationId.value || "")) return;
  const updates = Array.isArray(event.messages) ? event.messages : [];
  if (!updates.length) return;
  const otherId = currentContact.value?.id;
  let changed = false;
  messages.value = messages.value.map((message) => {
    const matched = updates.find((item) => String(item.id) === String(message.id));
    if (!matched) return message;
    const readIds = Array.isArray(matched.read_by_user_ids) ? matched.read_by_user_ids : [];
    changed = true;
    return { ...message, is_read: readIds.includes(currentAdminId.value), is_read_by_partner: otherId ? readIds.includes(otherId) : message.is_read_by_partner };
  });
  if (changed) nextTick(scrollToBottom);
}
async function fetchContacts() {
  loadingContacts.value = true;
  try {
    const res = await MessageService.fetchContacts(keyword.value);
    contacts.value = res?.contacts || [];
    sortContacts();
    window.dispatchEvent(new CustomEvent("admin-contacts-updated", { detail: { contacts: contacts.value } }));
  } catch {
    contacts.value = [];
  } finally {
    loadingContacts.value = false;
  }
}
async function loadMessages(conversationId) {
  if (!conversationId) {
    messages.value = [];
    return;
  }
  loadingMessages.value = true;
  error.value = "";
  try {
    const res = await MessageService.fetchMessages(conversationId);
    messages.value = (res?.messages || []).map(mapMessage);
    contacts.value = contacts.value.map((contact) => String(contact.conversation_id || "") === String(conversationId) ? { ...contact, unread: 0 } : contact);
    sortContacts();
    window.dispatchEvent(new CustomEvent("messages-read", { detail: { conversationId } }));
    await nextTick();
    scrollToBottom();
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Không tải được cuộc trò chuyện.";
  } finally {
    loadingMessages.value = false;
  }
}
async function syncConversationRead(conversationId) {
  if (!conversationId || syncingRead.value) return;
  syncingRead.value = true;
  try {
    const res = await MessageService.fetchMessages(conversationId);
    messages.value = (res?.messages || []).map(mapMessage);
    contacts.value = contacts.value.map((contact) => String(contact.conversation_id || "") === String(conversationId) ? { ...contact, unread: 0 } : contact);
    sortContacts();
    window.dispatchEvent(new CustomEvent("messages-read", { detail: { conversationId } }));
  } catch {
    // best effort
  } finally {
    syncingRead.value = false;
  }
}
async function selectConversation(conversationId, contact = null, replace = false) {
  if (!conversationId) return;
  activeConversationId.value = conversationId;
  if (String(route.params.id || "") !== String(conversationId)) {
    await (replace ? router.replace : router.push)({ name: "messages.chat", params: { id: conversationId } });
  }
  if (contact) {
    const idx = contacts.value.findIndex((item) => String(item.conversation_id || "") === String(conversationId));
    if (idx !== -1) contacts.value[idx] = { ...contacts.value[idx], unread: 0 };
  }
  await loadMessages(conversationId);
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
  const mapped = files.map((file, idx) => ({ id: `${Date.now()}-${idx}`, name: file.name, type: detectType(file), file, previewUrl: URL.createObjectURL(file) }));
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
      attachments: pendingFiles.value.map((f) => ({ id: f.id, name: f.name, url: f.previewUrl, type: f.type })),
      status: "sending",
    };
    messages.value.push(optimisticMessage);
    updateContactFromEvent({
      conversation_id: activeConversationId.value,
      content: optimisticMessage.text,
      created_at: optimisticMessage.time,
      type: optimisticMessage.attachments.length ? "media" : "text",
      user_id: currentAdminId.value,
    });
    const res = await MessageService.sendMessage(activeConversationId.value, {
      content: draft.value.trim(),
      files: pendingFiles.value.map((f) => f.file),
    });
    if (res?.data) {
      const idx = messages.value.findIndex((m) => m.id === optimisticId);
      if (idx !== -1) messages.value[idx] = mapMessage(res.data);
      else messages.value.push(mapMessage(res.data));
      updateContactFromEvent({ ...res.data, conversation_id: activeConversationId.value });
    }
    draft.value = "";
    pendingFiles.value.forEach((f) => f.previewUrl && URL.revokeObjectURL(f.previewUrl));
    pendingFiles.value = [];
    await nextTick();
    scrollToBottom();
  } catch (e) {
    messages.value = messages.value.filter((m) => m.status !== "sending");
    error.value = e?.response?.data?.message || e?.message || "Không gửi được tin nhắn. Vui lòng thử lại.";
  } finally {
    sending.value = false;
  }
}
async function recall(message) {
  if (!currentContact.value || message.sender !== "me" || message.type === "recalled") return;
  openMenuId.value = null;
  upsertMessage({ ...message, text: null, attachments: [], type: "recalled" });
  updateContactFromEvent({ conversation_id: activeConversationId.value, content: null, created_at: message.time, type: "recalled", user_id: currentAdminId.value });
  try {
    await MessageService.recallMessage(activeConversationId.value, message.id);
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Không thu hồi được tin nhắn.";
  }
}
function toggleMenu(id) { openMenuId.value = openMenuId.value === id ? null : id; }
function stopRealtime() {
  if (channelName.value && window.Echo) window.Echo.leave(channelName.value);
  echoChannel = null;
}
function handleIncomingMessage(event) {
  if (!event?.conversation_id) return;
  if (event.user_id === currentAdminId.value) {
    messages.value = messages.value.filter((m) => m.status !== "sending");
    return;
  }
  updateContactFromEvent(event);
  if (String(event.conversation_id) !== String(activeConversationId.value || "")) return;
  const normalized = mapIncoming(event);
  upsertMessage(normalized);
  nextTick(scrollToBottom);
  syncConversationRead(activeConversationId.value);
}
function startRealtime() {
  if (!channelName.value || !window.Echo) return;
  stopRealtime();
  echoChannel = window.Echo.private(channelName.value).listen(".MessageSent", handleIncomingMessage).listen(".MessageReadUpdated", applyReadUpdates);
}
function removePending(id) {
  const target = pendingFiles.value.find((f) => f.id === id);
  if (target?.previewUrl) URL.revokeObjectURL(target.previewUrl);
  pendingFiles.value = pendingFiles.value.filter((f) => f.id !== id);
}
function scrollToBottom() {
  const el = scrollBody.value;
  if (!el) return;
  requestAnimationFrame(() => { el.scrollTop = el.scrollHeight; });
}
async function bootstrap() {
  await fetchContacts();
  const routeConversationId = route.params.id || null;
  const firstConversationId = routeConversationId || filteredContacts.value[0]?.conversation_id || null;
  if (firstConversationId) await selectConversation(firstConversationId, null, true);
  else {
    activeConversationId.value = null;
    messages.value = [];
  }
  startRealtime();
}

onMounted(bootstrap);
onBeforeUnmount(stopRealtime);
watch(
  () => route.params.id,
  async (nextId) => {
    if (!nextId) {
      activeConversationId.value = null;
      messages.value = [];
      return;
    }
    if (String(activeConversationId.value || "") === String(nextId)) return;
    const target = contacts.value.find((contact) => String(contact.conversation_id || "") === String(nextId));
    if (target) {
      await selectConversation(target.conversation_id, target, true);
      return;
    }
    await fetchContacts();
    const fallback = contacts.value.find((contact) => String(contact.conversation_id || "") === String(nextId));
    if (fallback) await selectConversation(fallback.conversation_id, fallback, true);
  },
);
</script>

<style scoped>
.page-wrap { padding: 12px; min-height: calc(100vh - 96px); display: flex; }
.inbox-grid { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 18px; width: 100%; height: calc(100vh - 128px); min-height: 0; align-items: stretch; }
.inbox-sidebar, .chat-shell { border-radius: 18px; border: 1px solid var(--border-color); background: var(--main-extra-bg); overflow: hidden; min-height: 0; }
.inbox-sidebar { display: flex; flex-direction: column; height: 100%; overflow-y: auto; }
.conversation-item { width: 100%; display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border: none; border-bottom: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); background: transparent; text-align: left; min-height: 76px; }
.conversation-item.active, .conversation-item:hover { background: color-mix(in srgb, var(--primary) 8%, transparent); }
.avatar-box, .header-avatar { width: 44px; height: 44px; min-width: 44px; min-height: 44px; border-radius: 14px; display: grid; place-items: center; background: color-mix(in srgb, var(--border-color) 28%, transparent); color: var(--primary); overflow: hidden; flex-shrink: 0; }
.avatar-box img, .header-avatar img { display: block; width: 100%; height: 100%; object-fit: cover; }
.conversation-copy { min-width: 0; flex: 1; }
.chat-shell { height: 100%; display: flex; flex-direction: column; }
.chat-header { padding: 14px 16px; border-bottom: 1px solid var(--border-color); background: color-mix(in srgb, var(--primary) 12%, var(--main-extra-bg)); }
.chat-body { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 18px; background: #fff; }
.empty-state { margin-top: 40px; }
.time-chip { margin: 8px auto; padding: 6px 12px; border-radius: 20px; background: color-mix(in srgb, var(--border-color) 30%, transparent); width: fit-content; }
.message-row { display: flex; margin-bottom: 12px; }
.message-row.mine { justify-content: flex-end; }
.message-bubble { position: relative; max-width: 78%; padding: 12px 14px; border-radius: 18px; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); background: #fff; box-shadow: 0 6px 18px color-mix(in srgb, #000 8%, transparent); }
.message-row.mine .message-bubble { background: #ffddba; border-color: color-mix(in srgb, #f59e0b 35%, #ffddba); }
.message-bubble.recalled { font-style: italic; color: #9ca3af; background: #f9fafb; border-style: dashed; }
.bubble-text { white-space: pre-line; line-height: 1.45; margin-bottom: 8px; }
.msg-actions { position: absolute; top: 8px; left: -38px; }
.msg-actions.left .dropdown-menu { left: 0; top: 28px; }
.icon-btn { border: 1px solid color-mix(in srgb, var(--border-color) 50%, transparent); background: #ffddba; border-radius: 10px; padding: 4px 6px; color: var(--font-color); }
.dropdown-menu { position: absolute; min-width: 120px; background: #ffffff; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); border-radius: 10px; padding: 6px 0; box-shadow: 0 10px 24px color-mix(in srgb, #000 10%, transparent); z-index: 5; }
.dropdown-item { width: 100%; text-align: left; padding: 8px 12px; background: none; border: none; color: var(--font-color); }
.dropdown-item:hover { background: var(--hover-background-color); }
.attach-grid { display: flex; gap: 10px; margin-bottom: 6px; flex-wrap: wrap; }
.attach-card { border: 1px dashed var(--border-color); border-radius: 12px; padding: 8px 10px; background: var(--hover-background-color); display: inline-flex; align-items: center; gap: 8px; color: inherit; text-decoration: none; min-width: 120px; }
.attach-media { width: 72px; height: 72px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: color-mix(in srgb, var(--border-color) 40%, transparent); display: grid; place-items: center; }
.attach-image, .attach-video { width: 100%; height: 100%; object-fit: cover; }
.attach-thumb { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; color: var(--font-color); background: color-mix(in srgb, var(--border-color) 20%, transparent); font-size: 18px; }
.attach-thumb.image { background: color-mix(in srgb, var(--primary) 10%, transparent); }
.attach-thumb.video { background: color-mix(in srgb, var(--warning) 14%, transparent); }
.attach-thumb.pdf { background: color-mix(in srgb, #ef4444 14%, transparent); }
.attach-thumb.excel { background: color-mix(in srgb, #16a34a 14%, transparent); }
.suggested-products { margin-top: 12px; display: grid; gap: 10px; }
.suggested-card { display: grid; grid-template-columns: 108px minmax(0, 1fr); gap: 10px; align-items: start; padding: 10px; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); border-radius: 14px; background: color-mix(in srgb, #fff 84%, #f8fafc); }
.suggested-thumb { width: 108px; height: 108px; border-radius: 12px; overflow: hidden; background: #fff; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); display: grid; place-items: center; text-decoration: none; }
.suggested-image { width: 100%; height: 100%; object-fit: contain; background: #fff; }
.suggested-fallback { color: #94a3b8; font-size: 1.2rem; }
.suggested-copy { min-width: 0; }
.suggested-name { color: var(--font-color); font-weight: 700; text-decoration: none; display: block; line-height: 1.4; }
.suggested-name:hover { text-decoration: underline; }
.suggested-price { margin-top: 6px; font-weight: 700; color: #b45309; }
.chat-input { flex-shrink: 0; padding: 14px 16px; border-top: 1px solid var(--border-color); background: var(--main-extra-bg); }
.chat-input .form-control { background: #ffffff; border: 1px solid color-mix(in srgb, var(--border-color) 70%, transparent); resize: none; }
.chat-input.disabled { opacity: 0.6; pointer-events: none; }
.pending-files { display: flex; flex-wrap: wrap; gap: 8px; }
.pending-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 12px; background: color-mix(in srgb, var(--border-color) 35%, transparent); border: 1px solid var(--border-color); max-width: 240px; }
.pending-thumb { width: 40px; height: 40px; border-radius: 10px; overflow: hidden; flex-shrink: 0; }
.pending-thumb img, .pending-thumb video { width: 100%; height: 100%; object-fit: cover; }
.pending-thumb.video { background: color-mix(in srgb, var(--border-color) 40%, transparent); }
.pending-pill .btn-link { line-height: 1; }
@media (max-width: 992px) {
  .page-wrap { min-height: auto; }
  .inbox-grid { grid-template-columns: 1fr; height: auto; }
  .inbox-sidebar { max-height: 280px; }
  .chat-shell { height: calc(100vh - 220px); }
}
@media (max-width: 768px) {
  .message-bubble { max-width: 100%; }
  .attach-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
  .suggested-card { grid-template-columns: 88px minmax(0, 1fr); }
  .suggested-thumb { width: 88px; height: 88px; }
  .avatar-box, .header-avatar { width: 40px; height: 40px; min-width: 40px; min-height: 40px; border-radius: 12px; }
}
</style>
