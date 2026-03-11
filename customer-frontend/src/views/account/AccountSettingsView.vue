<template>
  <div>
    <AppHeader :cart-count="cartCount" :user="headerUser" />

    <main class="container py-4">
      <section class="account-shell">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
          <div>
            <h1 class="account-title mb-1">Cài đặt tài khoản</h1>
            <p class="text-muted mb-0">Quản lý thông tin cá nhân, bảo mật và địa chỉ giao hàng</p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-lg-3">
            <div class="tab-nav">
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'profile' }"
                @click="activeTab = 'profile'"
              >
                <i class="fa-solid fa-user me-2"></i>Thông tin cá nhân
              </button>
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'security' }"
                @click="activeTab = 'security'"
              >
                <i class="fa-solid fa-shield-halved me-2"></i>Bảo mật
              </button>
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'delivery' }"
                @click="activeTab = 'delivery'"
              >
                <i class="fa-solid fa-location-dot me-2"></i>Địa chỉ giao hàng
              </button>
              <button
                type="button"
                class="tab-btn"
                :class="{ active: activeTab === 'dealer' }"
                @click="activeTab = 'dealer'"
              >
                <i class="fa-solid fa-store me-2"></i>Đăng ký đại lý
              </button>
            </div>
          </div>

          <div class="col-12 col-lg-9">
            <div v-if="activeTab === 'profile'" class="panel">
              <h5 class="mb-3">Thông tin cá nhân</h5>

              <Form
                :key="profileFormKey"
                :validation-schema="profileSchema"
                :initial-values="profileValues"
                @submit="submitProfile"
                v-slot="{ errors, isSubmitting }"
              >
                <div class="row g-3">
                  <div class="col-12">
                    <div class="avatar-box">
                      <img :src="avatarPreview" alt="avatar" class="avatar-preview" />
                      <div class="flex-grow-1">
                        <label class="form-label">Ảnh đại diện</label>
                        <input
                          class="form-control"
                          type="file"
                          accept="image/png,image/jpeg,image/jpg,image/webp"
                          @change="onAvatarChange"
                        />
                        <div v-if="avatarError" class="invalid-feedback d-block">{{ avatarError }}</div>
                        <div class="form-text">Định dạng jpg, jpeg, png, webp.</div>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Họ và tên</label>
                    <Field name="name" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập họ và tên"
                      />
                    </Field>
                    <ErrorMessage name="name" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <Field name="phone" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập số điện thoại"
                      />
                    </Field>
                    <ErrorMessage name="phone" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Tier hiện tại</label>
                    <input type="text" class="form-control" :value="currentTierDisplay" readonly />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Ngày sinh</label>
                    <Field name="birthday" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="date"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                      />
                    </Field>
                    <ErrorMessage name="birthday" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Giới tính</label>
                    <Field name="gender" v-slot="{ field, meta }">
                      <select
                        v-bind="field"
                        class="form-select"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                      >
                        <option value="">Chọn giới tính</option>
                        <option value="male">Nam</option>
                        <option value="female">Nữ</option>
                      </select>
                    </Field>
                    <ErrorMessage name="gender" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-12 d-flex gap-2 justify-content-end">
                    <button class="btn btn-main" type="submit" :disabled="isSubmitting">
                      {{ isSubmitting ? "Đang lưu..." : "Lưu thay đổi" }}
                    </button>
                  </div>
                </div>

                <div v-if="Object.keys(errors || {}).length" class="alert alert-danger mt-3 mb-0">
                  Vui lòng kiểm tra lại thông tin trước khi lưu.
                </div>
              </Form>
            </div>

            <div v-if="activeTab === 'security'" class="panel">
              <h5 class="mb-3">Thay đổi mật khẩu</h5>

              <Form
                :validation-schema="passwordSchema"
                :initial-values="passwordValues"
                @submit="submitPassword"
                v-slot="{ isSubmitting }"
              >
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Mật khẩu hiện tại</label>
                    <Field name="current_password" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="password"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập mật khẩu hiện tại"
                      />
                    </Field>
                    <ErrorMessage name="current_password" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Mật khẩu mới</label>
                    <Field name="password" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="password"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập mật khẩu mới"
                      />
                    </Field>
                    <ErrorMessage name="password" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Xác nhận mật khẩu mới</label>
                    <Field name="password_confirmation" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="password"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập lại mật khẩu mới"
                      />
                    </Field>
                    <ErrorMessage name="password_confirmation" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-main" type="submit" :disabled="isSubmitting">
                      {{ isSubmitting ? "Đang cập nhật..." : "Đổi mật khẩu" }}
                    </button>
                  </div>
                </div>
              </Form>
            </div>

            <div v-if="activeTab === 'delivery'" class="panel">
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <h5 class="mb-0">Danh sách địa chỉ giao hàng</h5>
                <button class="btn btn-main btn-sm" type="button" @click="openCreateAddressForm">
                  <i class="fa-solid fa-plus me-1"></i>Thêm địa chỉ
                </button>
              </div>

              <div v-if="showAddressForm" class="address-form mb-3">
                <h6 class="mb-3">{{ addressEditingId ? "Chỉnh sửa địa chỉ" : "Thêm địa chỉ mới" }}</h6>
                <Form
                  :key="addressFormKey"
                  :validation-schema="addressSchema"
                  :initial-values="addressValues"
                  @submit="submitAddress"
                  v-slot="{ isSubmitting }"
                >
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Tên người nhận</label>
                      <Field name="name" v-slot="{ field, meta }">
                        <input
                          v-bind="field"
                          type="text"
                          class="form-control"
                          :class="{ 'is-invalid': meta.touched && !meta.valid }"
                          placeholder="Nhập tên người nhận"
                        />
                      </Field>
                      <ErrorMessage name="name" class="invalid-feedback d-block" />
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Số điện thoại</label>
                      <Field name="phone" v-slot="{ field, meta }">
                        <input
                          v-bind="field"
                          type="text"
                          class="form-control"
                          :class="{ 'is-invalid': meta.touched && !meta.valid }"
                          placeholder="Nhập số điện thoại"
                        />
                      </Field>
                      <ErrorMessage name="phone" class="invalid-feedback d-block" />
                    </div>

                    <div class="col-12">
                      <label class="form-label">Địa chỉ nhận hàng</label>
                      <Field name="address" v-slot="{ field, meta }">
                        <textarea
                          v-bind="field"
                          rows="3"
                          class="form-control"
                          :class="{ 'is-invalid': meta.touched && !meta.valid }"
                          placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố..."
                        ></textarea>
                      </Field>
                      <ErrorMessage name="address" class="invalid-feedback d-block" />
                    </div>

                    <div class="col-12">
                      <label class="form-check-label d-flex align-items-center gap-2">
                        <Field name="default" type="checkbox" class="form-check-input mt-0" />
                        Đặt làm địa chỉ mặc định
                      </label>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                      <button
                        class="btn btn-outline-secondary"
                        type="button"
                        :disabled="isSubmitting"
                        @click="cancelAddressForm"
                      >
                        Hủy
                      </button>
                      <button class="btn btn-main" type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? "Đang lưu..." : addressEditingId ? "Cập nhật địa chỉ" : "Thêm địa chỉ" }}
                      </button>
                    </div>
                  </div>
                </Form>
              </div>

              <div v-if="!deliveryInfos.length" class="text-muted small">
                Bạn chưa có địa chỉ giao hàng nào.
              </div>

              <div v-else class="address-grid">
                <article v-for="item in deliveryInfos" :key="item.id" class="address-card">
                  <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                      <div class="fw-semibold">{{ item.name }} - {{ item.phone }}</div>
                      <div class="small text-muted mt-1">{{ item.address }}</div>
                    </div>
                    <span v-if="item.default" class="badge text-bg-warning">Mặc định</span>
                  </div>

                  <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-outline-secondary btn-sm" @click="openEditAddressForm(item)">
                      Chỉnh sửa
                    </button>
                    <button
                      class="btn btn-outline-dark btn-sm"
                      :disabled="Boolean(item.default)"
                      @click="setDefaultAddress(item.id)"
                    >
                      Đặt mặc định
                    </button>
                  </div>
                </article>
              </div>
            </div>

            <div v-if="activeTab === 'dealer'" class="panel">
              <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-3">
                <div>
                  <h5 class="mb-1">Đăng ký trở thành đại lý</h5>
                  <p class="text-muted mb-0 small">
                    Chọn tier đang kích hoạt và nhập thông tin doanh nghiệp để gửi yêu cầu xét duyệt.
                  </p>
                </div>
                <span v-if="dealerProfile?.status" class="badge" :class="dealerStatusBadgeClass">
                  {{ dealerStatusLabel }}
                </span>
              </div>

              <div v-if="dealerProfile?.status === 'pending'" class="alert alert-warning py-2">
                Yêu cầu đăng ký đại lý của bạn đang chờ admin duyệt.
              </div>
              <div v-else-if="dealerProfile?.status === 'accepted'" class="alert alert-success py-2">
                Tài khoản của bạn đã được duyệt đại lý.
              </div>
              <div v-else-if="dealerProfile?.status === 'rejected'" class="alert alert-danger py-2">
                Yêu cầu trước đó bị từ chối. Bạn có thể cập nhật thông tin và gửi lại.
              </div>

              <Form
                :key="dealerFormKey"
                :validation-schema="dealerSchema"
                :initial-values="dealerValues"
                @submit="submitDealerRegistration"
                v-slot="{ isSubmitting }"
              >
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Loại Tier</label>
                    <Field name="tier_id" v-slot="{ field, meta }">
                      <select
                        v-bind="field"
                        class="form-select"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        :disabled="!canSubmitDealerRegistration || isSubmitting"
                      >
                        <option value="">Chọn tier</option>
                        <option v-for="tier in dealerTiers" :key="tier.id" :value="String(tier.id)">
                          {{ tier.name }} ({{ tier.code }})
                        </option>
                      </select>
                    </Field>
                    <ErrorMessage name="tier_id" class="invalid-feedback d-block" />
                    <div v-if="!dealerTiers.length" class="form-text text-danger">
                      Hiện chưa có tier đang kích hoạt để đăng ký.
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Mã số thuế</label>
                    <Field name="tax_code" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập mã số thuế"
                        :disabled="!canSubmitDealerRegistration || isSubmitting"
                      />
                    </Field>
                    <ErrorMessage name="tax_code" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-12">
                    <label class="form-label">Tên công ty</label>
                    <Field name="company_name" v-slot="{ field, meta }">
                      <input
                        v-bind="field"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập tên công ty"
                        :disabled="!canSubmitDealerRegistration || isSubmitting"
                      />
                    </Field>
                    <ErrorMessage name="company_name" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-12">
                    <label class="form-label">Địa chỉ công ty</label>
                    <Field name="company_address" v-slot="{ field, meta }">
                      <textarea
                        v-bind="field"
                        rows="3"
                        class="form-control"
                        :class="{ 'is-invalid': meta.touched && !meta.valid }"
                        placeholder="Nhập địa chỉ công ty"
                        :disabled="!canSubmitDealerRegistration || isSubmitting"
                      ></textarea>
                    </Field>
                    <ErrorMessage name="company_address" class="invalid-feedback d-block" />
                  </div>

                  <div class="col-12 d-flex justify-content-end">
                    <button
                      class="btn btn-main"
                      type="submit"
                      :disabled="!canSubmitDealerRegistration || isSubmitting || !dealerTiers.length"
                    >
                      {{
                        isSubmitting
                          ? "Đang gửi..."
                          : dealerProfile?.status === "rejected"
                            ? "Gửi lại yêu cầu"
                            : "Gửi đăng ký"
                      }}
                    </button>
                  </div>
                </div>
              </Form>
            </div>
          </div>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { Form, Field, ErrorMessage } from "vee-validate";
import * as yup from "yup";
import Swal from "sweetalert2";
import AppHeader from "@/components/layout/AppHeader.vue";
import AppFooter from "@/components/layout/AppFooter.vue";
import authService from "@/services/auth.service";
import profileService from "@/services/profile.service";
import cartService from "@/services/cart.service";

const activeTab = ref("profile");
const cartCount = ref(0);
const profile = ref(null);
const currentTier = ref(null);
const deliveryInfos = ref([]);
const dealerTiers = ref([]);
const dealerProfile = ref(null);

const selectedAvatarFile = ref(null);
const avatarPreview = ref("/default-user-avatar.svg");
const avatarError = ref("");
const profileFormKey = ref(0);
const dealerFormKey = ref(0);

const showAddressForm = ref(false);
const addressEditingId = ref(0);
const addressFormKey = ref(0);

const headerUser = computed(() => ({
  name: profile.value?.name || "Guest",
  avatar: profile.value?.avatar || "/default-user-avatar.svg",
}));

const currentTierDisplay = computed(() => {
  if (!currentTier.value) return "Chưa có tier";
  const name = currentTier.value?.name || "";
  const code = currentTier.value?.code || "";
  return code ? `${name} (${code})` : name || "Chưa có tier";
});

const profileValues = computed(() => ({
  name: profile.value?.name || "",
  phone: profile.value?.phone || "",
  birthday: profile.value?.birthday || "",
  gender: profile.value?.gender || "",
}));

const dealerValues = computed(() => ({
  tier_id: dealerProfile.value?.tier_id ? String(dealerProfile.value.tier_id) : "",
  company_name: dealerProfile.value?.company_name || "",
  company_address: dealerProfile.value?.company_address || "",
  tax_code: dealerProfile.value?.tax_code || "",
}));

const canSubmitDealerRegistration = computed(
  () => !["pending", "accepted"].includes(String(dealerProfile.value?.status || "")),
);

const dealerStatusLabel = computed(() => {
  const status = String(dealerProfile.value?.status || "");
  if (status === "pending") return "Chờ duyệt";
  if (status === "accepted") return "Đã duyệt";
  if (status === "rejected") return "Từ chối";
  return "";
});

const dealerStatusBadgeClass = computed(() => {
  const status = String(dealerProfile.value?.status || "");
  if (status === "pending") return "text-bg-warning";
  if (status === "accepted") return "text-bg-success";
  if (status === "rejected") return "text-bg-danger";
  return "text-bg-secondary";
});

const passwordValues = {
  current_password: "",
  password: "",
  password_confirmation: "",
};

const addressValues = ref({
  name: "",
  phone: "",
  address: "",
  default: false,
});

const phoneRegex = /^(0|\+84)(3|5|7|8|9)\d{8}$/;

const profileSchema = yup.object({
  name: yup
    .string()
    .trim()
    .required("Vui lòng nhập họ và tên")
    .min(2, "Họ và tên tối thiểu 2 ký tự")
    .max(100, "Họ và tên tối đa 100 ký tự"),
  phone: yup
    .string()
    .required("Vui lòng nhập số điện thoại")
    .matches(phoneRegex, "Số điện thoại không đúng định dạng Việt Nam"),
  birthday: yup.string().nullable(),
  gender: yup.string().nullable().oneOf(["", "male", "female"], "Giới tính không hợp lệ"),
});

const passwordSchema = yup.object({
  current_password: yup.string().required("Vui lòng nhập mật khẩu hiện tại"),
  password: yup.string().required("Vui lòng nhập mật khẩu mới").min(6, "Mật khẩu mới tối thiểu 6 ký tự"),
  password_confirmation: yup
    .string()
    .required("Vui lòng nhập lại mật khẩu mới")
    .oneOf([yup.ref("password")], "Mật khẩu xác nhận không khớp"),
});

const addressSchema = yup.object({
  name: yup
    .string()
    .trim()
    .required("Vui lòng nhập tên người nhận")
    .min(2, "Tên người nhận tối thiểu 2 ký tự")
    .max(100, "Tên người nhận tối đa 100 ký tự"),
  phone: yup
    .string()
    .required("Vui lòng nhập số điện thoại")
    .matches(phoneRegex, "Số điện thoại không đúng định dạng Việt Nam"),
  address: yup
    .string()
    .trim()
    .required("Vui lòng nhập địa chỉ giao hàng")
    .min(5, "Địa chỉ giao hàng tối thiểu 5 ký tự")
    .max(500, "Địa chỉ giao hàng tối đa 500 ký tự"),
  default: yup.boolean().default(false),
});

const dealerSchema = yup.object({
  tier_id: yup.string().required("Vui lòng chọn tier"),
  company_name: yup
    .string()
    .trim()
    .required("Vui lòng nhập tên công ty")
    .min(2, "Tên công ty tối thiểu 2 ký tự")
    .max(255, "Tên công ty tối đa 255 ký tự"),
  company_address: yup
    .string()
    .trim()
    .required("Vui lòng nhập địa chỉ công ty")
    .min(5, "Địa chỉ công ty tối thiểu 5 ký tự")
    .max(500, "Địa chỉ công ty tối đa 500 ký tự"),
  tax_code: yup.string().trim().required("Vui lòng nhập mã số thuế").min(5, "Mã số thuế tối thiểu 5 ký tự").max(50, "Mã số thuế tối đa 50 ký tự"),
});

function mapServerErrors(error, setErrors) {
  const errorsObj = error?.response?.data?.errors || {};
  const mapped = {};
  Object.keys(errorsObj).forEach((key) => {
    mapped[key] = Array.isArray(errorsObj[key]) ? errorsObj[key][0] : String(errorsObj[key]);
  });
  setErrors(mapped);
}

function onAvatarChange(event) {
  avatarError.value = "";
  const file = event?.target?.files?.[0];
  if (!file) {
    selectedAvatarFile.value = null;
    avatarPreview.value = profile.value?.avatar || avatarPreview.value;
    return;
  }

  const allowed = ["image/jpeg", "image/jpg", "image/png", "image/webp"];
  if (!allowed.includes(file.type)) {
    avatarError.value = "Ảnh đại diện chỉ hỗ trợ jpg, jpeg, png, webp";
    selectedAvatarFile.value = null;
    return;
  }

  selectedAvatarFile.value = file;
  avatarPreview.value = URL.createObjectURL(file);
}

async function submitProfile(values, { setErrors }) {
  try {
    if (avatarError.value) return;
    const formData = new FormData();
    formData.append("name", values.name);
    formData.append("phone", values.phone);
    if (values.birthday) formData.append("birthday", values.birthday);
    if (values.gender) formData.append("gender", values.gender);
    if (selectedAvatarFile.value) formData.append("avatar", selectedAvatarFile.value);

    const res = await profileService.updateProfile(formData);
    profile.value = res?.data?.profile || profile.value;
    selectedAvatarFile.value = null;
    avatarPreview.value = profile.value?.avatar || avatarPreview.value;
    try {
      const currentUser = JSON.parse(localStorage.getItem("currentUser") || "{}");
      localStorage.setItem(
        "currentUser",
        JSON.stringify({
          ...currentUser,
          name: profile.value?.name || currentUser?.name || "Guest",
          avatar: profile.value?.avatar || currentUser?.avatar || "",
          profile: {
            ...(currentUser?.profile || {}),
            ...(profile.value || {}),
          },
        }),
      );
      window.dispatchEvent(new Event("customer-user-updated"));
    } catch {
      // ignore local sync errors
    }

    await Swal.fire("Thành công!", res?.message || "Cập nhật thông tin thành công!", "success");
  } catch (e) {
    mapServerErrors(e, setErrors);
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Cập nhật thông tin thất bại. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function submitPassword(values, { setErrors, resetForm }) {
  try {
    const res = await profileService.changePassword(values);
    resetForm({ values: passwordValues });
    await Swal.fire("Thành công!", res?.message || "Đổi mật khẩu thành công!", "success");
  } catch (e) {
    mapServerErrors(e, setErrors);
    const msg = e?.response?.data?.message || e?.response?.data?.error || "Đổi mật khẩu thất bại.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

function openCreateAddressForm() {
  addressEditingId.value = 0;
  addressValues.value = {
    name: "",
    phone: "",
    address: "",
    default: deliveryInfos.value.length === 0,
  };
  addressFormKey.value += 1;
  showAddressForm.value = true;
}

function openEditAddressForm(item) {
  addressEditingId.value = Number(item?.id || 0);
  addressValues.value = {
    name: item?.name || "",
    phone: item?.phone || "",
    address: item?.address || "",
    default: Boolean(item?.default),
  };
  addressFormKey.value += 1;
  showAddressForm.value = true;
}

function cancelAddressForm() {
  showAddressForm.value = false;
  addressEditingId.value = 0;
}

async function submitAddress(values, { setErrors }) {
  try {
    if (addressEditingId.value) {
      await profileService.updateDeliveryInfo(addressEditingId.value, values);
      await Swal.fire("Thành công!", "Cập nhật địa chỉ thành công!", "success");
    } else {
      await profileService.createDeliveryInfo(values);
      await Swal.fire("Thành công!", "Thêm địa chỉ thành công!", "success");
    }

    cancelAddressForm();
    await loadDeliveryInfos();
  } catch (e) {
    mapServerErrors(e, setErrors);
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Lưu địa chỉ thất bại. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function setDefaultAddress(id) {
  try {
    const res = await profileService.setDefaultDeliveryInfo(id);
    await Swal.fire("Thành công!", res?.message || "Đặt địa chỉ mặc định thành công!", "success");
    await loadDeliveryInfos();
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.error || "Không thể đặt địa chỉ mặc định.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function submitDealerRegistration(values, { setErrors }) {
  try {
    if (!dealerTiers.value.length) {
      setErrors({ tier_id: "Hiện chưa có tier đang kích hoạt để đăng ký" });
      return;
    }

    const res = await profileService.registerDealer({
      tier_id: Number(values.tier_id),
      company_name: values.company_name,
      company_address: values.company_address,
      tax_code: values.tax_code,
    });

    dealerProfile.value = res?.data || dealerProfile.value;
    dealerFormKey.value += 1;
    await Swal.fire("Thành công!", res?.message || "Gửi đăng ký đại lý thành công!", "success");
  } catch (e) {
    mapServerErrors(e, setErrors);
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Đăng ký đại lý thất bại. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function loadProfile() {
  try {
    const meRes = await authService.me();
    const payload = meRes?.data ?? meRes ?? {};
    const meUser = payload?.user ?? payload ?? {};
    const meProfile = meUser?.profile ?? payload?.profile ?? null;

    profile.value = meProfile;
    currentTier.value = meUser?.tier ?? null;
    avatarPreview.value = meProfile?.avatar || meUser?.avatar || "/default-user-avatar.svg";
    profileFormKey.value += 1;
  } catch {
    profile.value = null;
    currentTier.value = null;
    avatarPreview.value = "/default-user-avatar.svg";
  }
}

async function loadDeliveryInfos() {
  try {
    deliveryInfos.value = await profileService.getDeliveryInfos();
  } catch {
    deliveryInfos.value = [];
  }
}

async function loadDealerRegistrationMeta() {
  try {
    const res = await profileService.getDealerRegistrationMeta();
    const data = res?.data || {};
    dealerTiers.value = Array.isArray(data?.tiers) ? data.tiers : [];
    dealerProfile.value = data?.dealer_profile || null;
    dealerFormKey.value += 1;
  } catch {
    dealerTiers.value = [];
    dealerProfile.value = null;
  }
}

onMounted(async () => {
  try {
    cartCount.value = await cartService.getCount();
  } catch {
    cartCount.value = 0;
  }

  await loadProfile();
  await loadDeliveryInfos();
  await loadDealerRegistrationMeta();
});
</script>

<style scoped>
.account-shell {
  min-height: 65vh;
}

.account-title {
  font-size: 1.55rem;
  font-weight: 800;
}

.panel {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 16px;
}

.tab-nav {
  display: grid;
  gap: 8px;
}

.tab-btn {
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
  border-radius: 12px;
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  color: var(--font-color);
}

.tab-btn.active {
  background: var(--main-color);
  border-color: var(--hover-border-color);
  color: var(--dark);
}

.avatar-box {
  display: flex;
  gap: 12px;
  align-items: center;
}

.avatar-preview {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-color);
  flex-shrink: 0;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}

.btn-main:hover {
  filter: var(--brightness);
}

.address-form {
  border: 1px dashed var(--border-color);
  border-radius: 12px;
  padding: 12px;
}

.address-grid {
  display: grid;
  gap: 12px;
}

.address-card {
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 12px;
}

@media (max-width: 767px) {
  .avatar-box {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
