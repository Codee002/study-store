<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Cấp tài khoản</h4>
          <div class="small opacity-75">Quản lý danh sách cấp</div>
        </div>

        <RouterLink
          class="icon-btn icon-add"
          :to="{ name: 'tiers.create' }"
          title="Thêm cấp"
        >
          <i class="fa-solid fa-circle-plus"></i>
        </RouterLink>
      </div>
    </div>

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
                  placeholder="Tìm theo tên hoặc mã..."
                />
                <button
                  v-if="keyword"
                  class="btn btn-outline-secondary"
                  title="Clear"
                  @click="keyword = ''"
                >
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            </div>

            <div
              class="col-12 col-md-6 col-lg-7 d-flex justify-content-md-end gap-2"
            >
              <span
                class="badge bg-secondary-subtle text-secondary align-self-center"
              >
                Tổng: {{ meta.total }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3" style="width: 120px">Mã</th>
                  <th>Tên</th>
                  <th style="width: 140px">Trạng thái</th>
                  <th style="width: 140px">Default</th>
                  <th class="text-end pe-3" style="width: 160px">Thao tác</th>
                </tr>
              </thead>

              <tbody v-if="items.length">
                <tr v-for="t in items" :key="t.id">
                  <td class="ps-3">
                    <span class="code-pill">{{ t.code }}</span>
                  </td>

                  <td>
                    <div class="fw-semibold">{{ t.name }}</div>
                  </td>

                  <td>
                    <button
                      class="badge badge-toggle"
                      type="button"
                      :class="t.status === 'actived' ? 'badge-on' : 'badge-off'"
                      :disabled="togglingIds.includes(t.id)"
                      @click="onToggleStatus(t)"
                    >
                      {{
                        togglingIds.includes(t.id)
                          ? "Đang đổi..."
                          : t.status === "actived"
                            ? "Bật"
                            : "Tắt"
                      }}
                    </button>
                  </td>

                  <td>
                    <i
                      v-if="t.default"
                      class="fa-solid fa-circle-check"
                      style="color: #16a34a"
                      title="Default"
                    ></i>
                    <span v-else class="opacity-50">--</span>
                  </td>

                  <td class="text-end pe-3">
                    <div class="d-flex justify-content-end gap-2">
                      <RouterLink
                        class="icon-btn icon-edit"
                        :to="{ name: 'tiers.edit', params: { id: t.id } }"
                        title="Chỉnh sửa"
                      >
                        <i class="fa-solid fa-pen-to-square"></i>
                      </RouterLink>

                      <button
                        class="icon-btn icon-delete"
                        title="Xóa"
                        @click="onDeleteClick(t.id)"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>

              <tbody v-else>
                <tr>
                  <td colspan="5" class="text-center py-5">
                    <div class="opacity-75">
                      <i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i>
                      Không có cấp phù hợp.
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="meta.total"
            class="d-flex justify-content-between align-items-center p-3 border-top"
          >
            <div class="small opacity-75">
              Hiển thị {{ (meta.current_page - 1) * meta.per_page + 1 }}
              -
              {{ Math.min(meta.current_page * meta.per_page, meta.total) }}
              /
              {{ meta.total }}
            </div>

            <div class="btn-group">
              <button
                class="btn btn-outline-secondary btn-sm"
                :disabled="page === 1"
                @click="page--"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="btn btn-outline-secondary btn-sm" disabled>
                Trang {{ page }}
              </button>
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
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import Swal from "sweetalert2";
import TierService from "../../services/tier.service";

const keyword = ref("");
const page = ref(1);
const perPage = 5;

const meta = ref({ current_page: 1, per_page: 10, total: 0, last_page: 1 });
const items = ref([]);
const togglingIds = ref([]);

async function fetchTiers() {
  try {
    const res = await TierService.getAll({
      q: keyword.value.trim() || undefined,
      page: page.value,
      per_page: perPage,
    });

    const list = res?.data?.items ?? res?.data ?? res?.items ?? [];
    items.value = Array.isArray(list) ? list : [];
    meta.value = res?.data?.meta ?? {
      current_page: 1,
      per_page: perPage,
      total: items.value.length,
      last_page: 1,
    };
  } catch (e) {
    Swal.fire("Lỗi", "Không thể tải tier", "error");
  }
}

onMounted(async () => {
  await fetchTiers();
});

watch(keyword, async () => {
  page.value = 1;
  await fetchTiers();
});

watch(page, async () => {
  await fetchTiers();
});

async function onDeleteClick(id) {
  const result = await Swal.fire({
    title: "Xóa tier này?",
    text: "Không thể hoàn tác!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Xóa",
    cancelButtonText: "Hủy",
  });

  if (!result.isConfirmed) return;

  try {
    await TierService.delete(id);
    await fetchTiers();
    Swal.fire({ title: "Xóa thành công", icon: "success" });
  } catch (e) {
    Swal.fire("Lỗi", e?.response?.data?.message || "Không thể xóa", "error");
  }
}

async function onToggleStatus(tier) {
  if (togglingIds.value.includes(tier.id)) return;

  togglingIds.value = [...togglingIds.value, tier.id];
  const nextStatus = tier.status === "actived" ? "disabled" : "actived";

  try {
    await TierService.update(tier.id, {
      name: tier.name,
      code: tier.code,
      status: nextStatus,
      is_default: !!tier.default,
    });

    tier.status = nextStatus;
  } catch (e) {
    await Swal.fire(
      "Lỗi",
      e?.response?.data?.message || "Không thể cập nhật trạng thái",
      "error"
    );
  } finally {
    togglingIds.value = togglingIds.value.filter((id) => id !== tier.id);
  }
}
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.code-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.3rem 0.6rem;
  border-radius: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.2px;
  background: color-mix(in srgb, var(--main-color) 14%, transparent);
  border: 1px solid var(--hover-border-color);
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

.badge-toggle {
  min-width: 88px;
  cursor: pointer;
}

.badge-toggle:disabled {
  opacity: 0.7;
  cursor: wait;
}

.icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 0.75rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border-color);
  background: transparent;
  text-decoration: none;
  transition: 0.12s ease;
}

.icon-btn:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}

.icon-add {
  color: #16a34a;
  width: 42px;
  height: 42px;
  border-radius: 1rem;
}

.icon-edit {
  color: #f59e0b;
}

.icon-delete {
  color: #ef4444;
}
</style>
