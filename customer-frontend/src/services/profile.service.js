import { createApiClient } from "./api.service";

class ProfileService {
  constructor() {
    this.api = createApiClient("/api");
    this.authApi = createApiClient("/api/customer/auth");
  }

  async getProfile() {
    const res = (await this.api.get("/profile")).data;
    return res?.data || null;
  }

  async updateProfile(payload) {
    return (await this.api.post("/profile", payload)).data;
  }

  async getDealerRegistrationMeta() {
    return (await this.api.get("/dealer-registration/meta")).data;
  }

  async registerDealer(payload) {
    return (await this.api.post("/dealer-registration", payload)).data;
  }

  async changePassword(payload) {
    return (await this.authApi.post("/change-password", payload)).data;
  }

  async getDeliveryInfos() {
    const res = (await this.api.get("/delivery-infos")).data;
    return Array.isArray(res?.data) ? res.data : [];
  }

  async createDeliveryInfo(payload) {
    return (await this.api.post("/delivery-infos", payload)).data;
  }

  async updateDeliveryInfo(id, payload) {
    return (await this.api.patch(`/delivery-infos/${id}`, payload)).data;
  }

  async setDefaultDeliveryInfo(id) {
    return (await this.api.patch(`/delivery-infos/${id}/set-default`)).data;
  }
}

export default new ProfileService();
