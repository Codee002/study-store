import { createApiClient } from "./api.service";

class EvaluateService {
  constructor(baseUrl = "/api/orders/evaluates") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    return (await this.api.get("/", { params })).data;
  }

  async reply(id, reply) {
    return (await this.api.post(`/${id}/reply`, { reply })).data;
  }
}

export default new EvaluateService();
