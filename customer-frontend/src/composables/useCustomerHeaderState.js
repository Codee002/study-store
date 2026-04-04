import { reactive } from "vue";
import router from "@/routers";
import Swal from "sweetalert2";
import authService from "@/services/auth.service";
import notificationService from "@/services/notification.service";
import MessageService from "@/services/message.service";
import cartService from "@/services/cart.service";

const DEFAULT_USER = {
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
};

const HEADER_ME_SYNC_KEY = "customer_header_me_synced";

const toast = Swal.mixin({
  toast: true,
  position: "bottom-end",
  showConfirmButton: false,
  timer: 5000,
  timerProgressBar: true,
});

const state = reactive({
  user: null,
  notifications: [],
  messages: [],
  unreadMessages: 0,
  cartCount: 0,
  notificationsLoaded: false,
  messagesLoaded: false,
  cartLoaded: false,
  userLoaded: false,
  isLoadingNoti: false,
  msgLoading: false,
  markingAll: false,
  bootstrapPromise: null,
  listenersBound: false,
  subscribedChannel: null,
  subscribedUserId: null,
  forceLoggingOut: false,
});

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
    avatar: meUser?.avatar || meUser?.profile?.avatar || "/default-user-avatar.svg",
    tier_id: meUser?.tier_id ?? meUser?.profile?.tier ?? null,
    profile: meUser?.profile ?? null,
  };
}

function syncStoredUser(user) {
  try {
    localStorage.setItem("currentUser", JSON.stringify(user));
  } catch {
    // ignore
  }
}

function hydrateUser() {
  if (!state.user) {
    state.user = readStoredUser() || { ...DEFAULT_USER };
  }
}

function syncUserState(user) {
  state.user = user;
  state.userLoaded = true;
  syncStoredUser(user);
}

async function loadMe(force = false) {
  if (!authService.isLoggin()) return;

  hydrateUser();
  if (!force && state.userLoaded && state.user) return;

  try {
    const res = await authService.me();
    syncUserState(normalizeUserPayload(res));
  } catch (error) {
    const status = Number(error?.response?.status || 0);
    if ((status === 401 || status === 423) && authService.isLoggin()) {
      await forceLogoutWithAlert(
        status === 423
          ? "Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên."
          : "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.",
      );
      return;
    }
    state.userLoaded = true;
  }
}

async function forceLogoutWithAlert(message) {
  if (state.forceLoggingOut) return;

  state.forceLoggingOut = true;
  try {
    await authService.logout();
  } catch {
    // ignore
  }

  resetHeaderState();
  await Swal.fire({
    icon: "warning",
    title: "Thông báo",
    text: message,
    confirmButtonText: "Đồng ý",
  });
  await router.push({ name: "login" });
  state.forceLoggingOut = false;
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

function buildMessagePreview(type, content, fallback = "Tin nhan moi") {
  if (type === "media") return "Da gui phuong tien";
  if (type === "recalled") return "Tin nhan da bi thu hoi";
  return content || fallback;
}

function mapInboxConversation(item) {
  const partnerName = item?.partner?.name || item?.name || "Tin nhan";
  return {
    id: item.id,
    conversation_id: item.id,
    kind: item.kind || null,
    fromName: partnerName,
    preview: item.last_message || "Tin nhan moi",
    created_at: item.updated_at || null,
    unread: Number(item.unread || 0) > 0,
  };
}

function isNewMessage(createdAt) {
  const seen = localStorage.getItem("customer_chat_seen_at");
  if (!createdAt) return false;
  return !seen || new Date(createdAt) > new Date(seen);
}

function syncNotificationCache() {
  state.notifications = [...state.notifications];
}

function syncMessageCache() {
  state.messages = [...state.messages];
}

async function fetchMessages(force = false) {
  if (!authService.isLoggin()) return;
  if (!force && state.messagesLoaded) return;

  state.msgLoading = true;
  try {
    const inbox = await MessageService.fetchInbox();
    const conversations = Array.isArray(inbox?.conversations) ? inbox.conversations : [];
    if (!conversations.length) {
      state.messages = [];
      state.unreadMessages = 0;
      state.messagesLoaded = true;
      return;
    }

    const mapped = conversations
      .map(mapInboxConversation)
      .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
      .slice(0, 5);

    state.messages = mapped;
    state.unreadMessages = mapped.filter((m) => m.unread && isNewMessage(m.created_at)).length;
    state.messagesLoaded = true;
    syncMessageCache();
  } catch {
    state.messages = [];
    state.unreadMessages = 0;
    state.messagesLoaded = true;
  } finally {
    state.msgLoading = false;
  }
}

async function fetchCartCount(force = false) {
  if (!authService.isLoggin()) {
    state.cartCount = 0;
    state.cartLoaded = true;
    return;
  }
  if (!force && state.cartLoaded) return;

  try {
    state.cartCount = await cartService.getCount();
  } catch {
    state.cartCount = 0;
  } finally {
    state.cartLoaded = true;
  }
}

async function loadNotifications(force = false) {
  if (!authService.isLoggin()) return;
  if (!force && state.notificationsLoaded) return;

  state.isLoadingNoti = true;
  try {
    const res = await notificationService.list();
    state.notifications = res?.data || res?.notifications || res || [];
  } catch {
    state.notifications = [];
  } finally {
    state.notificationsLoaded = true;
    state.isLoadingNoti = false;
    syncNotificationCache();
  }
}

async function markOneRead(item) {
  if (!item || item.is_read || item.read_at) return;
  item.is_read = true;
  item.read_at = new Date().toISOString();
  syncNotificationCache();
  try {
    await notificationService.markAsRead(item.id || item.url_id);
  } catch {
    // ignore
  }
}

async function markAllRead() {
  if (!state.notifications.length) return;
  state.markingAll = true;
  state.notifications = state.notifications.map((n) => ({
    ...n,
    is_read: true,
    read_at: n.read_at || new Date().toISOString(),
  }));
  syncNotificationCache();
  try {
    await notificationService.markAllAsRead();
  } catch {
    // ignore
  } finally {
    state.markingAll = false;
  }
}

function markMessagesSeen() {
  localStorage.setItem("customer_chat_seen_at", new Date().toISOString());
  state.unreadMessages = 0;
  state.messages = state.messages.map((m) => ({ ...m, unread: false }));
  syncMessageCache();
}

function pushNotification(payload, shouldToast = false) {
  if (!payload) return;
  const item = {
    ...payload,
    is_read: payload?.is_read ?? false,
    read_at: payload?.read_at ?? null,
    created_at: payload?.created_at || new Date().toISOString(),
  };
  const itemKey = String(item.id || item.url_id || "") + "|" + String(item.created_at || "");
  const exists = state.notifications.some(
    (n) => String(n.id || n.url_id || "") + "|" + String(n.created_at || "") === itemKey,
  );
  if (exists) return;
  state.notifications = [item, ...state.notifications];
  state.notificationsLoaded = true;
  syncNotificationCache();

  if (shouldToast) {
    toast.fire({
      icon: "info",
      title: item.title || "Thông báo mới",
      text: item.content || "Bạn có thông báo mới",
    });
  }
}

function refreshEchoAuthHeader() {
  try {
    const token = localStorage.getItem("access_token") || "";
    if (typeof window?.__resetCustomerEcho === "function") {
      window.__resetCustomerEcho(token);
      return;
    }

    if (window.Echo?.connector?.pusher?.config?.auth) {
      window.Echo.connector.pusher.config.auth.headers = {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      };
    }
  } catch {
    // ignore
  }
}

function leaveRealtime() {
  try {
    if (state.subscribedUserId) {
      window.Echo?.leave?.(`user.${state.subscribedUserId}`);
    }
  } catch {
    // ignore
  }
  state.subscribedChannel = null;
  state.subscribedUserId = null;
}

function subscribeRealtime() {
  if (!authService.isLoggin()) return;
  const userId = Number(state.user?.id || readStoredUser()?.id || 0);
  if (!userId) return;
  if (state.subscribedChannel && state.subscribedUserId === userId) return;

  leaveRealtime();
  refreshEchoAuthHeader();

  const channel = window.Echo?.private?.(`user.${userId}`);
  if (!channel) return;

  state.subscribedChannel = channel;
  state.subscribedUserId = userId;

  channel.listen(".NotificationPushed", (e) => {
    const n = e?.notification || e;
    if (n?.type === "account-locked") {
      forceLogoutWithAlert(
        n?.content || "Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.",
      );
      return;
    }

    pushNotification({
      ...n,
      is_read: false,
      read_at: null,
      created_at: n?.created_at || new Date().toISOString(),
    }, true);
  });

  channel.listen(".MessageSent", (e) => {
    const payload = e?.detail || e;
    if (!payload) return;

    const currentRoute = router.currentRoute.value;
    const isCurrentChat =
      currentRoute?.name === "contact.chat" &&
      String(currentRoute?.params?.id || "") === String(payload.conversation_id || "");
    const isFromMe = Number(payload.user_id) === userId;

    if (isCurrentChat || isFromMe) {
      markMessagesSeen();
      return;
    }

    const conversationId = payload.conversation_id;
    const idx = state.messages.findIndex((item) => String(item.conversation_id) === String(conversationId));
    const preview = buildMessagePreview(payload.type, payload.content);
    const existing = idx !== -1 ? state.messages[idx] : null;
    const entry = {
      id: existing?.id || conversationId,
      conversation_id: conversationId,
      kind: existing?.kind || null,
      fromName: existing?.fromName || "Tin nhan",
      preview,
      created_at: payload.created_at || new Date().toISOString(),
      unread: true,
    };

    if (idx !== -1) {
      state.messages.splice(idx, 1, entry);
    } else {
      state.messages.unshift(entry);
    }

    state.messages = [...state.messages]
      .sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))
      .slice(0, 5);
    state.unreadMessages = state.messages.filter((m) => m.unread && isNewMessage(m.created_at)).length;
    state.messagesLoaded = true;
    syncMessageCache();
  });
}

function onUserUpdated() {
  const stored = readStoredUser();
  if (stored) {
    syncUserState(stored);
    subscribeRealtime();
    return;
  }
  loadMe(true).finally(() => {
    subscribeRealtime();
  });
}

function bindGlobalListeners() {
  if (state.listenersBound || typeof window === "undefined") return;

  window.addEventListener("customer-user-updated", onUserUpdated);
  window.addEventListener("customer-messages-read", markMessagesSeen);
  window.addEventListener("cart-updated", (event) => {
    const nextCount = Number(event?.detail?.count);
    if (Number.isFinite(nextCount) && nextCount >= 0) {
      state.cartCount = nextCount;
      state.cartLoaded = true;
      return;
    }
    fetchCartCount(true);
  });

  state.listenersBound = true;
}

function bootstrapHeaderData(force = false) {
  if (!authService.isLoggin()) return Promise.resolve();
  if (state.bootstrapPromise && !force) return state.bootstrapPromise;

  const task = Promise.all([
    loadMe(force),
    fetchMessages(force),
    fetchCartCount(force),
  ]).then(() => {
    subscribeRealtime();
    sessionStorage.setItem(HEADER_ME_SYNC_KEY, "1");
  });

  state.bootstrapPromise = task.finally(() => {
    if (state.bootstrapPromise === task) {
      state.bootstrapPromise = null;
    }
  });

  return state.bootstrapPromise;
}

function initHeaderState(force = false) {
  hydrateUser();
  bindGlobalListeners();

  if (!authService.isLoggin()) return Promise.resolve();

  const synced = sessionStorage.getItem(HEADER_ME_SYNC_KEY) === "1";
  if (force || !synced || !state.userLoaded || !state.messagesLoaded || !state.cartLoaded) {
    return bootstrapHeaderData(force || !synced);
  }

  subscribeRealtime();
  return Promise.resolve();
}

function ensureHeaderSession() {
  if (!authService.isLoggin()) return Promise.resolve();
  return initHeaderState(false);
}

function resetHeaderState() {
  sessionStorage.removeItem(HEADER_ME_SYNC_KEY);
  leaveRealtime();
  state.user = { ...DEFAULT_USER };
  state.notifications = [];
  state.messages = [];
  state.unreadMessages = 0;
  state.cartCount = 0;
  state.notificationsLoaded = false;
  state.messagesLoaded = false;
  state.cartLoaded = false;
  state.userLoaded = false;
  state.isLoadingNoti = false;
  state.msgLoading = false;
  state.markingAll = false;
  state.bootstrapPromise = null;
  state.forceLoggingOut = false;
}

export function useCustomerHeaderState() {
  return {
    state,
    defaultUser: DEFAULT_USER,
    loadMe,
    fetchMessages,
    fetchCartCount,
    loadNotifications,
    markOneRead,
    markAllRead,
    markMessagesSeen,
    pushNotification,
    initHeaderState,
    ensureHeaderSession,
    resetHeaderState,
  };
}
