// src/services/product.service.js
import { createApiClient } from "./api.service";

class ProductService {
  constructor(baseUrl = "/api/products") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    return (await this.api.get("/", { params })).data;
  }

  async create(formData) {
    return (await this.api.post("/", formData)).data;
  }

  async get(id) {
    return (await this.api.get(`/${id}`)).data;
  }

  async update(id, formData) {
    // console.log(formData);
    console.log("FormData entries:");
    for (const pair of formData.entries()) {
      console.log(pair[0] + ": ", pair[1]);
    }
    return (await this.api.post(`/${id}`, formData)).data;
  }

  async delete(id) {
    return (await this.api.delete(`/${id}`)).data;
  }

  async saveProductPrices(id, payload) {
    return (await this.api.post(`/${id}/save-product-prices`, payload)).data;
  }

  async getPurchaseStats(id, params = {}) {
    return (await this.api.get(`/${id}/purchase-stats`, { params })).data;
  }
}

export default new ProductService();
