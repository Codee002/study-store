<template>
  <header class="app-header border-bottom">
    <div class="container h-100 d-flex align-items-center gap-3">
      <RouterLink to="/home" class="brand d-flex align-items-center gap-2 text-decoration-none">
        <span class="logo-circle">
          <i class="fa-solid fa-pencil"></i>
        </span>
        <div class="d-flex flex-column lh-sm">
          <span class="brand-name">Study Store</span>
          <span class="brand-sub">Văn phòng phẩm xinh</span>
        </div>
      </RouterLink>

      <nav class="d-none d-lg-flex ms-2 gap-2">
        <RouterLink class="nav-pill" to="/home">Trang chủ</RouterLink>
        <RouterLink class="nav-pill" to="/products">Sản phẩm</RouterLink>
        <RouterLink class="nav-pill" to="/orders">Đơn hàng</RouterLink>
        <RouterLink class="nav-pill" to="/price-quotation">Báo giá</RouterLink>
      </nav>

      <form class="ms-auto d-none d-md-block search-wrap" @submit.prevent="emitSearch">
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0">
            <i class="fa-solid fa-magnifying-glass"></i>
          </span>
          <input
            v-model.trim="keyword"
            class="form-control border-start-0"
            type="text"
            placeholder="Tìm bút, vở, sticker..."
          />
          <button class="btn btn-main d-none d-lg-inline-flex" type="submit">Tìm</button>
        </div>
      </form>

      <div class="d-flex align-items-center gap-2">
        <button class="icon-btn d-md-none" type="button" title="Tim kiem">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>

        <!-- Messages -->
        <div class="dropdown position-relative notification-dropdown" ref="messageRef">
          <button
            class="icon-btn position-relative"
            type="button"
            title="Tin nhắn"
            @click="toggleMessages"
          >
            <i class="fa-regular fa-message"></i>
            <span v-if="unreadMessages > 0" class="badge rounded-pill bg-danger badge-noti">
              {{ unreadMessages }}
            </span>
          </button>
          <div
            class="dropdown-menu dropdown-menu-end shadow-sm p-0 notification-menu"
            :class="{ show: isMessageOpen }"
          >
            <div class="notif-header d-flex align-items-center justify-content-between px-3 py-2">
              <span class="fw-semibold">Tin nhắn</span>
              <RouterLink to="/contact" class="btn btn-sm px-0" @click="closeMessages">Xem tất cả</RouterLink>
            </div>
            <div class="notif-list">
              <div v-if="msgLoading" class="p-3 small text-center text-muted">Đang tải...</div>
              <div v-else-if="!recentMessages.length" class="p-3 small text-center text-muted">Chưa có tin nhắn</div>
              <button
                v-else
                v-for="m in recentMessages"
                :key="m.id"
                class="notif-item w-100 text-start border-0 bg-transparent d-flex align-items-start gap-2 px-3 py-2"
                @click="goChat(m)"
              >
                <span class="dot" :class="{ unread: m.unread }"></span>
                <div class="flex-grow-1">
                  <div class="fw-semibold d-flex align-items-center gap-2">
                    {{ m.fromName || 'Tin nhắn' }}
                    <span v-if="m.unread" class="badge bg-danger-subtle text-danger rounded-pill">Mới</span>
                  </div>
                  <div class="small text-muted text-truncate">
                    {{ m.preview || 'Tin nhắn mới' }}
                  </div>
                </div>
                <div class="small text-muted">{{ formatTime(m.created_at) }}</div>
              </button>
            </div>
          </div>
        </div>

        <div class="dropdown position-relative notification-dropdown" ref="notificationRef">
          <button
            class="icon-btn position-relative"
            type="button"
            title="Thong bao"
            @click="toggleNotifications"
          >
            <i class="fa-regular fa-bell"></i>
            <span v-if="unreadCount > 0" class="badge rounded-pill bg-danger badge-noti">
              {{ unreadCount }}
            </span>
          </button>
          <div
            class="dropdown-menu dropdown-menu-end shadow-sm p-0 notification-menu"
            :class="{ show: isNotificationOpen }"
          >
            <div class="notif-header d-flex align-items-center justify-content-between px-3 py-2">
              <span class="fw-semibold">Thông báo</span>
              <button
                class="btn btn-sm px-0"
                type="button"
                :disabled="!notifications.length || markingAll"
                @click="markAllRead"
              >
                Đọc hết
              </button>
            </div>
            <div class="notif-list">
              <div v-if="isLoadingNoti" class="p-3 small text-center text-muted">
                Đang tải...
              </div>
              <div v-else-if="!hasNotifications" class="p-3 small text-center text-muted">
                Chưa có thông báo
              </div>
              <div v-else v-for="group in groupedNotifications" :key="group.key" class="notif-group">
                <div class="group-heading px-3 py-2 text-uppercase small fw-semibold text-muted">
                  {{ group.label }}
                </div>
                <button
                  v-for="item in group.items"
                  :key="item.id || `${group.key}-${item.url_id}`"
                  class="notif-item w-100 text-start border-0 bg-transparent d-flex align-items-start gap-2 px-3 py-2"
                  @click="openNotification(item)"
                >
                  <span class="dot" :class="{ unread: !item.read_at && !item.is_read }"></span>
                  <div class="flex-grow-1">
                    <div class="fw-semibold mb-1">
                      {{ item.title || fallbackTitle(item) }}
                    </div>
                    <div class="small text-muted text-wrap">
                      {{ item.content || item.body || item.message || 'Ban co mot thong bao moi' }}
                    </div>
                    <div class="small text-muted fst-italic">
                      {{ formatRelative(item.created_at) }}
                    </div>
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>

        <RouterLink
          to="/cart"
          class="icon-btn position-relative d-inline-flex align-items-center justify-content-center text-decoration-none"
          title="Gio hang"
        >
          <i class="fa-solid fa-cart-shopping"></i>
          <span class="badge rounded-pill bg-dark badge-cart">{{ cartCount }}</span>
        </RouterLink>

        <div class="dropdown">
          <button class="user-btn dropdown-toggle" data-bs-toggle="dropdown" type="button">
            <img class="avatar" :src="resolvedAvatar" alt="avatar" @click.stop="goAccountSettings" />
            <span class="d-none d-lg-inline" @click.stop="goAccountSettings">{{ localUser.name }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <RouterLink class="dropdown-item" to="/account/settings">
                <i class="fa-solid fa-user me-2"></i>Tài khoản
              </RouterLink>
            </li>
            <li>
              <RouterLink class="dropdown-item" to="/orders">
                <i class="fa-solid fa-receipt me-2"></i>Đơn hàng
              </RouterLink>
            </li>
            <li>
              <RouterLink class="dropdown-item" to="/price-quotation">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i>Báo giá
              </RouterLink>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
              <button class="dropdown-item" type="button" @click="onLogout">
                <i class="fa-solid fa-right-from-bracket me-2"></i>Đăng xuất
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";
import authService from "@/services/auth.service";
import notificationService from "@/services/notification.service";
import MessageService from "@/services/message.service";
import dayjs from "dayjs";
import { useRoute } from "vue-router";
import { watch } from "vue";

const router = useRouter();
const route = useRoute();

const props = defineProps({
  cartCount: { type: Number, default: 0 },
  user: {
    type: Object,
    default: () => ({
      name: "Khach",
      avatar: "/default-user-avatar.svg",
    }),
  },
});

const emit = defineEmits(["search"]);
const keyword = ref("");

const DEFAULT_USER = {
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
};
const HEADER_ME_SYNC_KEY = "customer_header_me_synced";

const localUser = ref(readStoredUser() || props.user || DEFAULT_USER);

function readStoredUser() {
  try {
    const raw = localStorage.getItem("currentUser");
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function normalizeUserPayload(res) {
  const me = res?.data ?? res;
  const meUser = me?.user ?? me ?? {};
  return {
    ...meUser,
    name: meUser?.name || "Guest",
    avatar:
      meUser?.avatar ||
      meUser?.profile?.avatar ||
      "/default-user-avatar.svg",
    tier_id: meUser?.tier_id ?? meUser?.profile?.tier ?? null,
    profile: meUser?.profile ?? null,
  };
}

const resolvedAvatar = computed(
  () =>
    localUser.value?.avatar ||
    localUser.value?.profile?.avatar ||
    props.user?.avatar ||
    props.user?.profile?.avatar ||
    "/default-user-avatar.svg",
);

const notifications = ref([]);
const recentMessages = ref([]);
const notificationsLoaded = ref(false);
const isNotificationOpen = ref(false);
const isMessageOpen = ref(false);
const isLoadingNoti = ref(false);
const msgLoading = ref(false);
const markingAll = ref(false);
const notificationRef = ref(null);
const messageRef = ref(null);
const toast = Swal.mixin({
  toast: true,
  position: "bottom-end",
  showConfirmButton: false,
  timer: 5000,
  timerProgressBar: true,
});
let subscribedChannel = null;
const unreadCount = computed(
  () => notifications.value.filter((n) => !n.read_at && !n.is_read).length,
);
const unreadMessages = ref(0);
const groupedNotifications = computed(() => {
  const map = {};
  notifications.value.forEach((item) => {
    const key = toDateKey(item?.created_at);
    if (!map[key]) map[key] = [];
    map[key].push(item);
  });
  return Object.entries(map)
    .map(([key, items]) => ({
      key,
      label: formatDayLabel(key),
      items: items.sort(
        (a, b) =>
          new Date(b?.created_at || 0).getTime() -
          new Date(a?.created_at || 0).getTime(),
      ),
    }))
    .sort((a, b) => keyToTime(b.key) - keyToTime(a.key));
});
const hasNotifications = computed(() =>
  groupedNotifications.value.some((g) => g.items.length),
);

function emitSearch() {
  emit("search", keyword.value);
}

async function loadMe() {
  if (!authService.isLoggin()) return;

  try {
    const res = await authService.me();
    const normalized = normalizeUserPayload(res);
    localUser.value = normalized;
    localStorage.setItem("currentUser", JSON.stringify(normalized));
  } catch {
    // Keep current user data if request fails.
  }
}

function goAccountSettings() {
  router.push("/account/settings");
}

function onUserUpdated() {
  const stored = readStoredUser();
  if (stored) {
    localUser.value = stored;
    return;
  }
  loadMe();
}

async function onLogout() {
  try {
    await authService.logout();
    sessionStorage.removeItem(HEADER_ME_SYNC_KEY);
    localUser.value = { ...DEFAULT_USER };
    notifications.value = [];
    await router.push({ name: "login" });
  } catch {
    await Swal.fire("Lï¿½-i", "Äï¿½fng xuáº¥t tháº¥t báº¡i. Vui lÃ²ng thá»­ láº¡i.", "error");
  }
}

function resolveTarget(item) {
  if (item.url) return item.url;
  if (item.type === "order" && item.url_id) return `/orders/${item.url_id}`;
  if (item.type === "price-quotation" && item.url_id)
    return `/price-quotation?ref=${item.url_id}`;
  return "/orders";
}

function mapMessage(raw, meId) {
  return {
    id: raw.id,
    sender: raw.user_id === meId ? "me" : "them",
    text: raw.content,
    type: raw.type,
    created_at: raw.created_at,
  };
}

async function fetchMessages() {
  if (!authService.isLoggin()) return;
  msgLoading.value = true;
  try {
    const res = await MessageService.ensureConversation();
    const convId = res?.conversation?.id;
    const meId = res?.conversation?.user?.id || localUser.value?.id;
    if (!convId) {
      recentMessages.value = [];
      unreadMessages.value = 0;
      return;
    }
    const msgs = await MessageService.fetchMessages(convId);
    const mapped = (msgs?.messages || []).map((m) => mapMessage(m, meId));
    recentMessages.value = mapped.slice(-5).reverse().map((m) => ({
      ...m,
      unread: m.sender === "them" && isNewMessage(m.created_at),
      preview:
        m.type === "media" ? "Đã gửi phương tiện" : m.text || "Tin nhắn mới",
      fromName: res?.conversation?.admin?.name || "Admin",
      conversation_id: convId,
    }));
    unreadMessages.value = recentMessages.value.filter((m) => m.unread).length;
  } catch {
    recentMessages.value = [];
  } finally {
    msgLoading.value = false;
  }
}

async function loadNotifications() {
  if (!authService.isLoggin()) return;
  isLoadingNoti.value = true;
  try {
    const res = await notificationService.list();
    notifications.value = res?.data || res?.notifications || res || [];
  } catch {
    notifications.value = [];
  } finally {
    isLoadingNoti.value = false;
    notificationsLoaded.value = true;
  }
}

async function markOneRead(item) {
  if (!item || item.is_read || item.read_at) return;
  item.is_read = true;
  item.read_at = new Date().toISOString();
  try {
    await notificationService.markAsRead(item.id || item.url_id);
  } catch {
    // ignore silently
  }
}

async function markAllRead() {
  if (!notifications.value.length) return;
  markingAll.value = true;
  notifications.value = notifications.value.map((n) => ({
    ...n,
    is_read: true,
    read_at: n.read_at || new Date().toISOString(),
  }));
  try {
    await notificationService.markAllAsRead();
  } catch {
    // ignore silently
  } finally {
    markingAll.value = false;
  }
}

function isNewMessage(createdAt) {
  const seen = localStorage.getItem("customer_chat_seen_at");
  if (!createdAt) return false;
  return !seen || new Date(createdAt) > new Date(seen);
}

function markMessagesSeen() {
  localStorage.setItem("customer_chat_seen_at", new Date().toISOString());
  unreadMessages.value = 0;
  recentMessages.value = recentMessages.value.map((m) => ({ ...m, unread: false }));
}

function goChat(msg) {
  closeMessages();
  markMessagesSeen();
  if (msg?.conversation_id) {
    router.push({ name: "contact.chat", params: { id: msg.conversation_id } });
  } else {
    router.push({ name: "contact.list" });
  }
}

function toggleNotifications() {
  isNotificationOpen.value = !isNotificationOpen.value;
  if (isNotificationOpen.value && !notificationsLoaded.value) {
    loadNotifications();
  }
}

function toggleMessages() {
  isMessageOpen.value = !isMessageOpen.value;
  if (isMessageOpen.value && !recentMessages.value.length) {
    fetchMessages();
  }
}
function closeMessages() {
  isMessageOpen.value = false;
}

function handleClickOutside(event) {
  if (notificationRef.value && !notificationRef.value.contains(event.target)) {
    isNotificationOpen.value = false;
  }
  if (messageRef.value && !messageRef.value.contains(event.target)) {
    isMessageOpen.value = false;
  }
}

function fallbackTitle(item) {
  if (item.type === "order") return "Cập nhật đơn hàng";
  if (item.type === "price-quotation") return "Cập nhật báo giá";
  return "Thông báo";
}

function formatTime(t) {
  if (!t) return "--:--";
  const d = dayjs(t);
  return d.isValid() ? d.format("HH:mm") : "--:--";
}

function formatRelative(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return "";
  const diffMs = Date.now() - d.getTime();
  const diffMin = Math.round(diffMs / 60000);
  if (diffMin < 1) return "Vua xong";
  if (diffMin < 60) return `${diffMin} phut truoc`;
  const diffH = Math.round(diffMin / 60);
  if (diffH < 24) return `${diffH} gio truoc`;
  const diffD = Math.round(diffH / 24);
  if (diffD < 7) return `${diffD} ngay truoc`;
  return d.toLocaleDateString();
}

function toDateKey(input) {
  const d = new Date(input || Date.now());
  if (Number.isNaN(d.getTime())) return "unknown";
  const tzOffset = d.getTimezoneOffset();
  const local = new Date(d.getTime() - tzOffset * 60000);
  return local.toISOString().slice(0, 10);
}

const todayKey = toDateKey(Date.now());
const yesterdayKey = toDateKey(Date.now() - 24 * 60 * 60 * 1000);

function keyToTime(key) {
  const d = new Date(key);
  return Number.isNaN(d.getTime()) ? 0 : d.getTime();
}

function formatDayLabel(key) {
  if (key === todayKey) return "Hôm nay";
  if (key === yesterdayKey) return "Hôm qua";
  if (key === "unknown") return "Khác";
  const parts = key.split("-");
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return key;
}

async function openNotification(item) {
  await markOneRead(item);
  const target = resolveTarget(item);
  if (target) {
    router.push(target);
  }
  isNotificationOpen.value = false;
}

function onIncomingNotification(event) {
  const payload = event?.detail;
  if (!payload) return;
  notifications.value = [
    {
      ...payload,
      is_read: false,
      read_at: null,
      created_at: payload.created_at || new Date().toISOString(),
    },
    ...notifications.value,
  ];
}

function refreshEchoAuthHeader() {
  try {
    const token = localStorage.getItem("access_token") || "";
    if (window.Echo?.connector?.pusher?.config?.auth) {
      window.Echo.connector.pusher.config.auth.headers = {
        Authorization: `Bearer ${token}`,
      };
    }
  } catch {
    // ignore
  }
}

function subscribeRealtime() {
  try {
    const raw = localStorage.getItem("currentUser");
    const user = raw ? JSON.parse(raw) : null;
    const userId = user?.id;
    if (!userId) return;
    if (subscribedChannel) return;
    refreshEchoAuthHeader();

    subscribedChannel = window.Echo?.private?.(`user.${userId}`);
    if (!subscribedChannel) return;

    subscribedChannel.listen(".NotificationPushed", (e) => {
      const n = e?.notification || e;
      const payload = {
        ...n,
        is_read: false,
        read_at: null,
        created_at: n?.created_at || new Date().toISOString(),
      };
      notifications.value = [payload, ...notifications.value];
      window.dispatchEvent(new CustomEvent("notification-received", { detail: payload }));
      toast.fire({
        icon: "info",
        title: payload.title || "Thông báo mới",
        text: payload.content || "Bạn có thông báo mới",
      });
    });

    subscribedChannel.listen(".MessageSent", (e) => {
      const payload = e?.detail || e;
      if (!payload) return;
      const isCurrentChat =
        route.name === "contact.chat" &&
        String(route.params?.id) === String(payload.conversation_id);
      const isFromMe = Number(payload.user_id) === Number(userId);
      if (isCurrentChat || isFromMe) {
        markMessagesSeen();
        return;
      }

      const preview =
        payload.type === "media" ? "Đã gửi phương tiện" : payload.content || "Tin nhắn mới";
      const entry = {
        id: payload.id,
        conversation_id: payload.conversation_id,
        fromName: "Admin",
        preview,
        created_at: payload.created_at || new Date().toISOString(),
        unread: true,
      };
      recentMessages.value = [entry, ...recentMessages.value].slice(0, 5);
      unreadMessages.value += 1;
    });
  } catch {
    // ignore
  }
}

function unsubscribeRealtime() {
  try {
    const raw = localStorage.getItem("currentUser");
    const user = raw ? JSON.parse(raw) : null;
    const userId = user?.id;
    if (subscribedChannel && userId) {
      window.Echo?.leave?.(`user.${userId}`);
    }
  } catch {
    // ignore
  }
  subscribedChannel = null;
}

onMounted(() => {
  const stored = readStoredUser();
  if (stored) {
    localUser.value = stored;
  }

  const synced = sessionStorage.getItem(HEADER_ME_SYNC_KEY) === "1";
  if (!synced) {
    loadMe();
    sessionStorage.setItem(HEADER_ME_SYNC_KEY, "1");
  }
  window.addEventListener("customer-user-updated", onUserUpdated);
  window.addEventListener("notification-received", onIncomingNotification);
  window.addEventListener("customer-messages-read", markMessagesSeen);
  window.addEventListener("click", handleClickOutside);
  subscribeRealtime();
  fetchMessages();
});

onBeforeUnmount(() => {
  window.removeEventListener("customer-user-updated", onUserUpdated);
  window.removeEventListener("notification-received", onIncomingNotification);
  window.removeEventListener("customer-messages-read", markMessagesSeen);
  window.removeEventListener("click", handleClickOutside);
  unsubscribeRealtime();
});

watch(
  () => route.name,
  (name) => {
    if (name === "contact.chat") {
      markMessagesSeen();
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.app-header {
  height: var(--header-heigh);
  background: var(--main-extra-bg);
  position: sticky;
  top: 0;
  z-index: 1030;
  opacity: 1;
  backdrop-filter: none !important;
  -webkit-backdrop-filter: none !important;
  filter: none !important;
}

.brand-name {
  font-weight: 800;
  color: var(--dark);
}

.brand-sub {
  font-size: 0.8rem;
  color: var(--font-extra-color);
}

.logo-circle {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  border-radius: 12px;
  color: var(--dark);
}

.nav-pill {
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  text-decoration: none;
  color: var(--font-color);
  border: 1px solid transparent;
}

.nav-pill:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}

.nav-pill.router-link-active,
.nav-pill.router-link-exact-active {
  background: var(--main-color);
  border-color: var(--hover-border-color);
  color: var(--dark);
  font-weight: 600;
}

.search-wrap .input-group {
  min-width: 360px;
}

.search-wrap .form-control,
.search-wrap .input-group-text {
  background: var(--main-extra-bg);
  border-color: var(--border-color);
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 600;
}

.btn-main:hover {
  filter: var(--brightness);
}

.icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  color: var(--font-color);
}

.icon-btn:hover {
  background: var(--hover-color);
}

.badge-cart {
  position: absolute;
  top: -6px;
  right: -6px;
  font-size: 0.7rem;
  padding: 0.25rem 0.4rem;
}

.user-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.6rem;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  color: var(--font-color);
}

.user-btn:hover {
  background: var(--hover-color);
}

.avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 1px solid var(--border-color);
}

.notification-menu {
  width: 320px;
  max-height: 420px;
  overflow: hidden;
}

.notif-list {
  max-height: 360px;
  overflow-y: auto;
}

.notif-item {
  border-bottom: 1px solid var(--border-color);
}

.notif-item:last-child {
  border-bottom: none;
}

.notif-group + .notif-group {
  border-top: 1px solid var(--border-color);
}

.group-heading {
  background: var(--main-extra-bg);
  letter-spacing: 0.04em;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-top: 6px;
  background: var(--border-color);
  flex-shrink: 0;
}

.dot.unread {
  background: var(--main-color);
  box-shadow: 0 0 0 3px rgba(255, 199, 0, 0.2);
}

.badge-noti {
  position: absolute;
  top: -6px;
  right: -6px;
  font-size: 0.7rem;
  padding: 0.25rem 0.4rem;
}
</style>
