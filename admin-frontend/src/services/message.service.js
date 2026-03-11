import { createApiClient } from "./api.service";

class MessageService {
  constructor(baseUrl = "/api/admin/messages") {
    this.api = createApiClient(baseUrl);
  }

  async fetchContacts(keyword = "") {
    const params = keyword ? { q: keyword } : undefined;
    return (await this.api.get("/contacts", { params })).data;
  }

  async ensureConversationWith(userId) {
    return (await this.api.post(`/with/${userId}`)).data;
  }

  async fetchMessages(conversationId) {
    return (await this.api.get(`/${conversationId}`)).data;
  }

  async sendMessage(conversationId, { content, files }) {
    const fd = new FormData();
    if (content) fd.append("content", content);
    (files || []).forEach((file) => fd.append("files[]", file));
    return (
      await this.api.post(`/${conversationId}/send`, fd, {
        headers: { "Content-Type": "multipart/form-data" },
      })
    ).data;
  }
}

export default new MessageService();
