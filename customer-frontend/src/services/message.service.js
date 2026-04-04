import { createApiClient } from "./api.service";

class MessageService {
  constructor(baseUrl = "/api/customer/messages") {
    this.api = createApiClient(baseUrl);
  }

  async ensureConversation() {
    return (await this.api.post("/start")).data;
  }

  async ensureChatboxConversation() {
    return (await this.api.post("/chatbox/start")).data;
  }

  async fetchInbox() {
    return (await this.api.get("/inbox")).data;
  }

  async fetchMessages(conversationId) {
    return (await this.api.get(`/${conversationId}`)).data;
  }

  async sendMessage(conversationId, { content, files, productIds }) {
    const fd = new FormData();
    if (content) {
      fd.append("content", content);
    }
    (files || []).forEach((file) => fd.append("files[]", file));
    (productIds || []).forEach((productId) => fd.append("product_ids[]", productId));

    return (
      await this.api.post(`/${conversationId}/send`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      })
    ).data;
  }

  async recallMessage(conversationId, messageId) {
    return (await this.api.post(`/${conversationId}/messages/${messageId}/recall`)).data;
  }
}

export default new MessageService();
