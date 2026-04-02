import { createApiClient } from "./api.service";

class OrderService {
  constructor(baseUrl = "/api") {
    this.api = createApiClient(baseUrl);
  }

  normalizeOrder(order = {}) {
    const items = Array.isArray(order?.items)
      ? order.items.map((item) => ({
          id: Number(item?.id || 0),
          product_id: Number(item?.product_id || item?.product?.id || 0),
          color_id: item?.color_id == null ? null : Number(item.color_id),
          name: String(item?.name || item?.product?.name || "San pham"),
          image: String(item?.image || item?.product?.image || item?.product?.images?.[0]?.url || ""),
          color_name: String(item?.color_name || item?.color?.color_name || "Mac dinh"),
          quantity: Number(item?.quantity || 0),
          unit_price: Number(item?.unit_price || item?.price || 0),
          line_total: Number(item?.line_total || Number(item?.quantity || 0) * Number(item?.unit_price || item?.price || 0)),
        }))
      : [];

    const reviewableProducts = Array.isArray(order?.reviewable_products)
      ? order.reviewable_products.map((item) => ({
          product_id: Number(item?.product_id || 0),
          name: String(item?.name || ""),
          image: String(item?.image || ""),
          total_quantity: Number(item?.total_quantity || 0),
          variants: Array.isArray(item?.variants) ? item.variants.map((v) => String(v || "")) : [],
          is_evaluated: Boolean(item?.is_evaluated),
          can_review: Boolean(item?.can_review),
          evaluate: item?.evaluate
            ? {
                id: Number(item.evaluate?.id || 0),
                product_id: Number(item.evaluate?.product_id || 0),
                order_id: Number(item.evaluate?.order_id || 0),
                rating: Number(item.evaluate?.rating || 0),
                content:
                  item.evaluate?.content === null || item.evaluate?.content === undefined
                    ? null
                    : String(item.evaluate.content),
                reply:
                  item.evaluate?.reply === null || item.evaluate?.reply === undefined
                    ? null
                    : String(item.evaluate.reply),
                created_at: item.evaluate?.created_at || null,
                medias: Array.isArray(item.evaluate?.medias)
                  ? item.evaluate.medias.map((m) => ({
                      id: Number(m?.id || 0),
                      type: String(m?.type || "image"),
                      url: String(m?.url || ""),
                    }))
                  : [],
              }
            : null,
        }))
      : [];

    return {
      id: Number(order?.id || order?.order_id || 0),
      status: String(order?.status || "pending"),
      created_at: order?.created_at || null,
      updated_at: order?.updated_at || null,
      delivery_info: order?.delivery_info || null,
      payment: order?.payment || null,
      items,
      items_count: Number(order?.items_count || items.length || 0),
      discounts: Array.isArray(order?.discounts)
        ? order.discounts.map((d) => ({
            id: Number(d?.id || 0),
            order_discount_id: Number(d?.order_discount_id || 0),
            des: String(d?.des || ""),
            percent: Number(d?.percent || 0),
            category_id: Number(d?.category_id || 0),
            category_name: String(d?.category_name || ""),
            price: Number(d?.price || 0),
          }))
        : [],
      reviewable_products: reviewableProducts,
      review_summary: {
        total_products: Number(order?.review_summary?.total_products || reviewableProducts.length || 0),
        evaluated_products: Number(order?.review_summary?.evaluated_products || 0),
        pending_products: Number(order?.review_summary?.pending_products || 0),
        can_submit: Boolean(order?.review_summary?.can_submit),
      },
      product_subtotal: Number(order?.product_subtotal || 0),
      discount_price: Number(order?.discount_price || 0),
      shipping_fee: Number(order?.shipping_fee || 0),
      total_price: Number(order?.total_price || 0),
    };
  }

  async getMyOrders(status = "all") {
    const res = (await this.api.get("/orders/my", { params: { status } })).data;
    const rows = Array.isArray(res?.data) ? res.data : [];
    return rows.map((row) => this.normalizeOrder(row));
  }

  async getMyOrderDetail(orderId) {
    const res = (await this.api.get(`/orders/my/${Number(orderId)}`)).data;
    return this.normalizeOrder(res?.data || {});
  }

  async cancelMyOrder(orderId) {
    const res = (await this.api.post(`/orders/my/${Number(orderId)}/cancel`)).data;
    return {
      message: res?.message || "Hủy đơn hàng thành công",
      order: this.normalizeOrder(res?.data || {}),
    };
  }

  async completeMyOrder(orderId) {
    const res = (await this.api.post(`/orders/my/${Number(orderId)}/complete`)).data;
    return {
      message: res?.message || "Xac nhan nhan hang thanh cong",
      order: this.normalizeOrder(res?.data || {}),
    };
  }

  async submitMyOrderEvaluate(orderId, reviews = []) {
    const formData = new FormData();

    reviews.forEach((row, idx) => {
      formData.append(`reviews[${idx}][product_id]`, String(Number(row?.product_id || 0)));
      formData.append(`reviews[${idx}][rating]`, String(Number(row?.rating || 0)));

      const content = typeof row?.content === "string" ? row.content.trim() : "";
      if (content) {
        formData.append(`reviews[${idx}][content]`, content);
      }

      const files = Array.isArray(row?.media_files) ? row.media_files : [];
      files.forEach((file) => {
        if (file) formData.append(`reviews[${idx}][media_files][]`, file);
      });

      const deleteIds = Array.isArray(row?.delete_media_ids) ? row.delete_media_ids : [];
      deleteIds.forEach((id) => {
        formData.append(`reviews[${idx}][delete_media_ids][]`, String(Number(id || 0)));
      });
    });

    const res = (await this.api.post(`/orders/my/${Number(orderId)}/evaluate`, formData)).data;
    return {
      message: res?.message || "Đánh giá sản phẩm thành công",
      order: this.normalizeOrder(res?.data || {}),
    };
  }
}

export default new OrderService();
