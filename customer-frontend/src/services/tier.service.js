import { createApiClient } from "./api.service";

class TierService {
  constructor(baseUrl = "/api/tiers") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    return (await this.api.get("/", { params })).data;
  }
}

export default new TierService();
