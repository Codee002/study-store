<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Chỉnh sửa khuyến mãi</h4>
          <div class="small opacity-75">Cập nhật thông tin khuyến mãi theo danh mục</div>
        </div>

        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'discounts.list' }">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <div v-if="loading" class="py-4 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i> Đang tải dữ liệu...
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
              <div class="col-12 col-md-6">
                <label class="form-label">Danh mục</label>
                <Field name="category_id" v-slot="{ field, meta }">
                  <select
                    v-bind="field"
                    class="form-select bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                  >
                    <option value="">Chọn danh mục</option>
                    <option v-for="c in categoryOptions" :key="c.id" :value="String(c.id)">
                      {{ c.name }}
                    </option>
                  </select>
                </Field>
                <ErrorMessage name="category_id" class="invalid-feedback d-block" />
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">% Khuyến mãi</label>
                <Field name="percent" v-slot="{ field, meta }">
                  <input
                    v-bind="field"
                    type="number"
                    min="1"
                    max="100"
                    step="0.01"
                    class="form-control bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                    placeholder="1 - 100"
                  />
                </Field>
                <ErrorMessage name="percent" class="invalid-feedback d-block" />
              </div>

              <div class="col-12">
                <label class="form-label">Mô tả</label>
                <Field name="des" v-slot="{ field, meta }">
                  <textarea
                    v-bind="field"
                    rows="3"
                    class="form-control bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                    placeholder="Nhập mô tả khuyến mãi"
                  />
                </Field>
                <ErrorMessage name="des" class="invalid-feedback d-block" />
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Trạng thái</label>
                <Field name="status" v-slot="{ field, meta }">
                  <select
                    v-bind="field"
                    class="form-select bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                  >
                    <option value="actived">Bật</option>
                    <option value="disabled">Tắt</option>
                  </select>
                </Field>
                <ErrorMessage name="status" class="invalid-feedback d-block" />
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Ngày bắt đầu</label>
                <Field name="start_at" v-slot="{ field, meta }">
                  <input
                    v-bind="field"
                    type="date"
                    class="form-control bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                  />
                </Field>
                <ErrorMessage name="start_at" class="invalid-feedback d-block" />
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label">Ngày kết thúc</label>
                <Field name="end_at" v-slot="{ field, meta }">
                  <input
                    v-bind="field"
                    type="date"
                    class="form-control bg-transparent"
                    :class="{ 'is-invalid': meta.touched && !meta.valid }"
                  />
                </Field>
                <ErrorMessage name="end_at" class="invalid-feedback d-block" />
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-accent" type="submit" :disabled="isSubmitting">
                <i class="fa-solid fa-floppy-disk me-1"></i>
                {{ isSubmitting ? "Đang lưu..." : "Lưu thay đổi" }}
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

import CategoryService from "../../services/category.service";
import DiscountService from "../../services/discount.service";

const route = useRoute();
const router = useRouter();
const id = route.params.id;

const loading = ref(true);
const formKey = ref(0);
const categoryOptions = ref([]);
const initialValues = ref({
  category_id: "",
  percent: "",
  des: "",
  status: "actived",
  start_at: "",
  end_at: "",
});
const originalValues = ref({
  category_id: "",
  percent: "",
  des: "",
  status: "actived",
  start_at: "",
  end_at: "",
});

const schema = yup.object({
  category_id: yup
    .number()
    .transform((value, originalValue) => (originalValue === "" ? NaN : value))
    .typeError("Vui lòng chọn danh mục")
    .required("Vui lòng chọn danh mục"),
  percent: yup
    .number()
    .transform((value, originalValue) => (originalValue === "" ? NaN : value))
    .typeError("Vui lòng nhập % khuyến mãi")
    .required("Vui lòng nhập % khuyến mãi")
    .min(1, "% khuyến mãi phải từ 1 đến 100")
    .max(100, "% khuyến mãi phải từ 1 đến 100"),
  des: yup
    .string()
    .trim()
    .required("Vui lòng nhập mô tả")
    .min(2, "Mô tả tối thiểu 2 ký tự")
    .max(255, "Mô tả tối đa 255 ký tự"),
  status: yup
    .string()
    .oneOf(["actived", "disabled"], "Trạng thái không hợp lệ")
    .required("Vui lòng chọn trạng thái"),
  start_at: yup.string().required("Vui lòng chọn ngày bắt đầu"),
  end_at: yup
    .string()
    .required("Vui lòng chọn ngày kết thúc")
    .test(
      "end-after-start",
      "Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu",
      function (value) {
        const start = this.parent.start_at;
        if (!start || !value) return true;
        return value >= start;
      }
    ),
});

function mapApiErrors(errorsObj = {}) {
  const mapped = {};
  Object.keys(errorsObj).forEach((k) => {
    mapped[k] = Array.isArray(errorsObj[k]) ? errorsObj[k][0] : String(errorsObj[k]);
  });
  return mapped;
}

function toDateInput(value) {
  if (!value) return "";
  const raw = String(value);
  return raw.length >= 10 ? raw.slice(0, 10) : raw;
}

async function fetchAllCategories() {
  const all = [];
  let page = 1;
  let lastPage = 1;

  do {
    const res = await CategoryService.getAll({ page, per_page: 50 });
    const items = res?.data?.items ?? res?.items ?? [];
    const meta = res?.data?.meta ?? res?.meta ?? {};
    all.push(...(Array.isArray(items) ? items : []));
    lastPage = Number(meta?.last_page || 1);
    page += 1;
  } while (page <= lastPage);

  categoryOptions.value = all;
}

async function fetchDiscount() {
  const res = await DiscountService.get(id);
  const d = res?.data ?? res;
  const values = {
    category_id: d?.category_id != null ? String(d.category_id) : "",
    percent: d?.percent ?? "",
    des: d?.des ?? "",
    status: d?.status === "disabled" ? "disabled" : "actived",
    start_at: toDateInput(d?.start_at),
    end_at: toDateInput(d?.end_at),
  };
  initialValues.value = values;
  originalValues.value = { ...values };
  formKey.value += 1;
}

async function loadData() {
  loading.value = true;
  try {
    await Promise.all([fetchAllCategories(), fetchDiscount()]);
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải dữ liệu khuyến mãi";
    await Swal.fire("Lỗi", msg, "error");
    router.push({ name: "discounts.list" });
  } finally {
    loading.value = false;
  }
}

function onReset(resetFormFn) {
  resetFormFn({ values: { ...originalValues.value } });
}

async function onSubmit(values, { resetForm, setErrors }) {
  try {
    await DiscountService.update(id, {
      category_id: Number(values.category_id),
      percent: Number(values.percent),
      des: String(values.des || "").trim(),
      status: values.status,
      start_at: values.start_at,
      end_at: values.end_at,
    });

    const nextValues = {
      category_id: String(values.category_id),
      percent: values.percent,
      des: String(values.des || "").trim(),
      status: values.status,
      start_at: values.start_at,
      end_at: values.end_at,
    };

    originalValues.value = { ...nextValues };
    resetForm({ values: nextValues });
    await Swal.fire("Thành công!", "Cập nhật khuyến mãi thành công!", "success");
    router.push({ name: "discounts.list" });
  } catch (e) {
    setErrors(mapApiErrors(e?.response?.data?.errors || {}));
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Cập nhật khuyến mãi thất bại. Vui lòng thử lại.";
    await Swal.fire("Cập nhật thất bại", msg, "error");
  }
}

onMounted(loadData);
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
