<template>
  <div class="min-vh-100 d-flex align-items-center bg-light">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
          <div class="card shadow-sm border-0">
            <div class="card-body p-4">
              <h1 class="h4 text-center">Đăng nhập</h1>
              <p class="text-center text-muted mb-4">Chào mừng quay lại</p>

              <Form
                :validation-schema="schema"
                @submit="onSubmit"
                v-slot="{ errors, isSubmitting }"
                novalidate
              >
                <!-- Username -->
                <div class="mb-3">
                  <label class="form-label" for="username">Username</label>
                  <div class="input-group">
                    <span class="input-group-text"
                      ><i class="fa-solid fa-user"></i
                    ></span>
                    <Field
                      id="username"
                      name="username"
                      type="text"
                      class="form-control"
                      :class="{ 'is-invalid': errors.username }"
                      placeholder="Nhập username"
                      autocomplete="username"
                    />
                  </div>
                  <div v-if="errors.username" class="invalid-feedback d-block">
                    {{ errors.username }}
                  </div>
                </div>

                <!-- Password -->
                <div class="mb-3">
                  <label class="form-label" for="password">Mật khẩu</label>
                  <div class="input-group">
                    <span class="input-group-text"
                      ><i class="fa-solid fa-lock"></i
                    ></span>
                    <Field
                      id="password"
                      name="password"
                      :type="showPassword ? 'text' : 'password'"
                      class="form-control"
                      :class="{ 'is-invalid': errors.password }"
                      placeholder="Nhập mật khẩu"
                      autocomplete="current-password"
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
                  <div v-if="errors.password" class="invalid-feedback d-block">
                    {{ errors.password }}
                  </div>
                </div>

                <!-- Remember -->
                <div
                  class="d-flex justify-content-between align-items-center mb-3"
                ></div>

                <button
                  class="btn btn-primary w-100"
                  type="submit"
                  :disabled="isSubmitting"
                >
                  <i class="fa-solid fa-right-to-bracket me-2"></i>
                  {{ isSubmitting ? "Đang đăng nhập..." : "Đăng nhập" }}
                </button>

                <div class="text-center mt-3">
                  <span class="text-muted">Chưa có tài khoản?</span>
                  <RouterLink class="text-decoration-none ms-1" to="/register"
                    >Đăng ký</RouterLink
                  >
                </div>
                <a
                  href="#"
                  class="text-decoration-none text-center d-block mt-2"
                  >Quên mật khẩu?</a
                >
              </Form>

              <div v-if="serverError" class="alert alert-danger mt-3 mb-0">
                {{ serverError }}
              </div>
            </div>
          </div>

          <p class="text-center text-muted small mt-3 mb-0">
            © {{ new Date().getFullYear() }} Study Store
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

const showPassword = ref(false);
const serverError = ref("");

const schema = yup.object({
  username: yup.string().required("Vui lòng nhập username"),
  password: yup.string().required("Vui lòng nhập mật khẩu"),
  remember: yup.boolean().default(false),
});

async function onSubmit(values, { setSubmitting }) {
  serverError.value = "";
  try {
    // TODO: call API Laravel login (username + password)
    // await authApi.login(values)
    console.log("LOGIN", values);
  } catch (e) {
    serverError.value = "Đăng nhập thất bại. Vui lòng kiểm tra lại.";
  } finally {
    setSubmitting(false);
  }
}
</script>
