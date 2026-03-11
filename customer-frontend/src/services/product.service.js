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

  async getHomeProducts(params = {}) {
    return (await this.api.get("/get-home-products", { params })).data;
  }

  async getCustomerProductDetail(id, params = {}) {
    return (await this.api.get(`/${id}/customer-detail`, { params })).data;
  }

  async getCustomerProductReviews(id) {
    return (await this.api.get(`/${id}/reviews`)).data;
  }
}

export default new ProductService();
