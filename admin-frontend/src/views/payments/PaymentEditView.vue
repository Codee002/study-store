<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Chinh sua phuong thuc thanh toan</h4>
          <div class="small opacity-75">Cap nhat ten va trang thai bat/tat</div>
        </div>

        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'payments.list' }">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lai
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-4 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i> Dang tai du lieu...
          </div>

          <Form
            v-else
            :key="formKey"
            :initial-values="initialValues"
            :validation-schema="schema"
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
                <i class="fa-solid fa-floppy-disk me-1"></i>
                {{ isSubmitting ? "Dang luu..." : "Luu thay doi" }}
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
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Form, Field, ErrorMessage } from "vee-validate";
import * as yup from "yup";
import Swal from "sweetalert2";

import PaymentService from "../../services/payment.service";

const route = useRoute();
const router = useRouter();
const id = route.params.id;

const loading = ref(true);
const formKey = ref(0);
const initialValues = ref({ name: "", status: "actived" });
const originalValues = ref({ name: "", status: "actived" });

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

async function fetchPayment() {
  loading.value = true;
  try {
    const res = await PaymentService.get(id);
    const data = res?.data ?? res;

    originalValues.value = {
      name: data?.name ?? "",
      status: data?.status ?? "actived",
    };
    initialValues.value = { ...originalValues.value };
    formKey.value += 1;
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Khong the tai phuong thuc thanh toan.";
    await Swal.fire("Loi", msg, "error");
    router.push({ name: "payments.list" });
  } finally {
    loading.value = false;
  }
}

function onReset(resetFormFn) {
  resetFormFn({ values: { ...originalValues.value } });
}

async function onSubmit(values, { resetForm, setErrors }) {
  try {
    const payload = {
      name: String(values.name || "").trim(),
      status: values.status,
    };

    await PaymentService.update(id, payload);
    await Swal.fire("Thanh cong!", "Cap nhat phuong thuc thanh toan thanh cong!", "success");

    originalValues.value = { ...payload };
    resetForm({ values: { ...payload } });
    router.push({ name: "payments.list" });
  } catch (e) {
    setErrors(mapApiErrors(e?.response?.data?.errors || {}));
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Cap nhat phuong thuc thanh toan that bai.";
    await Swal.fire("Cap nhat phuong thuc thanh toan that bai", msg, "error");
  }
}

onMounted(fetchPayment);
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
