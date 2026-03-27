<template>
  <header class="topbar">
    <div class="container-fluid py-2 py-lg-3 d-flex align-items-center gap-3">
      <button
        class="icon-btn d-lg-none"
        @click="$emit('toggleSidebar')"
      >
        <i class="fa-solid fa-bars"></i>
      </button>

      <div class="brand fw-semibold">
        <span class="accent">StudyStore</span>
        <span class="brand-sub">Admin</span>
      </div>

      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown position-relative" ref="msgDropdownRef">
          <button
            class="icon-btn position-relative"
            type="button"
            title="Tin nhắn"
            @click="toggleMessages"
          >
            <i class="fa-regular fa-message"></i>
            <span
              v-if="unreadMessages > 0"
              class="badge rounded-pill bg-danger badge-noti"
            >
              {{ unreadMessages }}
            </span>
          </button>
          <div
            class="dropdown-menu dropdown-menu-end shadow-sm p-0 notification-menu"
            :class="{ show: isMsgOpen }"
          >
            <div class="notif-header d-flex align-items-center justify-content-between px-3 py-2">
              <span class="fw-semibold">Tin nhắn</span>
              <RouterLink to="/messages" class="btn btn-sm px-0" @click="isMsgOpen = false">
                Xem tất cả
              </RouterLink>
            </div>
            <div class="notif-list">
              <div v-if="messagesLoading" class="p-3 small text-center text-muted">Đang tải...</div>
              <div v-else-if="!contacts.length" class="p-3 small text-center text-muted">Chưa có tin nhắn</div>
              <button
                v-else
                v-for="c in contacts"
                :key="c.conversation_id || c.id"
                class="notif-item w-100 text-start border-0 bg-transparent d-flex align-items-start gap-2 px-3 py-2"
                @click="openChat(c)"
              >
                <span class="dot" :class="{ unread: c.unread > 0 }"></span>
                <div class="flex-grow-1">
                  <div class="fw-semibold d-flex align-items-center gap-2">
                    {{ c.name }}
                    <span v-if="c.unread" class="badge bg-danger-subtle text-danger rounded-pill">{{ c.unread }}</span>
                  </div>
                  <div class="small text-muted text-truncate">
                    {{ c.last_message || "Chưa có tin nhắn" }}
                  </div>
                </div>
                <div class="small text-muted">{{ formatTime(c.updated_at) }}</div>
              </button>
            </div>
          </div>
        </div>

        <div class="dropdown position-relative" ref="dropdownRef">
          <button
            class="icon-btn position-relative"
            type="button"
            title="Thông báo"
            @click="toggle"
          >
            <i class="fa-regular fa-bell"></i>
            <span
              v-if="unreadCount > 0"
              class="badge rounded-pill bg-danger badge-noti"
            >
              {{ unreadCount }}
            </span>
          </button>
          <div
            class="dropdown-menu dropdown-menu-end shadow-sm p-0 notification-menu"
            :class="{ show: isOpen }"
          >
            <div
              class="notif-header d-flex align-items-center justify-content-between px-3 py-2"
            >
              <span class="fw-semibold">Thông báo</span>
              <button
                class="btn btn-link btn-sm px-0"
                type="button"
                :disabled="!notifications.length || markingAll"
                @click="markAllRead"
              >
                Đã đọc hết
              </button>
            </div>
            <div class="notif-list">
              <div v-if="isLoading" class="p-3 small text-center text-muted">
                Đang tải...
              </div>
              <div
                v-else-if="!hasNotifications"
                class="p-3 small text-center text-muted"
              >
                Chưa có thông báo
              </div>
              <div
                v-else
                v-for="group in groupedNotifications"
                :key="group.key"
                class="notif-group"
              >
                <div
                  class="group-heading px-3 py-2 text-uppercase small fw-semibold text-muted"
                >
                  {{ group.label }}
                </div>
                <button
                  v-for="item in group.items"
                  :key="item.id || `${group.key}-${item.url_id}`"
                  class="notif-item w-100 text-start border-0 bg-transparent d-flex align-items-start gap-2 px-3 py-2"
                  @click="openNotification(item)"
                >
                  <span
                    class="dot"
                    :class="{ unread: !item.read_at && !item.is_read }"
                  ></span>
                  <div class="flex-grow-1">
                    <div class="fw-semibold mb-1">
                      {{ item.title || fallbackTitle(item) }}
                    </div>
                    <div class="small text-muted text-wrap">
                      {{
                        item.content ||
                        item.body ||
                        item.message ||
                        "Bạn có thông báo mới"
                      }}
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
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useAdminHeaderState } from "@/composables/useAdminHeaderState";

const router = useRouter();
const headerStore = useAdminHeaderState();

const notifications = computed(() => headerStore.state.notifications);
const contacts = computed(() => headerStore.state.contacts);
const isOpen = ref(false);
const isMsgOpen = ref(false);
const dropdownRef = ref(null);
const msgDropdownRef = ref(null);

const unreadCount = computed(() =>
  notifications.value.filter((n) => !n.read_at && !n.is_read).length,
);
const unreadMessages = computed(() =>
  contacts.value.reduce((sum, c) => sum + (Number(c.unread) || 0), 0),
);
const isLoading = computed(() => headerStore.state.isLoadingNotifications);
const messagesLoading = computed(() => headerStore.state.messagesLoading);
const markingAll = computed(() => headerStore.state.markingAll);

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

async function loadNotifications() {
  await headerStore.loadNotifications();
}

function toggle() {
  isOpen.value = !isOpen.value;
  if (isOpen.value && !headerStore.state.notificationsLoaded) {
    loadNotifications();
  }
}

function toggleMessages() {
  isMsgOpen.value = !isMsgOpen.value;
  if (isMsgOpen.value && !headerStore.state.contactsLoaded) {
    headerStore.loadContacts();
  }
}

function handleClickOutside(evt) {
  if (!dropdownRef.value) return;
  if (!dropdownRef.value.contains(evt.target)) {
    isOpen.value = false;
  }
  if (msgDropdownRef.value && !msgDropdownRef.value.contains(evt.target)) {
    isMsgOpen.value = false;
  }
}

function resolveTarget(item) {
  if (item.url) return item.url;
  if (item.type === "order" && item.url_id) return `/orders/${item.url_id}`;
  if (item.type === "evaluate" && item.url_id) return `/orders/${item.url_id}`;
  if (item.type === "evaluate-reply" && item.url_id) return `/orders/${item.url_id}`;
  if (item.type === "price-quotation" && item.url_id) {
    return `/prices/${item.url_id}/edit`;
  }
  if (item.type === "receipt" && item.url_id) return `/receipts/${item.url_id}`;
  return "/";
}

async function openNotification(item) {
  await headerStore.markOneRead(item);
  const target = resolveTarget(item);
  if (target) router.push(target);
  isOpen.value = false;
}

function openChat(contact) {
  headerStore.markConversationRead(contact?.conversation_id);
  isMsgOpen.value = false;
  if (contact?.conversation_id) {
    router.push({ name: "messages.chat", params: { id: contact.conversation_id } });
  } else {
    router.push({ name: "messages.list" });
  }
}

async function markAllRead() {
  await headerStore.markAllRead();
}

function fallbackTitle(item) {
  if (item.type === "evaluate") return "Đánh giá mới";
  if (item.type === "evaluate-reply") return "Phản hồi đánh giá";
  if (item.type === "order") return "Cập nhật đơn hàng";
  if (item.type === "price-quotation") return "Cập nhật báo giá";
  return "Thông báo";
}

function formatTime(t) {
  if (!t) return "--:--";
  const d = new Date(t);
  if (Number.isNaN(d.getTime())) return "--:--";
  return d.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
}

function formatRelative(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return "";
  const diffMs = Date.now() - d.getTime();
  const diffMin = Math.round(diffMs / 60000);
  if (diffMin < 1) return "Vừa xong";
  if (diffMin < 60) return `${diffMin} phút trước`;
  const diffH = Math.round(diffMin / 60);
  if (diffH < 24) return `${diffH} giờ trước`;
  const diffD = Math.round(diffH / 24);
  if (diffD < 7) return `${diffD} ngày trước`;
  return d.toLocaleDateString();
}

function toDateKey(input) {
  const d = new Date(input || Date.now());
  if (Number.isNaN(d.getTime())) return "unknown";
  const tzOffset = d.getTimezoneOffset();
  const local = new Date(d.getTime() - tzOffset * 60000);
  return local.toISOString().slice(0, 10);
}

function keyToTime(key) {
  const d = new Date(key);
  return Number.isNaN(d.getTime()) ? 0 : d.getTime();
}

const todayKey = toDateKey(Date.now());
const yesterdayKey = toDateKey(Date.now() - 24 * 60 * 60 * 1000);

function formatDayLabel(key) {
  if (key === todayKey) return "Hôm nay";
  if (key === yesterdayKey) return "Hôm qua";
  if (key === "unknown") return "Khác";
  const parts = key.split("-");
  if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
  return key;
}

onMounted(() => {
  window.addEventListener("click", handleClickOutside);
  headerStore.initHeaderState();
});

onBeforeUnmount(() => {
  window.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
.topbar {
  background: var(--main-extra-bg);
  border-bottom: 1px solid var(--border-color);
  color: var(--font-color);
  box-shadow: 0 10px 26px -18px rgba(0, 0, 0, 0.35);
  position: sticky;
  top: 0;
  z-index: 1030;
}
.brand {
  font-size: 1.05rem;
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
}
.accent {
  color: var(--extra-color);
  font-weight: 800;
  letter-spacing: 0.02em;
}
.brand-sub {
  color: var(--font-color);
  opacity: 0.9;
  font-weight: 650;
}
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border: 1px solid var(--border-color);
  background: transparent;
  color: #6c757d;
  border-radius: 0.8rem;
  padding: 0.45rem 0.65rem;
  transition: all 120ms ease;
  box-shadow: none;
}
.icon-btn i {
  font-size: 1.05rem;
}
.icon-btn:hover,
.icon-btn:focus {
  color: #fff;
  background: #6c757d;
  border-color: #6c757d;
  box-shadow: 0 6px 16px -12px rgba(0, 0, 0, 0.25);
}

.btn-accent {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
}
.btn-accent:hover {
  filter: var(--brightness);
}

.notification-menu {
  width: min(340px, calc(100vw - 32px));
  max-height: 420px;
  overflow: hidden;
  right: 0;
  left: auto;
}
.notif-list {
  max-height: 360px;
  overflow-y: auto;
}
.notif-group + .notif-group {
  border-top: 1px solid var(--border-color);
}
.notif-item {
  border-bottom: 1px solid var(--border-color);
}
.notif-item:last-child {
  border-bottom: none;
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
