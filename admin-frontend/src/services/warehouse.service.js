// src/services/category.service.js
import { createApiClient } from "./api.service";

class CategoryService {
  constructor(baseUrl = "/api/warehouses") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    console.log(params);
    return (await this.api.get("/", { params })).data;
  }

  async create(data) {
    return (await this.api.post("/", data)).data;
  }

  async deleteAll() {
    return (await this.api.delete("/")).data;
  }

  async get(id) {
    return (await this.api.get(`/${id}`)).data;
  }

  async update(id, data) {
    return (await this.api.put(`/${id}`, data)).data;
  }

  async delete(id) {
    return (await this.api.delete(`/${id}`)).data;
  }

  async toggleStatus(warehouseDetailId) {
    return (await this.api.patch(`/${warehouseDetailId}/toggle-status`)).data;
  }

  // Lấy chi tiết có phân trang
  async getDetails(id, params = {}) {
    return (await this.api.get(`/${id}/details`, {params})).data;
  }
}

export default new CategoryService();
