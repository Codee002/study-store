<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Tao phuong thuc thanh toan</h4>
          <div class="small opacity-75">
            Nhap ten phuong thuc va chon trang thai bat/tat
          </div>
        </div>

        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'payments.list' }">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lai
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <Form
            :validation-schema="schema"
            :initial-values="defaultValues"
            @submit="onSubmit"
            v-slot="{ isSubmitting, resetForm }"
          >
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Ten phuong thuc thanh toan</label>
                <Field name="name" v-slot="{ field, meta }">
                  <input
                    v-bind="field"
                    type="text"
                    class="form-control bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                    placeholder="Vi du: Thanh toan khi nhan hang, Chuyen khoan..."
                  />
                </Field>
                <ErrorMessage name="name" class="invalid-feedback d-block" />
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Trang thai</label>
                <Field name="status" v-slot="{ field, meta }">
                  <select
                    v-bind="field"
                    class="form-select bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                  >
                    <option value="actived">Bat</option>
                    <option value="disabled">Tat</option>
                  </select>
                </Field>
                <ErrorMessage name="status" class="invalid-feedback d-block" />
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-accent" type="submit" :disabled="isSubmitting">
                <i class="fa-solid fa-circle-plus me-1"></i>
                {{ isSubmitting ? "Dang tao..." : "Tao phuong thuc" }}
              </button>

              <button
                class="btn btn-outline-secondary"
                type="button"
                :disabled="isSubmitting"
                @click="onReset(resetForm)"
              >
                <i class="fa-solid fa-rotate-left me-1"></i> Reset
              </button>
            </div>
          </Form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from "vue-router";
import { Form, Field, ErrorMessage } from "vee-validate";
import * as yup from "yup";
import Swal from "sweetalert2";

import PaymentService from "../../services/payment.service";

const router = useRouter();

const defaultValues = {
  name: "",
  status: "actived",
};

const schema = yup.object({
  name: yup
    .string()
    .trim()
    .required("Vui long nhap ten phuong thuc thanh toan")
    .min(2, "Ten phuong thuc toi thieu 2 ky tu")
    .max(100, "Ten phuong thuc toi da 100 ky tu"),
  status: yup
    .string()
    .oneOf(["actived", "disabled"], "Trang thai khong hop le")
    .required("Vui long chon trang thai"),
});

function mapApiErrors(errorsObj = {}) {
  const mapped = {};
  Object.keys(errorsObj).forEach((k) => {
    mapped[k] = Array.isArray(errorsObj[k]) ? errorsObj[k][0] : String(errorsObj[k]);
  });
  return mapped;
}

function onReset(resetFormFn) {
  resetFormFn({ values: { ...defaultValues } });
}

async function onSubmit(values, { resetForm, setErrors }) {
  try {
    await PaymentService.create({
      name: String(values.name || "").trim(),
      status: values.status,
    });

    await Swal.fire("Thanh cong!", "Tao phuong thuc thanh toan thanh cong!", "success");
    resetForm({ values: { ...defaultValues } });
    router.push({ name: "payments.list" });
  } catch (e) {
    setErrors(mapApiErrors(e?.response?.data?.errors || {}));
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Tao phuong thuc thanh toan that bai. Vui long thu lai.";
    await Swal.fire("Tao phuong thuc thanh toan that bai", msg, "error");
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

.btn-accent {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
}
.btn-accent:hover {
  filter: var(--brightness);
}
</style>
