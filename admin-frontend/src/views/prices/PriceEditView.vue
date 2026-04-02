<template>
  <div class="row g-3">
    <!-- Header -->
    <div class="col-12">
      <div
        class="d-flex align-items-start align-items-md-center justify-content-between gap-2 flex-column flex-md-row"
      >
        <div>
          <h4 class="mb-1">Giá bán của sản phẩm</h4>
          <div class="small opacity-75">
            Xem và chỉnh sửa các mức giá theo số lượng và cấp
          </div>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" @click="$router.back()">
            <i class="fa-solid fa-arrow-left me-1"></i> Quay lại
          </button>

          <button
            class="btn btn-outline-secondary"
            type="button"
            @click="refetch()"
            :disabled="loading"
            title="Tải lại"
          >
            <i class="fa-solid fa-rotate"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="col-12">
      <div class="card card-soft">
        <div class="card-body">
          <!-- Loading -->
          <div v-if="loading" class="py-4 text-center opacity-75">
            <i class="fa-solid fa-spinner fa-spin me-2"></i> Đang tải dữ liệu...
          </div>

          <template v-else>
            <!-- Product summary -->
            <div class="d-flex gap-3 align-items-center mb-3">
              <div class="thumb">
                <img v-if="productThumb" :src="productThumb" alt="thumb" />
                <div v-else class="thumb-placeholder">
                  <i class="fa-regular fa-image"></i>
                </div>
              </div>

              <div class="flex-grow-1">
                <div class="fw-semibold fs-5">{{ product?.name || "—" }}</div>
                <div class="small opacity-75">
                  Danh mục: {{ product?.category?.name || "—" }}
                </div>
                <div class="small opacity-75">
                  ID: P{{ product?.id || "—" }}
                </div>
              </div>
            </div>

            <div class="mb-3">
              <div class="fw-semibold">Tham khảo giá nhập</div>
              <div class="small opacity-75">
                Dựa trên các phiếu nhập đã hoàn tất, có thể giúp ước lượng giá bán hợp lý.
              </div>

              <div v-if="purchaseStats.loading" class="small mt-2 opacity-75">
                <i class="fa-solid fa-spinner fa-spin me-1"></i> Đang tải thống kê giá nhập...
              </div>

              <div
                v-else-if="
                  purchaseStats.data?.total_entries > 0 &&
                  purchaseStats.data?.avg_purchase_price >= 0
                "
                class="row g-3 mt-1"
              >
                <div class="col-12 col-md-6">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Giá nhập TB</div>
                    <div class="fs-4 fw-semibold">
                      {{ formatMoney(purchaseStats.data.avg_purchase_price) }}
                    </div>
                    <div class="small opacity-75">
                      {{ purchaseStats.data.total_entries }} phiếu nhập hoàn tất
                    </div>
                  </div>
                </div>

                <div class="col-12 col-md-6">
                  <div class="stat-box">
                    <div class="small text-uppercase opacity-75">Lần gần nhất</div>
                    <div class="fs-4 fw-semibold">
                      {{
                        purchaseStats.data.last_purchase_price
                          ? formatMoney(purchaseStats.data.last_purchase_price)
                          : "-"
                      }}
                    </div>
                    <div class="small opacity-75">
                      Tổng SL đã nhập: {{ purchaseStats.data.total_quantity ?? 0 }}
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-else-if="purchaseStats.data?.total_entries === 0"
                class="small mt-2 text-warning"
              >
                Chưa có phiếu nhập hoàn tất cho sản phẩm này.
              </div>

              <div v-else-if="purchaseStats.error" class="small mt-2 text-danger">
                {{ purchaseStats.error }}
              </div>
            </div>

            <!-- Form -->
            <Form
              :key="formKey"
              :initial-values="initialValues"
              :validation-schema="schema"
              @submit="onSubmit"
              v-slot="{ isSubmitting, values, setFieldValue, errors }"
            >
              <div
                class="d-flex align-items-center justify-content-between gap-2"
              >
                <div>
                  <div class="fw-semibold">Bảng giá theo số lượng</div>
                  <div class="small opacity-75">
                    Mỗi dòng là một mức <b>số lượng tối thiểu</b>, bắt buộc nhập
                    đủ giá cho tất cả cấp.
                  </div>
                </div>

                <div class="d-flex gap-2">
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="addRow(values, setFieldValue)"
                    :disabled="isSubmitting"
                  >
                    <i class="fa-solid fa-plus me-1"></i> Thêm dòng
                  </button>

                  <button
                    class="btn btn-accent"
                    type="submit"
                    :disabled="isSubmitting"
                  >
                    <i class="fa-solid fa-floppy-disk me-1"></i>
                    {{ isSubmitting ? "Đang lưu..." : "Lưu thay đổi" }}
                  </button>
                </div>
              </div>

              <FieldArray name="rows" v-slot="{ fields, remove }">
                <div class="mt-3 d-flex flex-column gap-2">
                  <div
                    v-for="(f, rowIdx) in fields"
                    :key="f.key"
                    class="row-box"
                  >
                    <!-- Row header -->
                    <div
                      class="d-flex align-items-center justify-content-between gap-2 mb-2"
                    >
                      <div class="fw-semibold">Mức giá #{{ rowIdx + 1 }}</div>

                      <button
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        title="Xóa dòng"
                        @click="removeRow(values, remove, rowIdx)"
                        :disabled="isSubmitting || !canRemoveRow(values, rowIdx)"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>

                    <!-- Row inputs: min_quantity  -->
                    <div class="row g-3">
                      <div class="col-12 col-md-4">
                        <label class="form-label">Số lượng tối thiểu</label>
                        <Field
                          :name="`rows[${rowIdx}].min_quantity`"
                          v-slot="{ field, meta, errors, handleChange }"
                        >
                          <input
                            v-bind="field"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                            class="form-control bg-transparent"
                            :class="{
                              'is-invalid': meta.touched && errors.length,
                            }"
                            placeholder="Ví dụ: 1 / 10 / 50..."
                            @input="handleChange"
                          />
                        </Field>

                      
                        <ErrorMessage
                          :name="`rows[${rowIdx}].min_quantity`"
                          class="invalid-feedback d-block"
                        />

                        <div
                          v-if="isDupMinQty(values, rowIdx)"
                          class="small text-danger mt-1"
                        >
                          Số lượng tối thiểu bị trùng
                        </div>
                        <div
                          v-else-if="!hasMinQtyOne(values) && rowIdx === 0"
                          class="small text-danger mt-1"
                        >
                          Phải có ít nhất 1 dòng với số lượng tối thiểu = 1
                        </div>
                      </div>
                    </div>

                    <!-- Tier prices (vertical/grid) -->
                    <div class="mt-3">
                      <div class="small opacity-75 mb-2">Giá theo cấp</div>

                      <div class="row g-3">
                        <div
                          v-for="(t, tierIdx) in tiers"
                          :key="t.id"
                          class="col-12 col-md-6 col-xl-4"
                        >
                          <div
                            class="d-flex align-items-center justify-content-between mb-1"
                          >
                            <div class="d-flex align-items-center gap-2">
                              <span class="badge badge-tier">{{ t.code }}</span>
                              <span class="fw-semibold">{{ t.name }}</span>
                            </div>
                          </div>

                          <Field
                            :name="`rows[${rowIdx}].prices[${tierIdx}].price`"
                            v-slot="{ field, meta, errors, handleChange }"
                          >
                            <input
                              v-bind="field"
                              type="number"
                              min="1"
                              step="1"
                              inputmode="numeric"
                              class="form-control bg-transparent"
                              :class="{
                                'is-invalid': meta.touched && errors.length,
                              }"
                              placeholder="Nhập giá..."
                              @input="handleChange"
                            />
                          </Field>
                          <ErrorMessage
                            :name="`rows[${rowIdx}].prices[${tierIdx}].price`"
                            class="invalid-feedback d-block"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </FieldArray>

              <div
                v-if="typeof errors.rows === 'string'"
                class="invalid-feedback d-block mt-2"
              >
                {{ errors.rows }}
              </div>
            </Form>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Form, Field, FieldArray, ErrorMessage } from "vee-validate";
import * as yup from "yup";
import { formatMoney } from "@/utils/utils";
import Swal from "sweetalert2";
import TierService from "@/services/tier.service";
import ProductService from "@/services/product.service";

const route = useRoute();
const router = useRouter();
const productId = route.params.id;

const loading = ref(true);
const formKey = ref(0);

const product = ref(null);
const tiers = ref([]);
const purchaseStats = ref({ loading: false, data: null, error: null });

const initialValues = ref({ rows: [] });

const productThumb = computed(() => product.value?.images?.[0]?.url || "");

/**
 * row = { min_quantity, prices:[{tier_id, price}] }
 */
function buildRow(minQty = 1, existingTierPrices = null) {
  const prices = tiers.value.map((t) => ({
    tier_id: String(t.id),
    price: existingTierPrices?.[String(t.id)] ?? "",
  }));

  return {
    _key: crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
    min_quantity: minQty,
    prices,
  };
}

/**
 * prices = [
 *  { tier_id, min_quantity, price, }
 * ]
 */
function normalizePricesToRows(prices = []) {
  const map = new Map(); // minQty -> { tierMap }

  for (const p of prices) {
    const minq = String(p.min_quantity);

    if (!map.has(minq)) {
      map.set(minq, {
        tierMap: {},
      });
    }

    const g = map.get(minq);
    g.tierMap[String(p.tier_id)] = p.price;
  }

  const rows = Array.from(map.entries())
    .map(([minq, g]) => buildRow(Number(minq), g.tierMap))
    .sort((a, b) => Number(a.min_quantity) - Number(b.min_quantity));

  return rows.length ? rows : [buildRow(1)];
}

const schema = computed(() => {
  const dateStr = () =>
    yup
      .string()
      .nullable()
      .transform((v) => (v === "" ? null : v));

  return yup.object({
    rows: yup
      .array()
      .min(1, "Vui lòng tạo ít nhất 1 dòng giá")
      .test("unique-minqty", "Min quantity bị trùng", function (rows) {
        if (!Array.isArray(rows)) return false;
        const seen = new Set();
        for (const r of rows) {
          const k = String(r?.min_quantity ?? "");
          if (!k) continue;
          if (seen.has(k)) return false;
          seen.add(k);
        }
        return true;
      })
      .test(
        "has-minqty-one",
        "Phải có ít nhất 1 dòng với min quantity = 1",
        function (rows) {
          if (!Array.isArray(rows) || !rows.length) return false;
          return rows.some((r) => Number(r?.min_quantity) === 1);
        }
      )
      .of(
        yup.object({
          min_quantity: yup
            .number()
            .typeError("Min quantity phải là số")
            .integer("Min quantity phải là số nguyên")
            .min(1, "Min quantity phải >= 1")
            .required("Vui lòng nhập min_quantity"),

          prices: yup
            .array()
            .min(1)
            .of(
              yup.object({
                tier_id: yup.string().required(),
                price: yup
                  .number()
                  .typeError("Giá phải là số")
                  .moreThan(0, "Giá phải > 0")
                  .required("Vui lòng nhập giá"),
              })
            )
            .test(
              "fill-all-tiers",
              "Vui lòng nhập giá cho tất cả tier",
              function (arr) {
                if (!tiers.value.length) return true;
                if (!Array.isArray(arr)) return false;
                if (arr.length !== tiers.value.length) return false;
                return arr.every((x) => Number(x?.price) > 0);
              }
            ),
        })
      ),
  });
});

function getDupMinQtySetFromValues(values) {
  const rows = values?.rows || [];
  const count = new Map();
  for (const r of rows) {
    const k = String(r?.min_quantity ?? "");
    if (!k) continue;
    count.set(k, (count.get(k) || 0) + 1);
  }
  const dup = new Set();
  for (const [k, c] of count.entries()) {
    if (c > 1) dup.add(k);
  }
  return dup;
}

function isDupMinQty(values, rowIdx) {
  const k = String(values?.rows?.[rowIdx]?.min_quantity ?? "");
  if (!k) return false;
  return getDupMinQtySetFromValues(values).has(k);
}

function hasMinQtyOne(values) {
  return (values?.rows || []).some((r) => Number(r?.min_quantity) === 1);
}

function canRemoveRow(values, rowIdx) {
  const rows = values?.rows || [];
  if (rows.length <= 1) return false;

  const currentMinQty = Number(rows[rowIdx]?.min_quantity);
  if (currentMinQty !== 1) return true;

  return rows.some((r, idx) => idx !== rowIdx && Number(r?.min_quantity) === 1);
}

async function removeRow(values, remove, rowIdx) {
  if (canRemoveRow(values, rowIdx)) {
    remove(rowIdx);
    return;
  }

  await Swal.fire(
    "Lỗi",
    "Không thể xóa dòng min quantity = 1 duy nhất trong bảng giá.",
    "warning"
  );
}

function addRow(values, setFieldValue) {
  const rows = values?.rows || [];
  const maxMin = rows.reduce(
    (m, r) => Math.max(m, Number(r?.min_quantity || 0)),
    0
  );
  const next = [...rows, buildRow(maxMin ? maxMin + 1 : 1)];
  setFieldValue("rows", next);
}

async function onSubmit(values, { setErrors }) {
  try {
    const dup = getDupMinQtySetFromValues(values);
    if (dup.size) {
      await Swal.fire(
        "Lỗi",
        "Min quantity bị trùng, vui lòng kiểm tra lại.",
        "error"
      );
      return;
    }

    if (!hasMinQtyOne(values)) {
      await Swal.fire(
        "Lỗi",
        "Bảng giá bắt buộc phải có ít nhất 1 dòng với min quantity = 1.",
        "error"
      );
      return;
    }

    const payload = {
      product_id: Number(productId),
      rows: (values.rows || []).map((r) => ({
        min_quantity: Number(r.min_quantity),
        prices: (r.prices || []).map((p) => ({
          tier_id: Number(p.tier_id),
          price: Number(p.price),
        })),
      })),
    };

    console.log(payload);
    const res = await ProductService.saveProductPrices(productId, payload);
    await Swal.fire("Thành công", res.message, "success");
  } catch (e) {
    console.log(e);
    const status = e?.response?.status;
    const data = e?.response?.data;

    if (status === 422 && data?.errors) {
      const mapped = {};
      Object.keys(data.errors).forEach((k) => {
        mapped[k] = Array.isArray(data.errors[k])
          ? data.errors[k][0]
          : String(data.errors[k]);
      });
      setErrors(mapped);
      return;
    }

    const msg =
      data?.message || data?.error || "Lưu giá thất bại. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
  }
}

async function refetch() {
  loading.value = true;
  purchaseStats.value = { loading: true, data: null, error: null };
  try {
    const tierRes = await TierService.getAll({ per_page: 200 });
    tiers.value = tierRes?.data?.items ?? tierRes?.data ?? tierRes ?? [];

    const [prRes, purchaseRes] = await Promise.all([
      ProductService.get(productId),
      ProductService.getPurchaseStats(productId),
    ]);
    product.value = prRes?.product ?? prRes;
    purchaseStats.value = {
      loading: false,
      data: purchaseRes?.data || null,
      error: null,
    };

    const prices = prRes?.product?.prices || [];

    initialValues.value = {
      rows: normalizePricesToRows(prices),
    };

    formKey.value += 1;
  } catch (e) {
    console.log(e);
    purchaseStats.value = {
      loading: false,
      data: null,
      error:
        e?.response?.data?.message ||
        e?.response?.data?.error ||
        "Khong the tai thong ke gia nhap.",
    };
    const msg =
      e?.response?.data?.message ||
      e?.response?.data?.error ||
      "Không thể tải dữ liệu giá. Vui lòng thử lại.";
    await Swal.fire("Lỗi", msg, "error");
    router.back();
  } finally {
    loading.value = false;
  }
}

onMounted(refetch);
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

/* product thumb */
.thumb {
  width: 8rem;
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

/* tier badge */
.badge-tier {
  border-radius: 999px;
  padding: 0.4rem 0.6rem;
  background: rgba(255, 166, 0, 0.15);
  border: 1px solid rgba(255, 166, 0, 0.35);
  color: #ffa500;
  font-weight: 700;
}

/* row card */
.row-box {
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.02);
}

.stat-box {
  border: 1px solid var(--border-color);
  border-radius: 0.8rem;
  padding: 0.9rem 1rem;
  background: rgba(255, 255, 255, 0.02);
}
</style>
