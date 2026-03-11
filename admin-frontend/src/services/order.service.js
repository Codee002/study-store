import { createApiClient } from "./api.service";

class OrderService {
  constructor(baseUrl = "/api/orders") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    return (await this.api.get("/", { params })).data;
  }

  async get(id) {
    return (await this.api.get(`/${id}`)).data;
  }

  async create(data) {
    return (await this.api.post("/", data)).data;
  }

  async getCreateMeta() {
    return (await this.api.get("/admin-create-meta")).data;
  }

  async approve(id, allocations) {
    return (await this.api.post(`/${id}/approve`, { allocations })).data;
  }

  async reject(id) {
    return (await this.api.post(`/${id}/reject`)).data;
  }
}

export default new OrderService();
