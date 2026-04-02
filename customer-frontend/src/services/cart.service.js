import { createApiClient } from "./api.service";

class CartService {
  constructor(baseUrl = "/api/carts") {
    this.api = createApiClient(baseUrl);
  }

  normalizeItem(item = {}) {
    const product = item?.product || {};
    const prices = Array.isArray(product?.prices) ? product.prices : [];
    const minRow = prices.find((p) => Number(p?.min_quantity || 0) === 1) || prices[0] || null;
    const serverUnitPrice = item?.unit_price != null ? Number(item.unit_price) : null;
    const unitPrice = Number.isFinite(serverUnitPrice) ? serverUnitPrice : Number(minRow?.price || 0);
    const quantity = Number(item?.quantity || 0);
    const minQuantityFromServer =
      item?.price_min_quantity != null ? Number(item.price_min_quantity) : null;
    const minQuantity = Number.isFinite(minQuantityFromServer)
      ? minQuantityFromServer
      : Number(minRow?.min_quantity || 1);
    const totalPriceFromServer = item?.total_price != null ? Number(item.total_price) : null;
    const totalPrice = Number.isFinite(totalPriceFromServer) ? totalPriceFromServer : unitPrice * quantity;

    return {
      id: Number(item?.id || 0),
      cart_id: Number(item?.cart_id || 0),
      product_id: Number(item?.product_id || 0),
      product_name: product?.name || "",
      product_image: product?.images?.[0]?.url || "",
      product_category: product?.category?.name || "Khac",
      product_category_id: Number(product?.category?.id || 0),
      color_id: item?.color_id == null ? null : Number(item.color_id),
      color_name: item?.color?.color_name || "Mac dinh",
      quantity,
      unit_price: unitPrice,
      total_price: totalPrice,
      price_min_quantity: minQuantity,
      prices,
      unit: product?.unit || "",
      stock_quantity: Number(item?.stock_quantity || 0),
      availability_status: String(item?.availability_status || "available"),
      availability_message: String(item?.availability_message || ""),
      is_available: Boolean(item?.is_available ?? true),
      can_checkout: Boolean(item?.can_checkout ?? true),
    };
  }

  normalizeCart(data = {}) {
    return {
      id: Number(data?.id || 0),
      user_id: Number(data?.user_id || 0),
      items: Array.isArray(data?.items) ? data.items.map((item) => this.normalizeItem(item)) : [],
    };
  }

  getCountFromItems(items = []) {
    return (items || []).reduce((sum, item) => sum + Number(item?.quantity || 0), 0);
  }

  async list() {
    const res = (await this.api.get("/")).data;
    return this.normalizeCart(res?.data).items;
  }

  async getCart() {
    const res = (await this.api.get("/")).data;
    return this.normalizeCart(res?.data);
  }

  async getCount() {
    const cart = await this.getCart();
    return this.getCountFromItems(cart?.items || []);
  }

  async addItem(payload) {
    const body = {
      product_id: Number(payload?.product_id),
      color_id: payload?.color_id == null ? null : Number(payload.color_id),
      quantity: Number(payload?.quantity || 1),
    };

    const res = (await this.api.post("/", body)).data;
    return {
      message: res?.message || "Thêm sản phẩm vào giỏ hàng thành công",
      cart: this.normalizeCart(res?.data),
    };
  }

  async updateQuantity(cartId, cartDetailId, quantity) {
    const body = {
      cart_detail_id: Number(cartDetailId),
      quantity: Math.max(1, Number(quantity || 1)),
    };

    const res = (await this.api.patch(`/${Number(cartId)}`, body)).data;
    return {
      message: res?.message || "Cập nhật giỏ hàng thành công",
      cart: this.normalizeCart(res?.data),
    };
  }

  async removeItem(cartId, cartDetailId) {
    const res = (await this.api.delete(`/${Number(cartId)}`, {
      params: { cart_detail_id: Number(cartDetailId) },
    })).data;

    return {
      message: res?.message || "Xóa sản phẩm khỏi giỏ hàng thành công",
      cart: this.normalizeCart(res?.data),
    };
  }

  async clear(cartId) {
    const res = (await this.api.delete(`/${Number(cartId)}`)).data;
    return {
      message: res?.message || "Xóa toàn bộ giỏ hàng thành công",
      cart: this.normalizeCart(res?.data),
    };
  }
}

export default new CartService();
