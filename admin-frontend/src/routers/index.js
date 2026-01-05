import { createRouter, createWebHistory } from "vue-router";
import DashboardView from "../views/DashboardView.vue";
import LoginView from "../views/auth/LoginView.vue";

// Category pages
import CategoryListView from "../views/categories/CategoryListView.vue";
import CategoryCreateView from "../views/categories/CategoryCreateView.vue";
import CategoryDetailView from "../views/categories/CategoryDetailView.vue";
import CategoryEditView from "../views/categories/CategoryEditView.vue";

// Supplier pages
import SupplierListView from "../views/suppliers/SupplierListView.vue";
import SupplierCreateView from "../views/suppliers/SupplierCreateView.vue";
import SupplierEditView from "../views/suppliers/SupplierEditView.vue";
import SupplierDetailView from "../views/suppliers/SupplierDetailView.vue";

// Tier pages
import TierListView from "../views/tiers/TierListView.vue";
import TierCreateView from "../views/tiers/TierCreateView.vue";
import TierEditView from "../views/tiers/TierEditView.vue";
import TierDetailView from "../views/tiers/TierDetailView.vue";

// function requireAuth(to, from, next) {
//   const token = localStorage.getItem("admin_token");
//   if (!token && to.name !== "login") return next({ name: "login" });
//   next();
// }

const routes = [
  { path: "/login", name: "login", component: LoginView },
  {
    path: "/",
    name: "dashboard",
    component: DashboardView,
    // beforeEnter: requireAuth,
  },

  // Categories
  { path: "/categories", name: "categories.list", component: CategoryListView },
  {
    path: "/categories/create",
    name: "categories.create",
    component: CategoryCreateView,
  },
  {
    path: "/categories/:id",
    name: "categories.detail",
    component: CategoryDetailView,
    props: true,
  },
  {
    path: "/categories/:id/edit",
    name: "categories.edit",
    component: CategoryEditView,
    props: true,
  },

  //   Suppliers
  { path: "/suppliers", name: "suppliers.list", component: SupplierListView },
  {
    path: "/suppliers/create",
    name: "suppliers.create",
    component: SupplierCreateView,
  },
  {
    path: "/suppliers/:id/edit",
    name: "suppliers.edit",
    component: SupplierEditView,
    props: true,
  },
  {
    path: "/suppliers/:id",
    name: "suppliers.detail",
    component: SupplierDetailView,
    props: true,
  },

  //   Tiers
  { path: "/tiers", name: "tiers.list", component: TierListView },
  { path: "/tiers/create", name: "tiers.create", component: TierCreateView },
  {
    path: "/tiers/:id/edit",
    name: "tiers.edit",
    component: TierEditView,
    props: true,
  },
  {
    path: "/tiers/:id",
    name: "tiers.detail",
    component: TierDetailView,
    props: true,
  },
];

export default createRouter({
  history: createWebHistory(),
  routes,
});
