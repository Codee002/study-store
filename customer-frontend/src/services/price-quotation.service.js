import { createApiClient } from "./api.service";

class PriceQuotationService {
  constructor(baseUrl = "/api/price-quotations") {
    this.api = createApiClient(baseUrl);
  }

  async downloadMyExport() {
    return await this.api.get("/my-export", {
      responseType: "blob",
    });
  }
}

export default new PriceQuotationService();
