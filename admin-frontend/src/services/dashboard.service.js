import { createApiClient } from "./api.service";

class DashboardService {
  constructor(baseUrl = "/api/admin/dashboard") {
    this.api = createApiClient(baseUrl);
  }

  async summary(params = {}) {
    return (await this.api.get("/summary", { params })).data;
  }
}

export default new DashboardService();
