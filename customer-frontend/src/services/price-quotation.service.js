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

  async validatePurchaseFile(rows = []) {
    return (await this.api.post("/validate-purchase-file", { rows })).data;
  }
}

export default new PriceQuotationService();
