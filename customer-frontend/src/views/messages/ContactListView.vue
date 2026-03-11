<template>
  <div>
    <AppHeader :cart-count="0" />

    <main class="container py-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h2 class="mb-1">Liên hệ</h2>
          <div class="text-muted small">Kết nối nhanh với admin để được hỗ trợ.</div>
        </div>
      </div>

      <div class="card card-soft shadow-sm">
        <div class="card-body text-center py-5">
          <div v-if="loading">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div>Đang mở cuộc trò chuyện với admin...</div>
          </div>
          <div v-else-if="error" class="alert alert-danger mb-0">
            {{ error }}
          </div>
          <div v-else class="text-muted">
            Đang chuyển tới cửa sổ chat...
          </div>
        </div>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import MessageService from "@/services/message.service";

const router = useRouter();
const loading = ref(true);
const error = ref("");

async function bootstrap() {
  try {
    const res = await MessageService.ensureConversation();
    const conversationId = res?.conversation?.id;
    if (conversationId) {
      router.replace({ name: "contact.chat", params: { id: conversationId } });
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

onMounted(bootstrap);
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 14px;
}
</style>
