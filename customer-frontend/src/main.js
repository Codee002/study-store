import { createApp } from "vue";
import router from "./routers";
import App from "./App.vue";

// CSS
import "./assets/styles/global.css";

// Pusher Laravel
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;
const API_BASE = import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000";

function buildEcho(token = "") {
  return new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_APP_PUSHER_APP_KEY,
    cluster: "ap1",
    forceTLS: (import.meta.env.VITE_APP_PUSHER_APP_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: `${API_BASE}/api/broadcasting/auth`,
    auth: {
      headers: {
        Accept: "application/json",
        Authorization: token ? `Bearer ${token}` : "",
      },
    },
  });
}

window.__resetCustomerEcho = (token = "") => {
  try {
    window.Echo?.disconnect?.();
  } catch {
    // ignore
  }

  window.Echo = buildEcho(token);
  return window.Echo;
};

window.__resetCustomerEcho(localStorage.getItem("access_token") || "");

const app = createApp(App);

app.use(router).mount("#app");
