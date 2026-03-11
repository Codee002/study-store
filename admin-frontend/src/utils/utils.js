const toNumber = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
};

const toInt = (value) => {
  const n = toNumber(value);
  return Math.round(n);
};

const formatMoney = (value) => {
  const n = toInt(value);
  return new Intl.NumberFormat("vi-VN").format(n) + " ₫";
};

const getProductThumb = (product) => {
  if (!product) return "";
  const first = product?.images?.[0]?.url || "";
  return first || "";
};

const statusLabel = (s) => {
  const v = String(s || "").toLowerCase();
  if (v === "pending") return "Đang duyệt";
  if (v === "completed") return "Hoàn thành";
  if (v === "canceled" || v === "cancelled") return "Đã hủy";
  return "—";
};

const statusBadgeClass = (s) => {
  const v = String(s || "").toLowerCase();
  if (v === "pending") return "badge-pending";
  if (v === "completed") return "badge-completed";
  if (v === "canceled" || v === "cancelled") return "badge-canceled";
  return "badge-secondary";
};

const statusTableBadgeClass = (status) => {
  switch (status) {
    case "pending":
      return "status-pending";
    case "completed":
      return "status-completed";
    case "canceled":
      return "status-canceled";
    default:
      return "bg-secondary-subtle text-secondary";
  }
};

const formatDateTimeVN = (input) => {
  if (!input) return "—";

  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return "—";

  const hh = String(d.getHours()).padStart(2, "0");
  const mm = String(d.getMinutes()).padStart(2, "0");
  const dd = String(d.getDate()).padStart(2, "0");
  const MM = String(d.getMonth() + 1).padStart(2, "0");
  const yyyy = d.getFullYear();

  return `${hh}:${mm} ${dd}/${MM}/${yyyy}`;
};

export {
  formatMoney,
  getProductThumb,
  statusLabel,
  statusBadgeClass,
  formatDateTimeVN,
  statusTableBadgeClass,
};
