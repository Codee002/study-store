import { createApiClient } from "./api.service";

class NotificationService {
  constructor(baseUrl = "/api/customer/notifications") {
    this.api = createApiClient(baseUrl);
  }

  async list() {
    return (await this.api.get("/")).data;
  }

  async markAsRead(id) {
    if (!id) return null;
    return (await this.api.post(`/${id}/read`)).data;
  }

  async markAllAsRead() {
    return (await this.api.post("/read-all")).data;
  }
}

export default new NotificationService();
