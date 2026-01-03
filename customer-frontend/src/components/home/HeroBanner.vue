<template>
  <section class="hero">
    <div class="container">
      <div class="hero-card shadow-sm">
        <img class="hero-img" :src="bannerUrl" alt="Back to school banner" />
        <div class="hero-overlay">
          <div class="hero-content">
            <span class="chip"
              ><i class="fa-solid fa-bolt me-2"></i>Back to school</span
            >
            <h2 class="title">Ưu đãi mùa tựu trường</h2>
            <p class="sub">
              Sản phẩm hot, giá tốt, cập nhật theo tier khi đăng nhập.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-main px-4">
                <i class="fa-solid fa-bag-shopping me-2"></i>Mua ngay
              </button>
              <button class="btn btn-outline-secondary px-4">Xem thêm</button>
            </div>
          </div>
        </div>
      </div>

      <!-- quick highlights -->
      <div class="row g-3 mt-3">
        <div class="col-12 col-md-4">
          <div class="mini-card">
            <i class="fa-solid fa-truck-fast"></i>
            <div>
              <div class="fw-bold">Giao nhanh</div>
              <div class="text-muted small">Tích hợp GHN (sắp có)</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="mini-card">
            <i class="fa-solid fa-tags"></i>
            <div>
              <div class="fw-bold">Giá theo tier</div>
              <div class="text-muted small">Retail / Dealer</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="mini-card">
            <i class="fa-solid fa-shield"></i>
            <div>
              <div class="fw-bold">Đổi trả dễ</div>
              <div class="text-muted small">Hỗ trợ trong 7 ngày</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
// bạn đặt ảnh tại src/assets/banner.jpg
import bannerUrl from "@/assets/banner.jpg";
</script>

<style scoped>
.hero {
  padding: 1.25rem 0;
}
.hero-card {
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
}
.hero-img {
  width: 100%;
  height: 30rem;
  object-fit: cover;
  display: block;
}
.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.05));
  display: flex;
  align-items: center;
}
.hero-content {
  color: #fff;
  padding: 1.5rem;
  max-width: 520px;
}
.chip {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.25);
  margin-bottom: 0.75rem;
}
.title {
  font-weight: 800;
  margin: 0 0 0.35rem;
}
.sub {
  opacity: 0.95;
  margin: 0 0 1rem;
}

.btn-main {
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
  font-weight: 700;
}
.btn-main:hover {
  filter: var(--brightness);
}

.mini-card {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  padding: 0.85rem 1rem;
  border-radius: 16px;
  border: 1px solid var(--border-color);
  background: var(--main-extra-bg);
}
.mini-card i {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--hover-background-color);
  border: 1px solid var(--hover-border-color);
  color: var(--dark);
}
</style>
