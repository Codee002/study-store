import { reactive } from "vue";
import router from "@/routers";
import Swal from "sweetalert2";
import AuthService from "@/services/auth.service";
import MessageService from "@/services/message.service";
import notificationService from "@/services/notification.service";

const ADMIN_HEADER_SYNC_KEY = "admin_header_bootstrapped";

const state = reactive({
  contacts: [],
  notifications: [],
  contactsLoaded: false,
  notificationsLoaded: false,
  messagesLoading: false,
  isLoadingNotifications: false,
  markingAll: false,
  bootstrapPromise: null,
  listenersBound: false,
  subscribedChannel: null,
  subscribedUserId: null,
});

const toast = Swal.mixin({
  toast: true,
  position: "bottom-end",
  showConfirmButton: false,
  timer: 5000,
  timerProgressBar: true,
});

function readStoredUser() {
  try {
    const raw = localStorage.getItem("currentUser");
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
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

function normalizeMessagePreview(payload) {
  if (!payload) return "Tin nhắn mới";
  if (payload.type === "media") return "Đã gửi phương tiện";
  if (payload.type === "recalled") return "Tin nhắn đã thu hồi";
  return payload.content || "Tin nhắn mới";
}

function syncContacts() {
  state.contacts = [...state.contacts];
}

function syncNotifications() {
  state.notifications = [...state.notifications];
}

async function loadContacts(force = false) {
  if (!AuthService.isLoggin()) return;
  if (state.messagesLoading || (state.contactsLoaded && !force)) return;

  state.messagesLoading = true;
  try {
    const res = await MessageService.fetchContacts();
    state.contacts = res?.contacts || [];
  } catch {
    state.contacts = [];
  } finally {
    state.messagesLoading = false;
    state.contactsLoaded = true;
    syncContacts();
  }
}

async function loadNotifications(force = false) {
  if (!AuthService.isLoggin()) return;
  if (state.isLoadingNotifications || (state.notificationsLoaded && !force)) return;

  state.isLoadingNotifications = true;
  try {
    const res = await notificationService.list();
    state.notifications = res?.data || res?.notifications || res || [];
  } catch {
    state.notifications = [];
  } finally {
    state.isLoadingNotifications = false;
    state.notificationsLoaded = true;
    syncNotifications();
  }
}

async function markOneRead(item) {
  if (!item || item.is_read || item.read_at) return;
  item.is_read = true;
  item.read_at = new Date().toISOString();
  syncNotifications();
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
  syncNotifications();
  try {
    await notificationService.markAllAsRead();
  } catch {
    // ignore
  } finally {
    state.markingAll = false;
  }
}

function pushNotification(payload, shouldToast = false) {
  if (!payload) return;

  const item = {
    ...payload,
    is_read: payload?.is_read ?? false,
    read_at: payload?.read_at ?? null,
    created_at: payload?.created_at || new Date().toISOString(),
  };
  const itemKey = `${item.id || item.url_id || ""}|${item.created_at || ""}`;
  const exists = state.notifications.some(
    (n) => `${n.id || n.url_id || ""}|${n.created_at || ""}` === itemKey,
  );
  if (exists) return;

  state.notifications = [item, ...state.notifications];
  state.notificationsLoaded = true;
  syncNotifications();

  if (shouldToast) {
    toast.fire({
      icon: "info",
      title: item.title || "Thông báo mới",
      text: item.content || "Bạn có thông báo mới",
    });
  }
}

function applyMessageToContacts(payload, currentUserId) {
  if (!payload?.conversation_id) return;

  const isFromMe = Number(payload.user_id) === Number(currentUserId);
  const currentRoute = router.currentRoute.value;
  const isCurrentChat =
    currentRoute?.name === "messages.chat" &&
    String(currentRoute?.params?.id || "") === String(payload.conversation_id);

  const index = state.contacts.findIndex(
    (c) => String(c.conversation_id || "") === String(payload.conversation_id),
  );

  if (index === -1) {
    loadContacts(true);
    return;
  }

  const current = state.contacts[index];
  const nextUnread = isFromMe || isCurrentChat ? 0 : Number(current.unread || 0) + 1;
  state.contacts[index] = {
    ...current,
    last_message: normalizeMessagePreview(payload),
    updated_at: payload.created_at || new Date().toISOString(),
    unread: nextUnread,
  };
  state.contacts = [...state.contacts].sort(
    (a, b) =>
      new Date(b?.updated_at || 0).getTime() - new Date(a?.updated_at || 0).getTime(),
  );
  state.contactsLoaded = true;
  syncContacts();
}

function markConversationRead(conversationId) {
  if (!conversationId) return;
  state.contacts = state.contacts.map((contact) =>
    String(contact.conversation_id || "") === String(conversationId)
      ? { ...contact, unread: 0 }
      : contact,
  );
  syncContacts();
}

function onMessagesRead(event) {
  markConversationRead(event?.detail?.conversationId || router.currentRoute.value?.params?.id);
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
  if (!AuthService.isLoggin()) return;

  const userId = Number(readStoredUser()?.id || 0);
  if (!userId) return;
  if (state.subscribedChannel && state.subscribedUserId === userId) return;

  leaveRealtime();
  refreshEchoAuthHeader();

  const channel = window.Echo?.private?.(`user.${userId}`);
  if (!channel) return;

  state.subscribedChannel = channel;
  state.subscribedUserId = userId;

  channel.listen(".NotificationPushed", (e) => {
    const notification = e?.notification || e;
    pushNotification(
      {
        ...notification,
        is_read: false,
        read_at: null,
        created_at: notification?.created_at || new Date().toISOString(),
      },
      true,
    );
  });

  channel.listen(".MessageSent", (e) => {
    const payload = e?.detail || e;
    if (!payload) return;
    applyMessageToContacts(payload, userId);
  });
}

function bindGlobalListeners() {
  if (state.listenersBound || typeof window === "undefined") return;

  window.addEventListener("messages-read", onMessagesRead);
  window.addEventListener("admin-contacts-updated", (event) => {
    if (Array.isArray(event?.detail?.contacts)) {
      state.contacts = [...event.detail.contacts];
      state.contactsLoaded = true;
      syncContacts();
      return;
    }
    loadContacts(true);
  });

  state.listenersBound = true;
}

function bootstrapHeaderData(force = false) {
  if (!AuthService.isLoggin()) return Promise.resolve();
  if (state.bootstrapPromise && !force) return state.bootstrapPromise;

  const task = Promise.all([loadContacts(force)]).then(() => {
    subscribeRealtime();
    sessionStorage.setItem(ADMIN_HEADER_SYNC_KEY, "1");
  });

  state.bootstrapPromise = task.finally(() => {
    if (state.bootstrapPromise === task) {
      state.bootstrapPromise = null;
    }
  });

  return state.bootstrapPromise;
}

function initHeaderState(force = false) {
  bindGlobalListeners();

  if (!AuthService.isLoggin()) return Promise.resolve();

  const synced = sessionStorage.getItem(ADMIN_HEADER_SYNC_KEY) === "1";
  if (force || !synced || !state.contactsLoaded) {
    return bootstrapHeaderData(force || !synced);
  }

  subscribeRealtime();
  return Promise.resolve();
}

function resetHeaderState() {
  sessionStorage.removeItem(ADMIN_HEADER_SYNC_KEY);
  leaveRealtime();
  state.contacts = [];
  state.notifications = [];
  state.contactsLoaded = false;
  state.notificationsLoaded = false;
  state.messagesLoading = false;
  state.isLoadingNotifications = false;
  state.markingAll = false;
  state.bootstrapPromise = null;
}

export function useAdminHeaderState() {
  return {
    state,
    loadContacts,
    loadNotifications,
    markOneRead,
    markAllRead,
    pushNotification,
    markConversationRead,
    initHeaderState,
    resetHeaderState,
  };
}
