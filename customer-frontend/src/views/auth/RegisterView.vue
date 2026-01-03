<template>
  <div class="min-vh-100 d-flex align-items-center bg-light">
    <div class="container mt-4 mb-4">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
          <div class="card shadow-sm border-0">
            <div class="card-body p-4">
              <h1 class="h4 mb-2 text-center">Đăng ký tài khoản</h1>
              <p class="text-muted mb-4 text-center">
                Tạo tài khoản để mua sắm nhanh hơn.
              </p>

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

                <!-- 3) Username -->
                <AppField
                  name="username"
                  label="Username"
                  placeholder="stationery_01"
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

                <!-- 5) Ngày sinh + Giới tính (giới tính nằm dưới, full width) -->
                <div class="mb-3">
                  <label class="form-label" for="dob">Ngày sinh</label>
                  <div class="input-group">
                    <span class="input-group-text"
                      ><i class="fa-solid fa-cake-candles"></i
                    ></span>
                    <Field
                      id="dob"
                      name="dob"
                      type="date"
                      class="form-control"
                      :class="{ 'is-invalid': errors.dob }"
                    />
                  </div>
                  <div v-if="errors.dob" class="invalid-feedback d-block">
                    {{ errors.dob }}
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
                  class="btn btn-success w-100"
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

              <div v-if="serverError" class="alert alert-danger mt-3 mb-0">
                {{ serverError }}
              </div>
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

const showPassword = ref(false);
const showConfirm = ref(false);
const serverError = ref("");

const phoneRegex = /^(0|\+84)(3|5|7|8|9)\d{8}$/;
const usernameRegex = /^[A-Za-z][A-Za-z0-9]{5,29}$/;

const schema = yup.object({
  email: yup
    .string()
    .required("Vui lòng nhập email")
    .email("Email không hợp lệ"),
  phone: yup
    .string()
    .required("Vui lòng nhập số điện thoại")
    .matches(phoneRegex, "Số điện thoại không hợp lệ"),
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
  dob: yup
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

async function onSubmit(values, { setSubmitting }) {
  serverError.value = "";
  try {
    // TODO: call API Laravel register
    console.log("REGISTER", values);
  } catch (e) {
    serverError.value = "Đăng ký thất bại. Vui lòng thử lại.";
  } finally {
    setSubmitting(false);
  }
}
</script>
