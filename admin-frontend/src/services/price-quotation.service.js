import { createApiClient } from "./api.service";

class PriceQuotationService {
  constructor(baseUrl = "/api/price-quotations") {
    this.api = createApiClient(baseUrl);
  }

  async downloadAdminExport(tierId) {
    return await this.api.get("/admin-export", {
      params: { tier_id: tierId },
      responseType: "blob",
    });
  }
}

export default new PriceQuotationService();
