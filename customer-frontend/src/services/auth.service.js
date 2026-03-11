// src/services/auth.service.js
import { createApiClient } from "./api.service";

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
    }

    return res;
  }

  async logout() {
    try {
      localStorage.removeItem("currentUser");
      localStorage.removeItem("access_token");
      const res = await this.api.post("/logout");
    } catch (err) {
      console.error("Logout error:", err);
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
