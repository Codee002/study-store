// src/services/category.service.js
import { createApiClient } from "./api.service";

class WarehouseService {
  constructor(baseUrl = "/api/warehouses") {
    this.api = createApiClient(baseUrl);
  }
  // Lấy tất cả sản phẩm trong kho
  async getProductTotalQuantity(params = {}) {
    return (await this.api.get("/get-product-total-quantity", { params })).data;
  }
}

export default new WarehouseService();
