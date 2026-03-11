<template>
  <div class="min-vh-100 d-flex align-items-center register-page">
    <div class="container mt-4 mb-4">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
          <div class="card shadow-sm register-card">
            <div class="card-body p-4">
              <div class="text-center">
                <span class="register-header-badge"> Đăng ký tài khoản </span>
              </div>

              <p class="subtext mb-4 text-center">
                Tạo tài khoản để mua sắm nhanh hơn.
              </p>

              <div v-if="serverError" class="alert alert-danger mt-3 mb-0">
                {{ serverError }}
              </div>
              <Form
                :validation-schema="schema"
                @submit="onSubmit"
                v-slot="{ errors, isSubmitting }"
                :initial-values="{ agree: false }"
                novalidate
              >
                <!-- 1) Email -->
                <AppField
                  name="email"
                  label="Email"
                  placeholder="user@email.com"
                  autocomplete="email"
                  icon="fa-solid fa-envelope"
                />

                <!-- 2) SĐT -->
                <AppField
                  name="phone"
                  label="Số điện thoại"
                  placeholder="0912345678"
                  autocomplete="tel"
                  icon="fa-solid fa-phone"
                />

                <AppField
                  name="name"
                  label="Họ tên"
                  placeholder="Trần Thanh Phúc"
                  autocomplete="name"
                  icon="fa-solid fa-id-card"
                />

                <!-- 3) Username -->
                <AppField
                  name="username"
                  label="Username"
                  placeholder="tranthanhphuc123"
                  autocomplete="username"
                  icon="fa-solid fa-user"
                  hint="6–30 ký tự, chỉ chữ và số"
                />

                <!-- 4) Password + Confirm -->
                <div class="row">
                  <div class="col-md-6">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <div class="input-group mb-3">
                      <span class="input-group-text"
                        ><i class="fa-solid fa-lock"></i
                      ></span>
                      <Field
                        id="password"
                        name="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="form-control"
                        :class="{ 'is-invalid': errors.password }"
                        placeholder="Tối thiểu 6 ký tự"
                        autocomplete="new-password"
                      />
                      <button
                        type="button"
                        class="btn btn-outline-secondary"
                        @click="showPassword = !showPassword"
                      >
                        <i
                          :class="
                            showPassword
                              ? 'fa-solid fa-eye-slash'
                              : 'fa-solid fa-eye'
                          "
                        ></i>
                      </button>
                    </div>
                    <div
                      v-if="errors.password"
                      class="invalid-feedback d-block"
                    >
                      {{ errors.password }}
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="password_confirmation"
                      >Nhập lại mật khẩu</label
                    >
                    <div class="input-group mb-3">
                      <span class="input-group-text"
                        ><i class="fa-solid fa-shield-halved"></i
                      ></span>
                      <Field
                        id="password_confirmation"
                        name="password_confirmation"
                        :type="showConfirm ? 'text' : 'password'"
                        class="form-control"
                        :class="{ 'is-invalid': errors.password_confirmation }"
                        placeholder="Nhập lại mật khẩu"
                        autocomplete="new-password"
                      />
                      <button
                        type="button"
                        class="btn btn-outline-secondary"
                        @click="showConfirm = !showConfirm"
                      >
                        <i
                          :class="
                            showConfirm
                              ? 'fa-solid fa-eye-slash'
                              : 'fa-solid fa-eye'
                          "
                        ></i>
                      </button>
                    </div>
                    <div
                      v-if="errors.password_confirmation"
                      class="invalid-feedback d-block"
                    >
                      {{ errors.password_confirmation }}
                    </div>
                  </div>
                </div>

                <!-- 5) Ngày sinh + Giới tính  -->
                <div class="mb-3">
                  <label class="form-label" for="birthday">Ngày sinh</label>
                  <div class="input-group">
                    <span class="input-group-text"
                      ><i class="fa-solid fa-cake-candles"></i
                    ></span>
                    <Field
                      id="birthday"
                      name="birthday"
                      type="date"
                      class="form-control"
                      :class="{ 'is-invalid': errors.birthday }"
                    />
                  </div>
                  <div v-if="errors.birthday" class="invalid-feedback d-block">
                    {{ errors.birthday }}
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Giới tính</label>
                  <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                      <Field
                        class="form-check-input"
                        type="radio"
                        name="gender"
                        id="g_m"
                        value="male"
                      />
                      <label class="form-check-label" for="g_m">Nam</label>
                    </div>
                    <div class="form-check">
                      <Field
                        class="form-check-input"
                        type="radio"
                        name="gender"
                        id="g_f"
                        value="female"
                      />
                      <label class="form-check-label" for="g_f">Nữ</label>
                    </div>
                  </div>
                  <div v-if="errors.gender" class="invalid-feedback d-block">
                    {{ errors.gender }}
                  </div>
                </div>

                <div class="form-check mb-3">
                  <Field
                    id="agree"
                    name="agree"
                    type="checkbox"
                    :value="true"
                    :unchecked-value="false"
                    class="form-check-input"
                  />
                  <label class="form-check-label" for="agree">
                    Tôi đồng ý với
                    <a href="#" class="text-decoration-none">điều khoản</a>
                  </label>

                  <div v-if="errors.agree" class="invalid-feedback d-block">
                    {{ errors.agree }}
                  </div>
                </div>

                <button
                  class="btn btn-main w-100"
                  type="submit"
                  :disabled="isSubmitting"
                >
                  <i class="fa-solid fa-user-plus me-2"></i>
                  {{ isSubmitting ? "Đang tạo tài khoản..." : "Tạo tài khoản" }}
                </button>

                <div class="text-center mt-3">
                  <span class="text-muted">Đã có tài khoản?</span>
                  <RouterLink class="text-decoration-none ms-1" to="/login"
                    >Đăng nhập</RouterLink
                  >
                </div>
              </Form>
            </div>
          </div>

          <p class="text-center text-muted small mt-3 mb-0">
            Bằng việc đăng ký, bạn có thể hưởng giá theo tier (nếu là dealer)
            sau khi được duyệt.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { Form, Field } from "vee-validate";
import * as yup from "yup";
import AppField from "@/components/form/AppField.vue";
import authService from "@/services/auth.service";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

const router = useRouter();
const showPassword = ref(false);
const showConfirm = ref(false);
const serverError = ref("");

const phoneRegex = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
const usernameRegex = /^[A-Za-z][A-Za-z0-9]{5,29}$/;
const nameRegex = /^[A-Za-zÀ-ỹ\s]+$/;

const schema = yup.object({
  email: yup
    .string()
    .required("Vui lòng nhập email")
    .email("Email không hợp lệ"),
  phone: yup
    .string()
    .required("Vui lòng nhập số điện thoại")
    .matches(phoneRegex, "Số điện thoại không hợp lệ"),
  name: yup
    .string()
    .trim()
    .required("Vui lòng nhập họ và tên")
    .matches(nameRegex, "Họ và tên chỉ được chứa chữ và khoảng trắng"),
  username: yup
    .string()
    .required("Vui lòng nhập username")
    .matches(
      usernameRegex,
      "Username phải bắt đầu bằng chữ và chỉ gồm chữ và số (6–30 ký tự)"
    ),
  password: yup
    .string()
    .required("Vui lòng nhập mật khẩu")
    .min(6, "Mật khẩu tối thiểu 6 ký tự"),
  password_confirmation: yup
    .string()
    .required("Vui lòng nhập lại mật khẩu")
    .oneOf([yup.ref("password")], "Mật khẩu nhập lại không khớp"),
  birthday: yup
    .date()
    .typeError("Ngày sinh không hợp lệ")
    .required("Vui lòng chọn ngày sinh")
    .max(new Date(), "Ngày sinh không được lớn hơn hôm nay"),
  gender: yup
    .string()
    .required("Vui lòng chọn giới tính")
    .oneOf(["male", "female"]),
  agree: yup.boolean().oneOf([true], "Bạn cần đồng ý điều khoản để tiếp tục"),
});

async function onSubmit(values, actions) {
  serverError.value = "";
  try {
    const res = await authService.register(values);
    await Swal.fire(
      "Thành công!",
      "Đăng ký thành công! Vui lòng đăng nhập",
      "success"
    );
    router.push("/login");
  } catch (e) {
    serverError.value =
      e?.response?.data?.message || "Đăng ký thất bại. Vui lòng thử lại.";

    const errors = e?.response?.data?.errors;
    const mapped = Object.fromEntries(
      Object.entries(errors).map(([field, messages]) => [
        field,
        Array.isArray(messages) ? messages[0] : String(messages),
      ])
    );
    actions.setErrors(mapped);
    Swal.fire("Lỗi", e.response?.data?.message || "Đăng ký thất bại", "error");
    console.log("Register error:", e);
  }
}
</script>

<style scoped>
.register-page {
  background: var(--main-bg);
}

.register-card {
  border-radius: 18px;
  border: 1px solid var(--hover-border-color);
  background: var(--main-extra-bg);
}

.register-header-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.75rem;
  font-weight: 700;
  font-size: 1.5rem;
}

.subtext {
  color: var(--font-extra-color) !important;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 800;
  border-radius: 14px;
  padding: 0.75rem 1rem;
}

.btn-main:hover {
  border-color: var(--hover-border-color);
}

.card-body :deep(.input-group-text) {
  background: var(--main-extra-bg);
  border-color: var(--border-color);
}

.card-body :deep(.form-control) {
  background: var(--main-extra-bg);
  border-color: var(--border-color);
  color: var(--font-color);
}

.card-body :deep(.form-control:focus) {
  box-shadow: 0 0 0 0.2rem rgba(242, 196, 149, 0.35);
  border-color: var(--hover-border-color);
}

.card-body :deep(.invalid-feedback) {
  font-size: 0.9rem;
}

.card-body :deep(.form-control.is-invalid),
.card-body :deep(.form-select.is-invalid),
.card-body :deep(.form-check-input.is-invalid) {
  border-color: var(--bs-danger) !important;
}

.card-body :deep(.form-control.is-invalid:focus),
.card-body :deep(.form-select.is-invalid:focus),
.card-body :deep(.form-check-input.is-invalid:focus) {
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important; /* đỏ nhạt */
}

.card-body :deep(.input-group .form-control.is-invalid) {
  z-index: 3; /* tránh bị che viền */
}
</style>
