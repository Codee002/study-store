// src/services/auth.service.js
import { createApiClient } from "./api.service";

function syncEchoAuthHeaders(token = "") {
  try {
    if (typeof window?.__resetCustomerEcho === "function") {
      window.__resetCustomerEcho(token);
      return;
    }

    const headers = {
      Accept: "application/json",
      Authorization: token ? `Bearer ${token}` : "",
    };

    const echo = window?.Echo;
    if (!echo?.connector?.pusher?.config?.auth) return;
    echo.connector.pusher.config.auth.headers = headers;
  } catch {
    // ignore
  }
}

function reconnectEcho() {
  try {
    const token = localStorage.getItem("access_token") || "";
    if (typeof window?.__resetCustomerEcho === "function") {
      window.__resetCustomerEcho(token);
      return;
    }

    const pusher = window?.Echo?.connector?.pusher;
    if (!pusher) return;
    pusher.disconnect();
    pusher.connect();
  } catch {
    // ignore
  }
}

class AuthService {
  constructor(baseUrl = "/api/customer/auth") {
    this.api = createApiClient(baseUrl);
  }

  async register(values) {
    const fd = new FormData();

    fd.append("email", values.email);
    fd.append("phone", values.phone);
    fd.append("name", values.name);
    fd.append("username", values.username);
    fd.append("password", values.password);
    fd.append("password_confirmation", values.password_confirmation);

    fd.append("birthday", values.birthday);
    fd.append("gender", values.gender);

    fd.append("agree", values.agree ? "1" : "0");

    return (await this.api.post("/register", fd)).data;
  }

  async login(values) {
    const payload = {
      username: values.username,
      password: values.password,
    };

    const res = (await this.api.post("/login", payload)).data;
    console.log("Login response:", res);
    if (res?.access_token) {
      localStorage.setItem("currentUser", JSON.stringify(res.user));
      localStorage.setItem("access_token", res.access_token);
      syncEchoAuthHeaders(res.access_token);
      reconnectEcho();
      window.dispatchEvent(new Event("customer-user-updated"));
    }

    return res;
  }

  async logout() {
    const currentToken = localStorage.getItem("access_token") || "";
    try {
      syncEchoAuthHeaders(currentToken);
      await this.api.post("/logout");
    } catch (err) {
      console.error("Logout error:", err);
    } finally {
      localStorage.removeItem("currentUser");
      localStorage.removeItem("access_token");
      syncEchoAuthHeaders("");
      reconnectEcho();
    }
  }

  async me() {
    const res = (await this.api.get("/me")).data;
    return res;
  }

  isLoggin() {
    return Boolean(localStorage.getItem("access_token"));
  }
}

export default new AuthService();
