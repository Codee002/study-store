<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Tài khoản người dùng</h4>
          <div class="small opacity-75">
            Quản lý danh sách tài khoản, tìm kiếm và xem đăng ký tier
          </div>
        </div>

        <button class="btn btn-outline-secondary" @click="fetchUsers" :disabled="loading">
          <i class="fa-solid fa-rotate me-1"></i> Làm mới
        </button>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6 col-lg-5">
              <div class="input-group">
                <span class="input-group-text bg-transparent">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  v-model="keyword"
                  type="text"
                  class="form-control bg-transparent"
                  placeholder="Tìm theo tên, username, email, mã số thuế..."
                />
                <button
                  class="btn btn-outline-secondary"
                  @click="keyword = ''"
                  v-if="keyword"
                  title="Clear"
                >
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>

            <div class="col-12 col-md-6 col-lg-7 d-flex justify-content-md-end gap-2">
              <span class="badge bg-secondary-subtle text-secondary align-self-center">
                Tổng: {{ meta.total }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3" style="width: 200px">Người dùng</th>
                  <th>Email</th>
              <th style="width: 130px">Tier hiện tại</th>
              <th style="width: 160px">Đăng ký tier</th>
              <th style="width: 120px">Trạng thái</th>
              <th class="text-end pe-3" style="width: 200px">Thao tác</th>
            </tr>
          </thead>

              <tbody v-if="items.length">
                <tr v-for="u in items" :key="u.id">
                  <td class="ps-3">
                    <div class="fw-semibold">{{ u.name || u.username }}</div>
                    <div class="small opacity-75">{{ u.username }}</div>
                  </td>
                  <td>
                    <div>{{ u.email }}</div>
                    <div class="small opacity-75" v-if="u.phone">ĐT: {{ u.phone }}</div>
                  </td>
                  <td>
                    <span
                      v-if="u.tier"
                      class="badge badge-on badge-tier"
                      :title="`${u.tier.name} (${u.tier.code})`"
                    >
                      <span class="badge-tier__code">{{ u.tier.code }}</span>
                      <span class="badge-tier__name text-truncate">{{ u.tier.name }}</span>
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary" v-else>Chưa gán</span>
                  </td>
                  <td>
                    <span
                      v-if="u.dealer_profile"
                      class="badge"
                      :class="dealerStatusClass(u.dealer_profile.status)"
                    >
                      {{ dealerStatusLabel(u.dealer_profile.status) }}
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary" v-else>Chưa đăng ký</span>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="u.status === 'actived' ? 'badge-on' : 'badge-off'"
                    >
                      {{ u.status === "actived" ? "Hoạt động" : "Đã khóa" }}
                    </span>
                  </td>
                  <td class="text-end pe-3">
                    <button
                      class="btn btn-sm btn-outline-secondary me-2"
                      @click="loadUserDetail(u.id)"
                      :disabled="loading && selectedUserId === u.id"
                    >
                      <i class="fa-solid fa-layer-group me-1"></i> Tier
                    </button>
                    <button
                      class="btn btn-sm"
                      :class="u.status === 'actived' ? 'btn-outline-danger' : 'btn-outline-success'"
                      @click="toggleStatus(u)"
                      :disabled="statusChangingId === u.id"
                    >
                      <i class="fa-solid" :class="u.status === 'actived' ? 'fa-lock' : 'fa-unlock'"></i>
                      {{ u.status === "actived" ? "Khóa" : "Mở khóa" }}
                    </button>
                  </td>
                </tr>
              </tbody>

              <tbody v-else>
                <tr>
                  <td colspan="6" class="text-center py-5">
                    <div class="opacity-75">
                      <i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i>
                      Không có tài khoản phù hợp.
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div
            class="d-flex justify-content-between align-items-center p-3 border-top"
            v-if="meta.total"
          >
            <div class="small opacity-75">
              Hiển thị
              {{ (meta.current_page - 1) * meta.per_page + 1 }} -
              {{ Math.min(meta.current_page * meta.per_page, meta.total) }} / {{ meta.total }}
            </div>

            <div class="btn-group">
              <button class="btn btn-outline-secondary btn-sm" :disabled="page === 1" @click="page--">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>Trang {{ page }}</button>
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="meta.current_page >= meta.last_page"
                @click="page++"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tier detail panel -->
    <div class="col-12" v-if="selectedUser">
      <div class="card card-soft">
        <div class="card-header border-0 pb-0">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-semibold">Thông tin tier & đăng ký</div>
            </div>
            <span
              v-if="selectedUser"
              class="badge bg-secondary-subtle text-secondary"
            >
              ID: {{ selectedUser.id }}
            </span>
          </div>
        </div>
        <div class="card-body">
      
          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="border rounded-4 p-3 h-100 bg-body-secondary bg-opacity-10">
                <div class="d-flex align-items-center gap-3 mb-2">
                  <div class="thumb-avatar">
                    <img
                      v-if="selectedUser?.avatar"
                      :src="selectedUser.avatar"
                      alt="Avatar"
                    />
                    <i v-else class="fa-regular fa-user"></i>
                  </div>
                  <div>
                    <div class="fw-semibold">{{ selectedUser.name || selectedUser.username }}</div>
                    <div class="small opacity-75">{{ selectedUser.email }}</div>
                    <div class="small opacity-75" v-if="selectedUser.phone">ĐT: {{ selectedUser.phone }}</div>
                  </div>
                </div>
                <div class="small text-uppercase opacity-75 mb-1">Tier hiện tại</div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                  <span
                    v-if="selectedUser.tier"
                    class="badge badge-on badge-tier"
                    :title="`${selectedUser.tier.name} (${selectedUser.tier.code})`"
                  >
                    <span class="badge-tier__code">{{ selectedUser.tier.code }}</span>
                    <span class="badge-tier__name text-truncate">{{ selectedUser.tier.name }}</span>
                  </span>
                  <span v-else class="badge bg-secondary-subtle text-secondary">Chưa gán</span>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-6">
              <div class="border rounded-4 p-3 h-100 bg-body-secondary bg-opacity-10">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <div class="fw-semibold">Đăng ký tier</div>
                  <span
                    class="badge"
                    v-if="selectedUser.dealer_profile"
                    :class="dealerStatusClass(selectedUser.dealer_profile.status)"
                  >
                    {{ dealerStatusLabel(selectedUser.dealer_profile.status) }}
                  </span>
                  <span v-else class="badge bg-secondary-subtle text-secondary">Không có đăng ký</span>
                </div>

                <template v-if="selectedUser.dealer_profile">
                  <div class="small opacity-75 mb-2">
                    Tier yêu cầu:
                    <span v-if="selectedUser.dealer_profile.tier">
                      {{ selectedUser.dealer_profile.tier.name }}
                      ({{ selectedUser.dealer_profile.tier.code }})
                    </span>
                    <span v-else>--</span>
                  </div>
                  <div class="small opacity-75 mb-1">
                    Công ty: {{ selectedUser.dealer_profile.company_name || "--" }}
                  </div>
                  <div class="small opacity-75 mb-1">
                    Địa chỉ: {{ selectedUser.dealer_profile.company_address || "--" }}
                  </div>
                  <div class="small opacity-75 mb-3">
                    Mã số thuế: {{ selectedUser.dealer_profile.tax_code || "--" }}
                  </div>

                  <div class="row g-2">
                    <div class="col-12 col-md-6">
                      <label class="form-label small mb-1">Trạng thái</label>
                      <select v-model="dealerStatus" class="form-select bg-transparent">
                        <option value="pending">Chờ duyệt</option>
                        <option value="accepted">Chấp nhận</option>
                        <option value="rejected">Từ chối</option>
                      </select>
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label small mb-1">Tier áp dụng</label>
                      <select v-model="dealerTierId" class="form-select bg-transparent">
                        <option value="">-- Chọn tier --</option>
                        <option v-for="t in tiers" :key="t.id" :value="String(t.id)">
                          {{ t.name }} ({{ t.code }})
                        </option>
                      </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                      <button
                        class="btn btn-primary"
                        @click="onUpdateDealer"
                        :disabled="loading"
                      >
                        <i class="fa-solid fa-check me-1"></i> Cập nhật đăng ký
                      </button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import Swal from "sweetalert2";
import UserService from "../../services/user.service";
import TierService from "../../services/tier.service";

const keyword = ref("");
const page = ref(1);
const perPage = 8;
const meta = ref({ current_page: 1, per_page: perPage, total: 0, last_page: 1 });
const items = ref([]);
const loading = ref(false);
const statusChangingId = ref(null);

const tiers = ref([]);
const selectedUserId = ref(null);
const selectedUser = ref(null);
const dealerStatus = ref("pending");
const dealerTierId = ref("");

async function fetchUsers() {
  loading.value = true;
  try {
    const res = await UserService.getAll({
      q: keyword.value.trim() || undefined,
      page: page.value,
      per_page: perPage,
    });

    const list = res?.data?.items ?? res?.items ?? [];
    items.value = Array.isArray(list) ? list : [];
    meta.value = res?.data?.meta ?? {
      current_page: 1,
      per_page: perPage,
      total: items.value.length,
      last_page: 1,
    };
  } catch (e) {
    await Swal.fire("Lỗi", "Không thể tải danh sách tài khoản", "error");
  } finally {
    loading.value = false;
  }
}

async function fetchTiers() {
  try {
    const res = await TierService.getAll({ per_page: 100 });
    const list = res?.data?.items ?? res?.data ?? res?.items ?? [];
    tiers.value = Array.isArray(list) ? list : [];
  } catch (e) {
    await Swal.fire("Lỗi", "Không thể tải danh sách tier", "error");
    console.log(e)
  }
}

async function loadUserDetail(id) {
  selectedUserId.value = id;
  loading.value = true;
  try {
    const res = await UserService.get(id);
    const data = res?.data ?? res ?? null;
    selectedUser.value = data;
    dealerStatus.value = data?.dealer_profile?.status ?? "pending";
    dealerTierId.value = data?.dealer_profile?.tier?.id ? String(data.dealer_profile.tier.id) : "";
  } catch (e) {
    console.log(e)
    await Swal.fire("Lỗi", "Không thể lấy thông tin tài khoản", "error");
  } finally {
    loading.value = false;
  }
}

async function toggleStatus(user) {
  if (!user || statusChangingId.value) return;
  const nextStatus = user.status === "actived" ? "disabled" : "actived";
  statusChangingId.value = user.id;
  try {
    const res = await UserService.updateStatus(user.id, { status: nextStatus });
    const data = res?.data ?? res ?? null;

    // Cập nhật danh sách
    await fetchUsers();

    // Cập nhật panel chi tiết nếu đang chọn cùng user
    if (selectedUser.value && Number(selectedUser.value.id) === Number(user.id)) {
      selectedUser.value = data;
    }

    await Swal.fire("Thành công", "Cập nhật trạng thái tài khoản thành công", "success");
  } catch (e) {
    await Swal.fire(
      "Lỗi",
      e?.response?.data?.message || "Không thể cập nhật trạng thái tài khoản",
      "error",
    );
  } finally {
    statusChangingId.value = null;
  }
}

async function onUpdateDealer() {
  if (!selectedUserId.value) return;
  loading.value = true;
  try {
    const res = await UserService.updateDealerStatus(selectedUserId.value, {
      status: dealerStatus.value,
      tier_id: dealerTierId.value || null,
    });
    const data = res?.data ?? res ?? null;
    selectedUser.value = data;
    await fetchUsers();
    await Swal.fire("Thành công", "Cập nhật đăng ký tier thành công", "success");
  } catch (e) {
    await Swal.fire(
      "Lỗi",
      e?.response?.data?.message || "Không thể cập nhật đăng ký tier",
      "error",
    );
  } finally {
    loading.value = false;
  }
}

function dealerStatusClass(status) {
  if (status === "accepted") return "badge-on";
  if (status === "rejected") return "badge-off";
  return "bg-warning-subtle text-warning";
}

function dealerStatusLabel(status) {
  if (status === "accepted") return "Đã duyệt";
  if (status === "rejected") return "Từ chối";
  return "Chờ duyệt";
}

onMounted(async () => {
  await Promise.all([fetchUsers(), fetchTiers()]);
});

watch(keyword, async () => {
  page.value = 1;
  await fetchUsers();
});

watch(page, async () => {
  await fetchUsers();
});
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.badge-on {
  background: color-mix(in srgb, #16a34a 16%, transparent);
  border: 1px solid color-mix(in srgb, #16a34a 40%, transparent);
  color: var(--font-color);
}
.badge-off {
  background: color-mix(in srgb, #ef4444 14%, transparent);
  border: 1px solid color-mix(in srgb, #ef4444 40%, transparent);
  color: var(--font-color);
}

.thumb-avatar {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--hover-background-color);
  border: 1px solid var(--hover-border-color);
  color: var(--font-color);
}
.thumb-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 10px;
}

.badge-tier {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0.35rem 0.55rem;
  max-width: 100%;
  line-height: 1.1;
}
.badge-tier__code {
  font-weight: 700;
  letter-spacing: 0.3px;
}
.badge-tier__name {
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}
</style>














