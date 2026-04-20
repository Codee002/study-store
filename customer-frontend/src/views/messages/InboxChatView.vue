<template>
  <div>
    <main class="container py-4">
      <div class="inbox-grid">
        <aside class="card card-soft shadow-sm inbox-sidebar">
          <div class="p-3 border-bottom">
            <h5 class="mb-1">Hộp thư</h5>
            <div class="small text-muted">Chọn Admin hoặc Trợ lý AI.</div>
          </div>

          <div v-if="loadingInbox" class="p-4 text-center text-muted">Đang tải hội thoại...</div>
          <div v-else-if="!conversations.length" class="p-4 text-center text-muted">Chưa có hội thoại.</div>

          <button
            v-for="item in conversations"
            :key="item.id"
            class="conversation-item"
            :class="{ active: String(item.id) === String(activeConversation?.id || '') }"
            @click="selectConversation(item.id)"
          >
            <div class="avatar-box" :class="{ bot: item.kind === 'chatbox_advice' }">
              <img v-if="item.partner?.avatar" :src="item.partner.avatar" alt="avatar" />
              <i v-else :class="item.kind === 'chatbox_advice' ? 'fa-solid fa-robot' : 'fa-regular fa-user'"></i>
            </div>
            <div class="conversation-copy">
              <div class="d-flex justify-content-between gap-2">
                <strong class="text-truncate">{{ item.partner?.name || item.name }}</strong>
                <small class="text-muted">{{ formatShortTime(item.updated_at) }}</small>
              </div>
              <div class="small text-muted">
                {{ item.kind === "chatbox_advice" ? "Tư vấn sản phẩm" : "Hỗ trợ khách hàng" }}
              </div>
              <div class="small text-truncate text-muted">{{ item.last_message || "Chưa có tin nhắn" }}</div>
            </div>
            <span v-if="item.unread" class="badge rounded-pill bg-danger">{{ item.unread }}</span>
          </button>
        </aside>

        <section class="card card-soft shadow-sm chat-shell">
          <header class="chat-header d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
              <div class="header-avatar" :class="{ bot: activeConversation?.kind === 'chatbox_advice' }">
                <img v-if="activeConversation?.partner?.avatar" :src="activeConversation.partner.avatar" alt="avatar" />
                <i v-else :class="activeConversation?.kind === 'chatbox_advice' ? 'fa-solid fa-robot' : 'fa-regular fa-user'"></i>
              </div>
              <div>
                <h5 class="mb-0">{{ activeConversation?.partner?.name || "Hộp chat" }}</h5>
                <div class="small text-muted">
                  {{ activeConversation?.kind === "chatbox_advice" ? "Trợ lý AI dùng dữ liệu sản phẩm trong hệ thống" : "Trao đổi trực tiếp với admin" }}
                </div>
              </div>
            </div>
            <RouterLink to="/home" class="btn btn-outline-secondary btn-sm">Trang chủ</RouterLink>
          </header>

          <div class="chat-body" ref="scrollBody">
            <div v-if="loadingMessages" class="p-4 text-center text-muted">Đang tải tin nhắn...</div>
            <div v-else-if="!orderedMessages.length" class="p-4 text-center text-muted">
              {{ activeConversation?.kind === "chatbox_advice" ? "Hãy hỏi nhu cầu sản phẩm để AI tư vấn." : "Chưa có tin nhắn." }}
            </div>

            <template v-else>
              <div v-for="(message, index) in orderedMessages" :key="message.id">
                <div v-if="shouldShowTime(index)" class="time-chip">
                  {{ formatGapTime(message.time) }}
                </div>

                <div class="message-row" :class="{ mine: message.sender === 'me' }">
                <div class="message-bubble" :class="{ bot: activeConversation?.kind === 'chatbox_advice' && message.sender === 'them', recalled: message.type === 'recalled' }">
                  <div
                    v-if="message.sender === 'me' && message.type !== 'recalled'"
                    class="msg-actions left"
                  >
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
                      <div class="suggested-toolbar">
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-secondary"
                          @click="toggleAllSuggestedProducts(message)"
                        >
                          <i
                            class="fa-regular"
                            :class="allSuggestedProductsSelected(message) ? 'fa-square-minus' : 'fa-square-check'"
                          ></i>
                          {{ allSuggestedProductsSelected(message) ? "Bỏ chọn tất cả" : "Chọn tất cả" }}
                        </button>
                      </div>
                      <div v-for="product in message.products" :key="product.id" class="suggested-card">
                        <label class="suggested-check">
                          <input
                            type="checkbox"
                          :checked="isSuggestedProductSelected(message.id, product.id)"
                          @change="toggleSuggestedProduct(message.id, product.id, $event.target.checked)"
                        />
                      </label>
                      <RouterLink class="suggested-thumb" :to="product.url">
                        <img v-if="product.image" :src="product.image" :alt="product.name" class="suggested-image" />
                        <div v-else class="suggested-fallback">
                          <i class="fa-regular fa-image"></i>
                        </div>
                      </RouterLink>
                      <div class="suggested-copy">
                        <RouterLink class="suggested-name" :to="product.url">{{ product.name }}</RouterLink>
                        <div class="small text-muted">{{ product.category }}</div>
                        <div v-if="product.price != null" class="suggested-price">
                          {{ Number(product.price).toLocaleString("vi-VN") }} đ
                        </div>
                        <div v-if="product.colors?.length" class="suggested-variant mt-2">
                          <label class="small text-muted d-block mb-1">Phân loại</label>
                          <select
                            class="form-select form-select-sm"
                            :value="selectedSuggestedColor(message.id, product) ?? ''"
                            @change="setSuggestedColor(message.id, product.id, $event.target.value)"
                          >
                            <option
                              v-for="color in product.colors"
                              :key="color.id"
                              :value="color.id"
                            >
                              {{ color.color_name }}
                            </option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="suggested-actions">
                      <button type="button" class="btn btn-sm btn-main" @click="addSelectedProductsToCart(message)">
                        <i class="fa-solid fa-cart-plus me-1"></i>Thêm vào giỏ
                      </button>
                    </div>
                  </div>
                  <div class="small opacity-75 mt-2 d-flex gap-2 justify-content-end">
                    <span>{{ formatTime(message.time) }}</span>
                    <span
                      v-if="activeConversation?.kind === 'admin_support' && message.sender === 'me' && message.id === lastOwnMessageId && message.is_read_by_partner"
                    >
                      Đã xem
                    </span>
                  </div>
                </div>
              </div>
              </div>
              <div v-if="isAiTyping" class="message-row">
                <div class="message-bubble bot typing-bubble">
                  <div class="typing-label">Trợ lý AI đang soạn tin nhắn...</div>
                </div>
              </div>
            </template>
          </div>

          <footer class="chat-input">


            <div class="input-group">
              <button class="btn btn-outline-secondary" type="button" @click="filePicker?.click()">Thêm file</button>
              <input ref="filePicker" class="d-none" type="file" multiple accept="image/*,video/*,.pdf,.xlsx,.doc,.docx" @change="onFilesSelected" />
              <textarea
                v-model="draft"
                class="form-control"
                rows="1"
                :placeholder="activeConversation?.kind === 'chatbox_advice' ? 'Ví dụ: Tôi cần bút để làm nổi bật văn bản' : 'Viết tin nhắn...'"
                @keydown.enter.exact.prevent="send"
              ></textarea>
              <button class="btn btn-main" type="button" :disabled="!canSend || sending || !activeConversation" @click="send">
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
                    'fa-regular fa-file-lines': file.type === 'file'
                  }"
                ></i>
                <span class="text-truncate">{{ file.name }}</span>
                <button type="button" class="btn btn-link btn-sm p-0 text-danger text-decoration-none" @click="removePending(file.id)">x</button>
              </div>
            </div>
          </footer>
        </section>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import dayjs from "dayjs";
import Swal from "sweetalert2";
import AppFooter from "@/components/layout/AppFooter.vue";
import MessageService from "@/services/message.service";
import cartService from "@/services/cart.service";
import { useCustomerHeaderState } from "@/composables/useCustomerHeaderState";

const route = useRoute();
const router = useRouter();
const headerStore = useCustomerHeaderState();
const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const loadingInbox = ref(true);
const loadingMessages = ref(false);
const error = ref("");
const draft = ref("");
const sending = ref(false);
const aiTypingConversationId = ref(null);
const pendingFiles = ref([]);
const filePicker = ref(null);
const scrollBody = ref(null);
const selectedProducts = ref({});
const currentUserId = computed(() => Number(headerStore.state.user?.id || JSON.parse(localStorage.getItem("currentUser") || "{}")?.id || 0));
const orderedMessages = computed(() => [...messages.value].sort((a, b) => new Date(a.time) - new Date(b.time)));
const canSend = computed(() => draft.value.trim() || pendingFiles.value.length);
const lastOwnMessageId = computed(() => [...orderedMessages.value].reverse().find((item) => item.sender === "me")?.id || null);
const isAiTyping = computed(() => (
  activeConversation.value?.kind === "chatbox_advice"
  && String(aiTypingConversationId.value || "") === String(activeConversation.value?.id || "")
));
let echoChannel = null;
const openMenuId = ref(null);

function formatTime(value) { return dayjs(value).format("HH:mm"); }
function formatShortTime(value) { return value ? (dayjs(value).isSame(dayjs(), "day") ? dayjs(value).format("HH:mm") : dayjs(value).format("DD/MM")) : ""; }
function formatGapTime(value) { return dayjs(value).format("DD/MM/YYYY HH:mm"); }
function shouldShowTime(index) {
  if (index === 0) return true;
  const prev = orderedMessages.value[index - 1];
  const current = orderedMessages.value[index];
  return dayjs(current.time).diff(dayjs(prev.time), "minute") >= 15;
}
function detectType(file) {
  if ((file.type || "").startsWith("image")) return "image";
  if ((file.type || "").startsWith("video")) return "video";
  if ((file.name || "").toLowerCase().endsWith(".pdf")) return "pdf";
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
  } catch {
    return null;
  }
}
function mapMedia(media) {
  const type = media.type || detectTypeByName(media.name) || detectTypeByUrl(media.url);
  return { id: media.id, name: media.name || "Tệp tin", url: media.url, type: type || "file" };
}
function mapMessage(raw) {
  const readIds = raw.read_by_user_ids || [];
  const partnerId = Number(activeConversation.value?.partner?.id || 0);
  return { id: raw.id, user_id: raw.user_id, sender: raw.user_id === currentUserId.value ? "me" : "them", text: raw.content, time: raw.created_at, type: raw.type, is_read_by_partner: partnerId ? readIds.includes(partnerId) : false, attachments: (raw.medias || []).map(mapMedia), products: raw.products || [] };
}
function normalizeConversation(item) { return { ...item, partner: item.partner || item.admin || item.bot || null, unread: Number(item.unread || 0) }; }
function preferredConversation() {
  const byRoute = conversations.value.find((item) => String(item.id) === String(route.params.id || ""));
  if (byRoute) return byRoute;
  return conversations.value.find((item) => item.kind === "chatbox_advice") || conversations.value[0] || null;
}
function sortConversations() {
  conversations.value = [...conversations.value].sort((a, b) => new Date(b.updated_at || 0) - new Date(a.updated_at || 0));
}
function updateConversationFromEvent(payload) {
  const idx = conversations.value.findIndex((item) => String(item.id) === String(payload.conversation_id || payload.id));
  if (idx === -1) return;
  const preview = payload.type === "media" ? "Đã gửi một tệp" : payload.type === "recalled" ? "Tin nhắn đã bị thu hồi" : (payload.content || conversations.value[idx].last_message);
  const current = conversations.value[idx];
  conversations.value[idx] = { ...current, last_message: preview, updated_at: payload.created_at || current.updated_at, unread: String(activeConversation.value?.id || "") === String(current.id) || payload.user_id === currentUserId.value ? 0 : Number(current.unread || 0) + 1 };
  sortConversations();
  if (String(activeConversation.value?.id || "") === String(current.id)) activeConversation.value = conversations.value.find((item) => String(item.id) === String(current.id)) || activeConversation.value;
}
async function fetchInbox() {
  loadingInbox.value = true;
  const res = await MessageService.fetchInbox();
  conversations.value = (res?.conversations || []).map(normalizeConversation);
  sortConversations();
  const first = preferredConversation();
  if (first) await selectConversation(first.id, true);
  loadingInbox.value = false;
}
async function loadMessages(id) {
  if (!id) return;
  loadingMessages.value = true;
  const res = await MessageService.fetchMessages(id);
  if (res?.conversation?.id) {
    const idx = conversations.value.findIndex((item) => String(item.id) === String(res.conversation.id));
    if (idx !== -1) conversations.value[idx] = normalizeConversation({ ...conversations.value[idx], ...res.conversation });
    activeConversation.value = conversations.value.find((item) => String(item.id) === String(id)) || activeConversation.value;
  }
  messages.value = (res?.messages || []).map(mapMessage);
  const idx = conversations.value.findIndex((item) => String(item.id) === String(id));
  if (idx !== -1) conversations.value[idx] = { ...conversations.value[idx], unread: 0 };
  if (activeConversation.value?.kind === "admin_support") headerStore.markMessagesSeen();
  loadingMessages.value = false;
  await scrollToLatest();
}
async function selectConversation(id, replace = false) {
  const target = conversations.value.find((item) => String(item.id) === String(id));
  if (!target) return;
  activeConversation.value = target;
  if (String(route.params.id || "") !== String(id)) {
    await (replace ? router.replace : router.push)({ name: "contact.chat", params: { id } });
  }
  await loadMessages(id);
}
function onFilesSelected(event) {
  pendingFiles.value = [...pendingFiles.value, ...Array.from(event.target.files || []).map((file, index) => ({ id: `${Date.now()}-${index}`, name: file.name, file, type: detectType(file), previewUrl: URL.createObjectURL(file) }))];
  event.target.value = "";
}
function removePending(id) {
  const found = pendingFiles.value.find((item) => item.id === id);
  if (found?.previewUrl) URL.revokeObjectURL(found.previewUrl);
  pendingFiles.value = pendingFiles.value.filter((item) => item.id !== id);
}
async function scrollToLatest() {
  await nextTick();
  await new Promise((resolve) => requestAnimationFrame(resolve));
  if (scrollBody.value) {
    scrollBody.value.scrollTop = scrollBody.value.scrollHeight;
  }
}
async function send() {
  if (!canSend.value || !activeConversation.value || sending.value) return;
  sending.value = true;
  error.value = "";
  let content = "";
  let optimisticFiles = [];
  const conversationId = activeConversation.value.id;
  const isChatboxConversation = activeConversation.value.kind === "chatbox_advice";
  try {
    content = draft.value.trim();
    optimisticFiles = [...pendingFiles.value];
    if (isChatboxConversation) {
      aiTypingConversationId.value = conversationId;
    }
    draft.value = "";
    pendingFiles.value = [];

    const optimistic = { id: `local-${Date.now()}`, sender: "me", text: content, time: new Date().toISOString(), type: optimisticFiles.length ? "media" : "text", attachments: optimisticFiles.map((item) => ({ id: item.id, name: item.name, url: item.previewUrl, type: item.type })), is_read_by_partner: false };
    messages.value.push(optimistic);
    updateConversationFromEvent({ conversation_id: conversationId, content: optimistic.text, created_at: optimistic.time, type: optimistic.type, user_id: currentUserId.value });
    const res = await MessageService.sendMessage(conversationId, { content, files: optimisticFiles.map((item) => item.file) });
    messages.value = messages.value.filter((item) => item.id !== optimistic.id);
    if (res?.data) {
      const mapped = mapMessage(res.data);
      const existingIdx = messages.value.findIndex((item) => String(item.id) === String(mapped.id));
      if (existingIdx === -1) {
        messages.value.push(mapped);
      } else {
        messages.value[existingIdx] = mapped;
      }
      updateConversationFromEvent({ ...res.data, conversation_id: conversationId });
    }
    await scrollToLatest();
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Gửi tin nhắn thất bại.";
    messages.value = messages.value.filter((item) => !String(item.id).startsWith("local-"));
    if (String(aiTypingConversationId.value || "") === String(conversationId || "")) {
      aiTypingConversationId.value = null;
    }
    if (!draft.value) {
      draft.value = content;
    }
    if (!pendingFiles.value.length) {
      pendingFiles.value = optimisticFiles;
    }
  } finally {
    sending.value = false;
  }
}
async function recall(message) {
  if (!activeConversation.value || message.sender !== "me" || message.type === "recalled") return;
  openMenuId.value = null;
  try { await MessageService.recallMessage(activeConversation.value.id, message.id); } catch (e) { error.value = e?.response?.data?.message || e?.message || "Thu hồi tin nhắn thất bại."; }
}
function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}
function toggleSuggestedProduct(messageId, productId, checked) {
  selectedProducts.value = {
    ...selectedProducts.value,
    [`${messageId}:${productId}`]: checked,
  };
}
function isSuggestedProductSelected(messageId, productId) {
  return Boolean(selectedProducts.value[`${messageId}:${productId}`]);
}
function allSuggestedProductsSelected(message) {
  const products = Array.isArray(message?.products) ? message.products : [];
  return products.length > 0 && products.every((product) => isSuggestedProductSelected(message.id, product.id));
}
function toggleAllSuggestedProducts(message) {
  const products = Array.isArray(message?.products) ? message.products : [];
  if (!products.length) return;
  const nextChecked = !allSuggestedProductsSelected(message);
  const nextSelected = { ...selectedProducts.value };
  products.forEach((product) => {
    nextSelected[`${message.id}:${product.id}`] = nextChecked;
  });
  selectedProducts.value = nextSelected;
}
function selectedSuggestedColor(messageId, product) {
  const colors = Array.isArray(product?.colors) ? product.colors : [];
  if (!colors.length) return null;
  const key = `${messageId}:${product.id}:color`;
  return selectedProducts.value[key] ?? colors[0]?.id ?? null;
}
function setSuggestedColor(messageId, productId, colorId) {
  selectedProducts.value = {
    ...selectedProducts.value,
    [`${messageId}:${productId}:color`]: colorId === "" ? null : Number(colorId),
  };
}
async function addSelectedProductsToCart(message) {
  const chosen = (message.products || []).filter((product) => isSuggestedProductSelected(message.id, product.id));
  if (!chosen.length) {
    error.value = "Hãy chọn ít nhất một sản phẩm trước khi thêm vào giỏ.";
    return;
  }
  try {
    let latestCart = null;
    for (const product of chosen) {
      const hasColors = Array.isArray(product?.colors) && product.colors.length > 0;
      const chosenColorId = selectedSuggestedColor(message.id, product);
      if (hasColors && (chosenColorId === null || chosenColorId === undefined || chosenColorId === "")) {
        error.value = `Hãy chọn phân loại cho sản phẩm ${product.name}.`;
        return;
      }
      const res = await cartService.addItem({
        product_id: product.id,
        color_id: hasColors ? Number(chosenColorId) : null,
        quantity: 1,
      });
      latestCart = res?.cart || latestCart;
    }
    const count = cartService.getCountFromItems(latestCart?.items || []);
    window.dispatchEvent(new CustomEvent("cart-updated", { detail: { count } }));
    error.value = "";
    await Swal.fire({
      icon: "success",
      title: "Thành công",
      text: chosen.length > 1
        ? "Đã thêm các sản phẩm đã chọn vào giỏ hàng."
        : "Đã thêm sản phẩm vào giỏ hàng.",
      timer: 1800,
      showConfirmButton: false,
    });
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Không thể thêm sản phẩm vào giỏ hàng.";
  }
}
function onMessageSent(event) {
  if (!event?.conversation_id) return;
  updateConversationFromEvent(event);
  if (
    String(event.conversation_id) === String(aiTypingConversationId.value || "")
    && Number(event.user_id) !== currentUserId.value
  ) {
    aiTypingConversationId.value = null;
  }
  if (String(event.conversation_id) !== String(activeConversation.value?.id || "")) return;
  messages.value = messages.value.filter((item) => !String(item.id).startsWith("local-"));
  const mapped = mapMessage(event);
  const idx = messages.value.findIndex((item) => String(item.id) === String(mapped.id));
  if (idx === -1) messages.value.push(mapped); else messages.value[idx] = mapped;
  if (activeConversation.value?.kind === "admin_support" && mapped.sender === "them") {
    headerStore.markMessagesSeen();
  }
  scrollToLatest();
}
function onReadUpdated(event) {
  if (String(event?.conversation_id || "") !== String(activeConversation.value?.id || "")) return;
  const partnerId = Number(activeConversation.value?.partner?.id || 0);
  messages.value = messages.value.map((item) => {
    const found = (event.messages || []).find((entry) => String(entry.id) === String(item.id));
    if (!found) return item;
    return { ...item, is_read_by_partner: partnerId ? (found.read_by_user_ids || []).includes(partnerId) : item.is_read_by_partner };
  });
}

onMounted(async () => {
  try {
    headerStore.initHeaderState();
    await fetchInbox();
    if (window.Echo && currentUserId.value) {
      echoChannel = window.Echo.private(`user.${currentUserId.value}`)
        .listen(".MessageSent", onMessageSent)
        .listen(".MessageReadUpdated", onReadUpdated);
    }
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Không tải được hộp thư.";
  } finally {
    loadingInbox.value = false;
  }
});

onBeforeUnmount(() => {
  if (window.Echo && currentUserId.value) window.Echo.leave(`user.${currentUserId.value}`);
  pendingFiles.value.forEach((item) => {
    if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
  });
});

watch(() => route.params.id, async (nextId) => {
  const target = conversations.value.find((item) => String(item.id) === String(nextId || ""));
  if (target && String(activeConversation.value?.id || "") !== String(target.id)) {
    await selectConversation(target.id, true);
  }
});

watch(isAiTyping, async (typing) => {
  if (typing) {
    await scrollToLatest();
  }
});

watch(error, async (message) => {
  if (!message) return;
  await Swal.fire({
    icon: "error",
    title: "Lỗi",
    text: message,
  });
  error.value = "";
});
</script>

<style scoped>
main.container {
  min-height: calc(100vh - 96px);
  display: flex;
}

.inbox-grid {
  display: grid;
  grid-template-columns: 320px minmax(0, 1fr);
  gap: 18px;
  width: 100%;
  height: calc(100vh - 128px);
  min-height: 0;
  align-items: stretch;
}

.inbox-sidebar,
.chat-shell {
  border-radius: 18px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  overflow: hidden;
  min-height: 0;
}

.inbox-sidebar {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow-y: auto;
}

.conversation-item { width: 100%; display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border: none; border-bottom: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); background: transparent; text-align: left; }
.conversation-item.active, .conversation-item:hover { background: color-mix(in srgb, var(--primary) 8%, transparent); }
.avatar-box, .header-avatar { width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; background: color-mix(in srgb, var(--border-color) 28%, transparent); color: var(--primary); overflow: hidden; flex-shrink: 0; }
.avatar-box.bot, .header-avatar.bot { background: color-mix(in srgb, #0ea5e9 16%, transparent); color: #0369a1; }
.avatar-box img, .header-avatar img { width: 100%; height: 100%; object-fit: cover; }
.conversation-copy { min-width: 0; flex: 1; }
.chat-shell { height: 100%; display: flex; flex-direction: column; }
.chat-header { padding: 14px 16px; border-bottom: 1px solid var(--border-color); background: color-mix(in srgb, var(--primary) 12%, var(--main-extra-bg)); }
.chat-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 16px 18px; background: #fff; }
.time-chip { margin: 8px auto; padding: 6px 12px; border-radius: 20px; background: color-mix(in srgb, var(--border-color) 30%, transparent); width: fit-content; }
.message-row { display: flex; margin-bottom: 12px; }
.message-row.mine { justify-content: flex-end; }
.message-bubble { max-width: 78%; padding: 12px 14px; border-radius: 18px; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); background: #fff; box-shadow: 0 6px 18px color-mix(in srgb, #000 8%, transparent); position: relative; }
.message-row.mine .message-bubble { background: #ffddba; border-color: color-mix(in srgb, #f59e0b 35%, #ffddba); }
.message-bubble.bot { background: color-mix(in srgb, #dbeafe 58%, #fff); border-color: color-mix(in srgb, #60a5fa 32%, #dbeafe); }
.message-bubble.recalled { font-style: italic; color: #9ca3af; background: #f9fafb; border-style: dashed; }
.typing-bubble { display: inline-flex; align-items: center; min-width: 220px; }
.typing-label { color: #475569; font-style: italic; }
.bubble-text { white-space: pre-line; line-height: 1.5; }
.msg-actions { position: absolute; top: 8px; left: -38px; right: auto; }
.msg-actions.left .dropdown-menu { left: 0; right: auto; top: 28px; }
.icon-btn { border: 1px solid color-mix(in srgb, var(--border-color) 50%, transparent); background: #ffddba; border-radius: 10px; padding: 4px 6px; color: var(--font-color); }
.dropdown-menu { position: absolute; min-width: 120px; background: #ffffff; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); border-radius: 10px; padding: 6px 0; box-shadow: 0 10px 24px color-mix(in srgb, #000 10%, transparent); z-index: 5; }
.dropdown-item { width: 100%; text-align: left; padding: 8px 12px; background: none; border: none; color: var(--font-color); }
.dropdown-item:hover { background: var(--hover-background-color); }
.attach-grid { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }
.attach-card { border: 1px dashed var(--border-color); border-radius: 12px; padding: 8px 10px; background: var(--hover-background-color); display: inline-flex; align-items: center; gap: 8px; color: inherit; text-decoration: none; min-width: 120px; }
.attach-media { width: 160px; height: 160px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: color-mix(in srgb, var(--border-color) 40%, transparent); display: grid; place-items: center; }
.attach-image, .attach-video { width: 100%; height: 100%; object-fit: contain; background: #fff; }
.attach-thumb { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: color-mix(in srgb, var(--border-color) 20%, transparent); font-size: 18px; }
.chat-input { flex-shrink: 0; }
.chat-input { padding: 14px 16px; border-top: 1px solid var(--border-color); background: var(--main-extra-bg); }
.chat-hint { margin-bottom: 10px; padding: 10px 12px; border-radius: 12px; background: color-mix(in srgb, #0ea5e9 10%, transparent); }
.pending-files { display: flex; flex-wrap: wrap; gap: 8px; }
.pending-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 12px; background: color-mix(in srgb, var(--border-color) 35%, transparent); border: 1px solid var(--border-color); max-width: 240px; }
.pending-thumb { width: 40px; height: 40px; border-radius: 10px; overflow: hidden; flex-shrink: 0; }
.pending-thumb img, .pending-thumb video { width: 100%; height: 100%; object-fit: cover; }
.suggested-products { margin-top: 12px; display: grid; gap: 10px; }
.suggested-toolbar { display: flex; justify-content: flex-end; }
.suggested-card { display: grid; grid-template-columns: auto 108px minmax(0, 1fr); gap: 10px; align-items: start; padding: 10px; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); border-radius: 14px; background: color-mix(in srgb, #fff 84%, #f8fafc); }
.suggested-check { padding-top: 4px; }
.suggested-thumb { width: 108px; height: 108px; border-radius: 12px; overflow: hidden; background: #fff; border: 1px solid color-mix(in srgb, var(--border-color) 60%, transparent); display: grid; place-items: center; text-decoration: none; }
.suggested-image { width: 100%; height: 100%; object-fit: contain; background: #fff; }
.suggested-fallback { color: #94a3b8; font-size: 1.2rem; }
.suggested-copy { min-width: 0; }
.suggested-name { color: var(--font-color); font-weight: 700; text-decoration: none; display: block; line-height: 1.4; }
.suggested-name:hover { text-decoration: underline; }
.suggested-price { margin-top: 6px; font-weight: 700; color: #b45309; }
.suggested-variant .form-select { background-color: #fff; }
.suggested-actions { display: flex; justify-content: flex-end; }
@media (max-width: 992px) {
  main.container { min-height: auto; }
  .inbox-grid { grid-template-columns: 1fr; height: auto; }
  .inbox-sidebar { max-height: 280px; }
  .chat-shell { height: calc(100vh - 220px); }
}
@media (max-width: 768px) {
  .message-bubble { max-width: 100%; }
  main.container { padding: 0 10px 18px; }
  .chat-shell { height: calc(100vh - 200px); }
  .attach-media { width: 120px; height: 120px; }
  .msg-actions { left: 6px; right: auto; }
  .suggested-card { grid-template-columns: auto 88px minmax(0, 1fr); }
  .suggested-thumb { width: 88px; height: 88px; }
}
</style>
