<template>
  <footer class="app-footer mt-5 border-top">
    <div class="container py-5">
      <div class="row g-4">
        <div class="col-12 col-lg-4">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="logo-circle"><i class="fa-solid fa-pencil"></i></span>
            <span class="fw-bold fs-5">Study Store</span>
          </div>
          <p class="text-muted mb-3">
            Văn phòng phẩm dễ thương – giao nhanh – giá tốt. Mọi sản phẩm sẽ
            được cập nhật theo tier khi bạn đăng nhập.
          </p>

          <div class="d-flex gap-2">
            <a class="social" href="#"
              ><i class="fa-brands fa-facebook-f"></i
            ></a>
            <a class="social" href="#"
              ><i class="fa-brands fa-instagram"></i
            ></a>
            <a class="social" href="#"><i class="fa-brands fa-tiktok"></i></a>
            <a class="social" href="#"><i class="fa-brands fa-youtube"></i></a>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <h6 class="fw-bold mb-3">Liên hệ</h6>
          <ul class="list-unstyled text-muted mb-0">
            <li class="mb-2">
              <i class="fa-solid fa-location-dot me-2"></i>TP.HCM, Việt Nam
            </li>
            <li class="mb-2">
              <i class="fa-solid fa-phone me-2"></i>0900 000 000
            </li>
            <li class="mb-2">
              <i class="fa-solid fa-envelope me-2"></i>support@studystore.vn
            </li>
            <li class="mb-2">
              <i class="fa-solid fa-clock me-2"></i>8:00 - 22:00 (T2 - CN)
            </li>
          </ul>
        </div>

        <div class="col-12 col-lg-4">
          <h6 class="fw-bold mb-3">Hệ thống</h6>
          <div class="d-grid gap-2">
            <a class="footer-link" href="#"
              ><i class="fa-solid fa-circle-info me-2"></i>Giới thiệu</a
            >
            <a class="footer-link" href="#"
              ><i class="fa-solid fa-truck-fast me-2"></i>Giao hàng (GHN)</a
            >
            <a class="footer-link" href="#"
              ><i class="fa-solid fa-shield me-2"></i>Chính sách</a
            >
            <a class="footer-link" href="#"
              ><i class="fa-solid fa-headset me-2"></i>Hỗ trợ</a
            >
          </div>
        </div>
      </div>

      <hr class="my-4" />
      <div
        class="d-flex flex-column flex-md-row justify-content-between gap-2 text-muted small"
      >
        <span>© {{ new Date().getFullYear() }} Study Store</span>
        <span>Powered by Study Store System</span>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.app-footer {
  background: var(--main-extra-bg);
}
.logo-circle {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--main-color);
  border: 1px solid var(--hover-border-color);
  border-radius: 12px;
  color: var(--dark);
}
.social {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border-color);
  color: var(--font-color);
  background: var(--main-extra-bg);
  text-decoration: none;
}
.social:hover {
  background: var(--hover-background-color);
  border-color: var(--hover-border-color);
}
.footer-link {
  text-decoration: none;
  color: var(--font-color);
  border-radius: 12px;
  background: var(--main-extra-bg);
}
</style>
