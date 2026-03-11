import { createRouter, createWebHistory } from "vue-router";
import LoginView from "@/views/auth/LoginView.vue";
import RegisterView from "@/views/auth/RegisterView.vue";
import ProductsView from "@/views/ProductsView.vue";
import AccountSettingsView from "@/views/account/AccountSettingsView.vue";
import ContactListView from "@/views/messages/ContactListView.vue";
import ContactChatView from "@/views/messages/ContactChatView.vue";
import AuthService from "@/services/auth.service";

const routes = [
  {
    path: "/login",
    name: "login",
    component: LoginView,
    meta: { title: "Đăng nhập" },
  },
  {
    path: "/register",
    name: "register",
    component: RegisterView,
    meta: { title: "Đăng ký" },
  },
  {
    path: "/home",
    name: "home",
    component: () => import("@/views/HomeView.vue"),
    meta: { title: "Trang chủ", requeresAuth: true },
  },
  {
    path: "/products",
    name: "products",
    component: ProductsView,
    meta: { title: "Sản phẩm", requeresAuth: true },
  },
  {
    path: "/products/:id",
    name: "product-detail",
    component: () => import("@/views/ProductDetailView.vue"),
    meta: { title: "Chi tiết sản phẩm", requeresAuth: true },
  },
  {
    path: "/cart",
    name: "cart",
    component: () => import("@/views/CartView.vue"),
    meta: { title: "Giỏ hàng", requeresAuth: true },
  },
  {
    path: "/checkout",
    name: "checkout",
    component: () => import("@/views/CheckoutView.vue"),
    meta: { title: "Đặt hàng", requeresAuth: true },
  },
  {
    path: "/payment/vnpay-result",
    name: "vnpay-result",
    component: () => import("@/views/VNPayResultView.vue"),
    meta: { title: "Kết quả thanh toán", requeresAuth: true },
  },  {
    path: "/orders",
    name: "orders",
    component: () => import("@/views/OrdersView.vue"),
    meta: { title: "Đơn hàng của tôi", requeresAuth: true },
  },
  {
    path: "/price-quotation",
    name: "price-quotation",
    component: () => import("@/views/PriceQuotationView.vue"),
    meta: { title: "Báo giá và tra cứu giá", requeresAuth: true },
  },
  {
    path: "/orders/:id",
    name: "order-detail",
    component: () => import("@/views/OrderDetailView.vue"),
    meta: { title: "Chi tiết đơn hàng", requeresAuth: true },
  },
  {
    path: "/contact",
    name: "contact.list",
    component: ContactListView,
    meta: { title: "Liên hệ", requeresAuth: true },
  },
  {
    path: "/contact/:id",
    name: "contact.chat",
    component: ContactChatView,
    meta: { title: "Trò chuyện", requeresAuth: true },
    props: true,
  },
  {
    path: "/account/settings",
    name: "account-settings",
    component: AccountSettingsView,
    meta: { title: "Cài đặt", requeresAuth: true },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const isLoggin = AuthService.isLoggin();
  const guestOnlyRoutes = ["login", "register"];

  if (isLoggin && guestOnlyRoutes.includes(String(to.name || ""))) {
    return next({ name: "home" });
  }

  if (!isLoggin && !guestOnlyRoutes.includes(String(to.name || ""))) {
    return next({ name: "login" });
  }

  if (to.meta.requeresAuth && !isLoggin) {
    return next({ name: "login" });
  }

  next();
});

router.afterEach((to) => {
  document.title = to.meta.title || "Quản lý hệ thống văn phòng phẩm";
});

export default router;

