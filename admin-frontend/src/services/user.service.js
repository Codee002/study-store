import { createApiClient } from "./api.service";

class UserService {
  constructor(baseUrl = "/api/users") {
    this.api = createApiClient(baseUrl);
  }

  async getAll(params = {}) {
    return (await this.api.get("/", { params })).data;
  }

  async get(id) {
    return (await this.api.get(`/${id}`)).data;
  }

  async setTier(id, data) {
    return (await this.api.post(`/${id}/set-tier`, data)).data;
  }

  async updateDealerStatus(id, data) {
    return (await this.api.post(`/${id}/dealer-profile/status`, data)).data;
  }

  async updateStatus(id, data) {
    return (await this.api.post(`/${id}/status`, data)).data;
  }
}

export default new UserService();
