import { createApiClient } from "./api.service";

class CheckoutService {
  constructor(baseUrl = "/api") {
    this.api = createApiClient(baseUrl);
    this.buyNowStorageKey = "checkout_buy_now_item";
  }

  async getDeliveryInfos() {
    const res = (await this.api.get("/delivery-infos")).data;
    return Array.isArray(res?.data) ? res.data : [];
  }

  async getDiscounts(params = {}) {
    const res = (await this.api.get("/discounts", { params })).data;
    return Array.isArray(res?.data?.items) ? res.data.items : [];
  }

  async getCheckoutOptions(payload = {}) {
    const res = (await this.api.post("/checkout/options", payload)).data;
    return {
      discounts: Array.isArray(res?.data?.discounts) ? res.data.discounts : [],
      payments: Array.isArray(res?.data?.payments) ? res.data.payments : [],
      summary: res?.data?.summary || {},
    };
  }

  async placeOrder(payload) {
    return (await this.api.post("/checkout/place", payload)).data;
  }

  async createVNPayPayment(payload) {
    return (await this.api.post("/checkout/vnpay/create", payload)).data;
  }

  async getVNPayStatus(txnRef) {
    return (await this.api.get("/checkout/vnpay/status", { params: { txn_ref: txnRef } })).data;
  }

  saveBuyNowItem(item) {
    if (!item) return;
    window.localStorage.setItem(this.buyNowStorageKey, JSON.stringify(item));
  }

  getBuyNowItem() {
    try {
      const raw = window.localStorage.getItem(this.buyNowStorageKey);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }

  clearBuyNowItem() {
    window.localStorage.removeItem(this.buyNowStorageKey);
  }
}

export default new CheckoutService();

