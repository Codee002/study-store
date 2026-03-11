<template>
  <div>
    <AppHeader :cart-count="0" :user="user" />

    <main class="container py-4">
      <section class="result-shell">
        <div class="result-card mx-auto text-center">
          <h1 class="mb-2">Kết quả thanh toán VNPay</h1>
          <p class="text-muted mb-3">Mã giao dịch: {{ txnRef || "Không có" }}</p>

          <div v-if="loading" class="text-muted">Đang kiểm tra trạng thái thanh toán...</div>

          <template v-else>
            <div class="result-message mb-3" :class="statusClass">{{ statusText }}</div>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
              <button v-if="orderId > 0" class="btn btn-main" type="button" @click="goOrderDetail">
                Xem đơn hàng
              </button>
              <RouterLink class="btn btn-outline-secondary" to="/orders">Đơn hàng của tôi</RouterLink>
              <RouterLink class="btn btn-outline-secondary" to="/products">Tiếp tục mua sắm</RouterLink>
            </div>
          </template>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import authService from "@/services/auth.service";
import checkoutService from "@/services/checkout.service";

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const orderId = ref(0);
const txnRef = ref(String(route.query?.txn_ref || ""));
const localStatus = ref(String(route.query?.status || "processing"));

const user = ref({
  name: "Guest",
  avatar: "/default-user-avatar.svg",
  tier_id: null,
  profile: null,
});

const statusText = computed(() => {
  if (orderId.value > 0) return "Thanh toán thành công.";
  if (localStatus.value === "pending" || localStatus.value === "processing")
    return "Hệ thống đang xác nhận thanh toán...";
  if (localStatus.value === "failed") return "Thanh toán thất bại hoặc đã hủy.";
  if (localStatus.value === "invalid") return "Dữ liệu trả về từ VNPay không hợp lệ.";
  return "Không tìm thấy trạng thái giao dịch.";
});

const statusClass = computed(() => {
  if (orderId.value > 0) return "text-success fw-semibold";
  if (localStatus.value === "pending" || localStatus.value === "processing") return "text-warning fw-semibold";
  return "text-danger fw-semibold";
});

function goOrderDetail() {
  if (orderId.value > 0) {
    router.push({ name: "order-detail", params: { id: orderId.value } });
  }
}

async function fetchMe() {
  try {
    const meRes = await authService.me();
    const me = meRes?.data ?? meRes;
    const meUser = me?.user ?? me ?? {};
    user.value = {
      ...meUser,
      name: meUser?.name || "Guest",
      avatar: meUser?.avatar || "/default-user-avatar.svg",
      tier_id: meUser?.tier_id ?? meUser?.profile?.tier ?? null,
      profile: meUser?.profile ?? null,
    };
  } catch {
    user.value = { name: "Guest", avatar: "/default-user-avatar.svg", tier_id: null, profile: null };
  }
}

async function checkVNPayStatus() {
  if (!txnRef.value) {
    loading.value = false;
    return;
  }

  try {
    const res = await checkoutService.getVNPayStatus(txnRef.value);
    const data = res?.data || {};
    localStatus.value = String(data?.status || localStatus.value || "processing");
    orderId.value = Number(data?.order_id || 0);
  } catch {
    // keep fallback status from query
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await fetchMe();
  await checkVNPayStatus();
});
</script>

<style scoped>
.result-shell {
  min-height: 60vh;
}

.result-card {
  width: min(680px, 100%);
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 24px;
}

.result-message {
  font-size: 1.05rem;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}
</style>