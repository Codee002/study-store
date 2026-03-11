import { createApiClient } from "./api.service";

class ProductStatsService {
  constructor(baseUrl = "/api/admin/product-stats") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    return (await this.api.get("/", { params })).data;
  }

  async export(params = {}) {
    return await this.api.get("/export", { params, responseType: "blob" });
  }
}

export default new ProductStatsService();
