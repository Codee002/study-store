<template>
  <div class="page-wrap">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-1">Liên hệ</h4>
        <div class="small text-muted">Chọn khách hàng để mở hộp chat</div>
      </div>
    </div>

    <div class="card card-soft">
      <div class="card-body">
        <div class="input-group mb-3">
          <span class="input-group-text bg-transparent">
            <i class="fa-solid fa-magnifying-glass"></i>
          </span>
          <input
            v-model="keyword"
            type="search"
            class="form-control bg-transparent"
            placeholder="Tìm theo tên hoặc công ty..."
            @keydown.enter.prevent="fetchContacts"
          />
          <button v-if="keyword" class="btn btn-outline-secondary" @click="keyword = ''">
            <i class="fa-solid fa-xmark"></i>
          </button>
          <button class="btn btn-outline-primary" type="button" @click="fetchContacts">
            Làm mới
          </button>
        </div>

        <div v-if="loading" class="text-center text-muted py-3">
          <div class="spinner-border text-primary mb-2"></div>
          Đang tải danh sách...
        </div>
        <div v-else-if="error" class="alert alert-danger">
          {{ error }}
        </div>

        <div class="contact-list">
          <button
            v-for="c in filteredContacts"
            :key="c.id"
            class="contact-item"
            @click="openChat(c.id)"
          >
            <div class="d-flex align-items-center gap-3">
              <img
                v-if="c.avatar"
                :src="c.avatar"
                class="avatar"
                alt="avatar"
              />
              <div v-else class="avatar placeholder">
                <i class="fa-regular fa-user"></i>
              </div>
              <div class="flex-1">
                <div class="fw-semibold d-flex align-items-center gap-2">
                  {{ c.name }}
                  <span v-if="c.unread" class="badge bg-danger-subtle text-danger rounded-pill">
                    {{ c.unread }} mới
                  </span>
                </div>
                <div class="small text-muted text-truncate">{{ c.last_message || "Chưa có tin nhắn" }}</div>
              </div>
            </div>
            <div class="text-end small text-muted">
              {{ formatTime(c.updated_at) }}
            </div>
          </button>

          <div v-if="!filteredContacts.length" class="text-center py-4 text-muted">
            Không tìm thấy liên hệ.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import dayjs from "dayjs";
import MessageService from "@/services/message.service";

const keyword = ref("");
const contacts = ref([]);
const loading = ref(false);
const error = ref("");
const router = useRouter();

const filteredContacts = computed(() => {
  const q = keyword.value.toLowerCase().trim();
  if (!q) return contacts.value;
  return contacts.value.filter((c) => c.name.toLowerCase().includes(q));
});

function formatTime(t) {
  return t ? dayjs(t).format("HH:mm DD/MM") : "--:--";
}

async function fetchContacts() {
  loading.value = true;
  error.value = "";
  try {
    const res = await MessageService.fetchContacts(keyword.value);
    contacts.value = res?.contacts || [];
    window.dispatchEvent(
      new CustomEvent("admin-contacts-updated", {
        detail: { contacts: contacts.value },
      }),
    );
  } catch (e) {
    error.value =
      e?.response?.data?.message || e?.message || "Không tải được danh sách liên hệ.";
  } finally {
    loading.value = false;
  }
}

async function openChat(userId) {
  try {
    let conversationId = contacts.value.find((c) => c.id === userId)?.conversation_id || null;

    if (!conversationId) {
      const res = await MessageService.ensureConversationWith(userId);
      conversationId = res?.conversation?.id;
      await fetchContacts();
    }

    if (conversationId) {
      router.push({ name: "messages.chat", params: { id: conversationId } });
    }
  } catch (e) {
    error.value =
      e?.response?.data?.message || e?.message || "Không mở được cuộc trò chuyện.";
  }
}

onMounted(fetchContacts);
</script>

<style scoped>
.page-wrap {
  padding: 12px;
}
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 12px;
}
.contact-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.contact-item {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--hover-background-color);
  padding: 12px;
  width: 100%;
  text-align: left;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
  transition: transform 0.05s ease, border-color 0.1s ease;
}
.contact-item:hover {
  border-color: var(--hover-border-color);
  transform: translateY(-1px);
}
.avatar {
  width: 48px;
  height: 48px;
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
.text-truncate {
  max-width: 320px;
}
</style>
