<template>
  <div class="row g-3">
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Tạo đơn hàng</h4>
          <div class="small opacity-75">Chọn khách, địa chỉ giao và thêm sản phẩm</div>
        </div>

        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'orders.list' }">
          <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
        </RouterLink>
      </div>
    </div>

    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <Form
            :validation-schema="schema"
            :initial-values="initialValues"
            @submit="onSubmit"
            v-slot="{
              isSubmitting,
              setFieldValue,
              values,
              errors,
            }"
          >
            <div class="row g-3">
              <div class="col-12 col-md-4">
                <label class="form-label">Khách hàng</label>
                <Field name="user_id" v-slot="{ field, meta, errors: fErrors }">
                  <select
                    v-bind="field"
                    class="form-select bg-transparent"
                    :class="{ 'is-invalid': (meta.touched && !meta.valid) || fErrors.length }"
                    :disabled="isSubmitting"
                    @change="
                      (e) => {
                        field.onChange(e);
                        onUserChange(e.target.value, setFieldValue);
                      }
                    "
                  >
                    <option value="">-- Chọn khách --</option>
                    <option v-for="u in users" :key="u.id" :value="String(u.id)">
                      {{ u.name }} ({{ u.email || 'N/A' }})
                    </option>
                  </select>
                </Field>
                <ErrorMessage name="user_id" class="invalid-feedback d-block" />
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label">Địa chỉ giao</label>
                <Field name="delivery_info_id" v-slot="{ field, meta, errors: fErrors }">
                  <select
                    v-bind="field"
                    class="form-select bg-transparent"
                    :class="{ 'is-invalid': (meta.touched && !meta.valid) || fErrors.length }"
                    :disabled="isSubmitting || !getDeliveriesForUser(values.user_id).length"
                  >
                    <option value="">-- Chọn địa chỉ --</option>
                    <option
                      v-for="d in getDeliveriesForUser(values.user_id)"
                      :key="d.id"
                      :value="String(d.id)"
                    >
                      {{ d.name }} - {{ d.phone }} - {{ d.address }}
                      <span v-if="d.is_default"> (mặc định)</span>
                    </option>
                  </select>
                </Field>
                <ErrorMessage name="delivery_info_id" class="invalid-feedback d-block" />
                <div v-if="values.user_id && !getDeliveriesForUser(values.user_id).length" class="small text-danger mt-1">
                  Khách chưa có địa chỉ giao hàng.
                </div>
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label">Thanh toán</label>
                <Field name="payment_id" v-slot="{ field, meta, errors: fErrors }">
                  <select
                    v-bind="field"
                    class="form-select bg-transparent"
                    :class="{ 'is-invalid': (meta.touched && !meta.valid) || fErrors.length }"
                    :disabled="isSubmitting"
                  >
                    <option value="">-- Chọn phương thức --</option>
                    <option v-for="p in payments" :key="p.id" :value="String(p.id)">
                      {{ p.name }}
                    </option>
                  </select>
                </Field>
                <ErrorMessage name="payment_id" class="invalid-feedback d-block" />
                <div class="small opacity-75 mt-1">Chỉ hỗ trợ phương thức thanh toán khi nhận hàng.</div>
              </div>

              <div class="col-12">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">Sản phẩm trong đơn</div>
                    <div class="small opacity-75">
                      Chọn sản phẩm → phân loại (nếu có) → nhập số lượng & giá bán. Giá gợi ý sẽ hiển thị bên dưới.
                    </div>
                  </div>
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="addRow(setFieldValue, values)"
                    :disabled="isSubmitting"
                  >
                    <i class="fa-solid fa-plus me-1"></i> Thêm dòng
                  </button>
                </div>

                <div class="table-responsive mt-2">
                  <table class="table align-middle mb-0">
                    <thead>
                      <tr class="small opacity-75">
                        <th style="min-width: 320px">Sản phẩm</th>
                        <th style="min-width: 180px">Phân loại</th>
                        <th style="width: 140px">Số lượng</th>
                        <th style="width: 200px">Giá bán</th>
                        <th style="width: 110px" class="text-end">Thao tác</th>
                      </tr>
                    </thead>

                    <FieldArray name="items" v-slot="{ fields, remove }">
                      <tbody>
                        <tr v-for="(f, idx) in fields" :key="f.key">
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div class="thumb">
                                <img
                                  v-if="getProductThumb(f.value.product_id)"
                                  :src="getProductThumb(f.value.product_id)"
                                  alt="thumb"
                                />
                                <div v-else class="thumb-placeholder">
                                  <i class="fa-regular fa-image"></i>
                                </div>
                              </div>
                              <div class="flex-grow-1">
                                <Field :name="`items[${idx}].product_id`" v-slot="{ field, meta, errors: fErrors }">
                                  <select
                                    v-bind="field"
                                    class="form-select bg-transparent"
                                    :class="{ 'is-invalid': meta.touched && fErrors.length }"
                                    :disabled="isSubmitting"
                                    @change="
                                      async (e) => {
                                        field.onChange(e);
                                        await onProductSelect(idx, e.target.value, setFieldValue, values);
                                      }
                                    "
                                  >
                                    <option value="">-- Chọn sản phẩm --</option>
                                    <option v-for="p in products" :key="p.id" :value="String(p.id)">
                                      {{ p.name }}
                                    </option>
                                  </select>
                                </Field>
                                <ErrorMessage :name="`items[${idx}].product_id`" class="invalid-feedback d-block" />
                              </div>
                            </div>
                          </td>

                          <td>
                            <Field :name="`items[${idx}].color_id`" v-slot="{ field, meta, errors: fErrors }">
                              <select
                                v-bind="field"
                                class="form-select bg-transparent"
                                :class="{ 'is-invalid': meta.touched && fErrors.length }"
                                :disabled="isSubmitting || !getColorsForRow(f.value).length"
                              >
                                <option value="">Mặc định</option>
                                <option v-for="c in getColorsForRow(f.value)" :key="c.id" :value="String(c.id)">
                                  {{ c.color_name }}
                                </option>
                              </select>
                            </Field>
                            <ErrorMessage :name="`items[${idx}].color_id`" class="invalid-feedback d-block" />
                          </td>

                          <td>
                            <Field :name="`items[${idx}].quantity`" v-slot="{ field, meta, errors: fErrors }">
                              <input
                                v-bind="field"
                                type="number"
                                min="1"
                                class="form-control bg-transparent"
                                :class="{ 'is-invalid': meta.touched && fErrors.length }"
                                :disabled="isSubmitting"
                                @input="
                                  (e) => {
                                    field.onInput(e);
                                    onQuantityChange(idx, setFieldValue, values);
                                  }
                                "
                              />
                            </Field>
                            <ErrorMessage :name="`items[${idx}].quantity`" class="invalid-feedback d-block" />
                          </td>

                          <td>
                            <Field :name="`items[${idx}].unit_price`" v-slot="{ field, meta, errors: fErrors }">
                              <input
                                v-bind="field"
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-control bg-transparent"
                                :class="{ 'is-invalid': meta.touched && fErrors.length }"
                                :disabled="isSubmitting"
                              />
                            </Field>
                            <ErrorMessage :name="`items[${idx}].unit_price`" class="invalid-feedback d-block" />
                            <div class="small opacity-75 mt-1 d-flex align-items-center gap-2">
                              <span>Giá gợi ý: {{ formatMoney(suggestedPrice(f.value)) }}</span>
                              <button
                                type="button"
                                class="btn btn-link btn-sm p-0"
                                :disabled="isSubmitting || !suggestedPrice(f.value)"
                                @click="applySuggestedPrice(idx, setFieldValue, values)"
                              >
                                Dùng giá gợi ý
                              </button>
                            </div>
                          </td>

                          <td class="text-end">
                            <button
                              type="button"
                              class="icon-btn text-danger"
                              title="Xóa"
                              :disabled="isSubmitting || fields.length === 1"
                              @click="remove(idx)"
                            >
                              <i class="fa-solid fa-trash"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </FieldArray>
                  </table>
                </div>
              </div>

              <div class="col-12">
                <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between border-top pt-3">
                  <div class="small opacity-75">
                    Tổng số lượng: {{ calcTotalQuantity(values.items) }} | Tạm tính: {{ formatMoney(calcSubtotal(values.items)) }}
                  </div>
                  <div class="fw-semibold">
                    Tổng thanh toán: {{ formatMoney(calcSubtotal(values.items) + shippingFee) }}
                    <span class="small opacity-75">(đã gồm phí giao {{ formatMoney(shippingFee) }})</span>
                  </div>
                </div>
              </div>

              <div class="col-12 d-flex justify-content-end gap-2">
                <button
                  type="button"
                  class="btn btn-outline-secondary"
                  :disabled="isSubmitting"
                  @click="resetAll(setFieldValue)"
                >
                  Đặt lại
                </button>
                <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                  <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin me-1"></i>
                  Tạo đơn hàng
                </button>
              </div>
            </div>
          </Form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { Field, FieldArray, Form, ErrorMessage } from "vee-validate";
import * as yup from "yup";
import Swal from "sweetalert2";

import OrderService from "@/services/order.service";
import ProductService from "@/services/product.service";
import { formatMoney } from "@/utils/utils";

const router = useRouter();
const users = ref([]);
const payments = ref([]);
const products = ref([]);
const productDetailCache = ref({});
const shippingFee = ref(0);

const initialValues = {
  user_id: "",
  delivery_info_id: "",
  payment_id: "",
  items: [
    {
      product_id: "",
      color_id: "",
      quantity: 1,
      unit_price: "",
    },
  ],
};

const schema = yup.object({
  user_id: yup.string().required("Vui lòng chọn khách"),
  delivery_info_id: yup.string().required("Vui lòng chọn địa chỉ giao"),
  payment_id: yup.string().required("Vui lòng chọn phương thức thanh toán"),
  items: yup
    .array()
    .min(1, "Vui lòng thêm ít nhất 1 sản phẩm")
    .of(
      yup.object({
        product_id: yup.string().required("Chọn sản phẩm"),
        color_id: yup
          .string()
          .test("color-required", "Vui lòng chọn phân loại", function (val) {
            const pid = this.parent?.product_id;
            if (!pid) return true;
            const p = findProductById(pid);
            const hasColors = (p?.colors || []).length > 0;
            if (!hasColors) return true;
            return !!val;
          }),
        quantity: yup
          .number()
          .typeError("Số lượng phải là số")
          .integer("Số lượng phải là số nguyên")
          .min(1, "Số lượng phải lớn hơn 0")
          .required("Nhập số lượng"),
        unit_price: yup
          .number()
          .typeError("Giá bán phải là số")
          .moreThan(0, "Giá bán phải lớn hơn 0")
          .required("Nhập giá bán"),
      })
    ),
});

const selectedUserId = ref("");

function findUser(userId) {
  return users.value.find((u) => String(u.id) === String(userId));
}

function findProductById(productId) {
  return products.value.find((p) => String(p.id) === String(productId));
}

function getDeliveriesForUser(userId) {
  const u = findUser(userId);
  return u?.delivery_infos || [];
}

function addRow(setFieldValue, values) {
  setFieldValue("items", [
    ...values.items,
    { product_id: "", color_id: "", quantity: 1, unit_price: "" },
  ]);
}

function resetAll(setFieldValue) {
  setFieldValue("user_id", "");
  setFieldValue("delivery_info_id", "");
  setFieldValue("payment_id", "");
  setFieldValue("items", JSON.parse(JSON.stringify(initialValues.items)));
  selectedUserId.value = "";
}

function getColorsForRow(row) {
  const p = findProductById(row.product_id);
  return p?.colors || [];
}

function getProductThumb(productId) {
  const p = findProductById(productId);
  if (!p) return "";
  const first = p?.images?.[0]?.url || "";
  return first || "";
}

async function onProductSelect(idx, productId, setFieldValue, values) {
  setFieldValue(`items[${idx}].product_id`, productId);
  setFieldValue(`items[${idx}].color_id`, "");
  await ensureProductDetail(productId);
  if (!productId) {
    return;
  }
  applySuggestedPrice(idx, setFieldValue, values);
}

function onQuantityChange(idx, setFieldValue, values) {
  // keep unit price intact, only recompute suggestion display
  // optional auto-fill when unit_price is empty
  const current = values.items?.[idx];
  if (!current) return;
  if (String(current.unit_price || "") === "") {
    applySuggestedPrice(idx, setFieldValue, values);
  }
}

function onUserChange(userId, setFieldValue) {
  selectedUserId.value = userId;
  const deliveries = getDeliveriesForUser(userId);
  const def = deliveries.find((d) => d.is_default) || deliveries[0];
  setFieldValue("delivery_info_id", def ? String(def.id) : "");
}

async function ensureProductDetail(productId) {
  if (!productId) return null;
  if (productDetailCache.value[productId]) {
    return productDetailCache.value[productId];
  }
  const res = await ProductService.get(productId);
  const detail = res?.product || null;
  productDetailCache.value[productId] = detail;
  return detail;
}

function resolveUnitPrice(prices, tierId, quantity) {
  const rows = [...(prices || [])].sort((a, b) => Number(a.min_quantity || 0) - Number(b.min_quantity || 0));
  if (!rows.length) return 0;

  let tierRows = tierId ? rows.filter((r) => Number(r.tier_id) === Number(tierId)) : [];
  if (!tierRows.length) {
    const retailRows = rows.filter((r) => String(r?.tier?.code || "").toUpperCase() === "RETAIL");
    if (retailRows.length) {
      tierRows = retailRows;
    }
  }
  if (!tierRows.length) {
    const fallbackTierId = rows[0].tier_id;
    tierRows = rows.filter((r) => Number(r.tier_id) === Number(fallbackTierId));
  }

  let applied = tierRows[0];
  tierRows.forEach((r) => {
    if (Number(r.min_quantity || 0) <= Number(quantity || 0)) {
      applied = r;
    }
  });

  return Number(applied?.price || 0);
}

function suggestedPrice(row) {
  const pid = row?.product_id;
  if (!pid) return 0;
  const detail = productDetailCache.value[pid];
  if (!detail || !(detail.prices || []).length) return 0;
  const user = findUser(selectedUserId.value);
  const tierId = user?.effective_tier_id ?? null;
  return resolveUnitPrice(detail.prices, tierId, Number(row?.quantity || 1));
}

function applySuggestedPrice(idx, setFieldValue, values) {
  const row = values.items?.[idx] || {};
  const price = suggestedPrice(row);
  if (!price) return;
  setFieldValue(`items[${idx}].unit_price`, price);
}

function calcSubtotal(items) {
  return (items || []).reduce((sum, it) => {
    const q = Number(it.quantity || 0);
    const p = Number(it.unit_price || 0);
    return sum + q * p;
  }, 0);
}

function calcTotalQuantity(items) {
  return (items || []).reduce((sum, it) => sum + Number(it.quantity || 0), 0);
}

async function onSubmit(values, { setErrors }) {
  try {
    const payload = {
      user_id: Number(values.user_id),
      delivery_info_id: Number(values.delivery_info_id),
      payment_id: Number(values.payment_id),
      items: values.items.map((it) => ({
        product_id: Number(it.product_id),
        color_id: it.color_id ? Number(it.color_id) : null,
        quantity: Number(it.quantity),
        unit_price: Number(it.unit_price),
      })),
    };

    await OrderService.create(payload);
    await Swal.fire("Thành công", "Tạo đơn hàng thành công!", "success");
    router.push({ name: "orders.list" });
  } catch (e) {
    const status = e?.response?.status;
    const data = e?.response?.data;

    if (status === 422 && data?.errors) {
      const mapped = {};
      Object.keys(data.errors).forEach((k) => {
        mapped[k] = Array.isArray(data.errors[k]) ? data.errors[k][0] : String(data.errors[k]);
      });
      setErrors(mapped);
      return;
    }

    const msg = data?.message || data?.error || "Tạo đơn hàng thất bại. Vui lòng thử lại.";
    await Swal.fire("Thất bại", msg, "error");
  }
}

onMounted(async () => {
  try {
    const meta = await OrderService.getCreateMeta();
    users.value = meta?.data?.users || [];
    payments.value = meta?.data?.payments || [];
    shippingFee.value = meta?.data?.shipping_fee || 0;

    products.value = (await ProductService.getAll({ per_page: 200 })).data.items || [];
  } catch (e) {
    const msg = e?.response?.data?.message || "Không tải được dữ liệu tạo đơn hàng.";
    await Swal.fire("Lỗi", msg, "error");
  }
});
</script>

<style scoped>
.card-soft {
  background: var(--main-extra-bg);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  color: var(--font-color);
}

.thumb {
  width: 7rem;
  border-radius: 0.6rem;
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: rgba(255, 255, 255, 0.03);
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.thumb-placeholder {
  opacity: 0.6;
  font-size: 1.1rem;
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
}

.icon-btn:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}
</style>
