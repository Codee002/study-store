# Hệ thống Quản Lý và Kinh Doanh Văn Phòng Phẩm

## Giới thiệu

Đây là dự án xây dựng hệ thống quản lý và kinh doanh văn phòng phẩm theo mô hình tách nhiều thành phần, gồm:

- `admin-frontend`: giao diện quản trị dành cho quản trị viên.
- `customer-frontend`: giao diện khách hàng dành cho người mua hàng.
- `backend`: API trung tâm xử lý nghiệp vụ, dữ liệu, xác thực và realtime.
- `ai-services`: dịch vụ AI phục vụ tìm kiếm, gợi ý và xử lý dữ liệu liên quan đến sản phẩm.

Mục tiêu của hệ thống là hỗ trợ quản lý danh mục sản phẩm, kho, nhập hàng, giá bán, đơn hàng, khách hàng, tin nhắn và các tác vụ hỗ trợ kinh doanh trong cùng một nền tảng.

## Kiến trúc tổng thể

Hệ thống được chia thành 4 khối chính:

1. `customer-frontend`
   Ứng dụng web cho khách hàng tra cứu sản phẩm, mua hàng, thanh toán, theo dõi đơn hàng và liên hệ với cửa hàng.

2. `admin-frontend`
   Ứng dụng web cho quản trị viên quản lý dữ liệu hệ thống như sản phẩm, danh mục, nhà cung cấp, kho, phiếu nhập, đơn hàng, khuyến mãi, người dùng và thống kê.

3. `backend`
   Xây dựng bằng Laravel, cung cấp REST API cho cả hai frontend, xử lý xác thực, phân quyền, nghiệp vụ đơn hàng, kho, thông báo, nhắn tin và phát sự kiện realtime.

4. `ai-services`
   Dịch vụ AI độc lập dùng để hỗ trợ tìm kiếm ngữ nghĩa, gợi ý sản phẩm và lưu trữ dữ liệu vector phục vụ tra cứu thông minh.

## Công nghệ sử dụng

### Frontend

- Vue 3
- Vue Router
- Vite
- Axios
- Vee Validate
- Yup
- SweetAlert2
- Day.js
- Laravel Echo
- Pusher JS
- Chart.js (ở trang quản trị)
- XLSX (ở giao diện khách hàng để xử lý dữ liệu bảng tính)

### Backend

- PHP 8.2
- Laravel 12
- Laravel Sanctum
- Laravel Broadcasting
- Queue Jobs
- Eloquent ORM
- PHPUnit và Pest
- PhpSpreadsheet
- Cloudinary Laravel
- Predis / Redis
- Pusher PHP Server

### AI Services

- Python
- FastAPI
- Dịch vụ tìm kiếm và gợi ý dựa trên vector
- Lưu trữ dữ liệu embedding và metadata sản phẩm

### Công cụ phát triển

- Node.js
- npm
- Composer
- Vite
- Docker Compose (được khai báo trong backend)

## Chức năng chính của dự án

### Chức năng phía khách hàng

- Đăng ký, đăng nhập và đăng xuất tài khoản khách hàng
- Xem trang chủ và danh sách sản phẩm
- Xem chi tiết sản phẩm
- Xem giá, báo giá và tra cứu thông tin mua hàng
- Thêm sản phẩm vào giỏ hàng
- Thực hiện đặt hàng
- Thanh toán trực tuyến qua VNPay
- Theo dõi danh sách đơn hàng và chi tiết đơn hàng
- Hủy đơn hoặc hoàn tất đơn ở các trạng thái phù hợp
- Đánh giá đơn hàng hoặc sản phẩm sau mua
- Cập nhật thông tin tài khoản cá nhân
- Nhắn tin liên hệ với quản trị viên
- Nhận thông báo realtime

### Chức năng phía quản trị

- Đăng nhập quản trị viên
- Xem dashboard tổng quan
- Quản lý danh mục sản phẩm
- Quản lý nhà cung cấp
- Quản lý cấp tài khoản
- Quản lý người dùng
- Quản lý sản phẩm
- Quản lý màu sắc và giá sản phẩm
- Quản lý kho hàng
- Quản lý phiếu nhập
- Quản lý phương thức thanh toán
- Quản lý chương trình khuyến mãi
- Quản lý đơn hàng
- Duyệt hoặc từ chối đơn hàng
- Xem và phản hồi đánh giá
- Quản lý tin nhắn với khách hàng
- Nhận thông báo realtime
- Xem thống kê sản phẩm và dữ liệu báo cáo

### Chức năng phía hệ thống

- Xác thực và bảo vệ API bằng Sanctum
- Phân tách luồng quản trị và khách hàng
- Phát thông báo và tin nhắn realtime qua Pusher/Echo
- Xuất dữ liệu phục vụ nghiệp vụ báo giá và thống kê
- Đồng bộ và tìm kiếm dữ liệu sản phẩm bằng dịch vụ AI
- Hỗ trợ gợi ý sản phẩm

## Cấu trúc thư mục

```text
Code/
|-- admin-frontend/
|-- customer-frontend/
|-- backend/
`-- ai-services/
```

### Mô tả nhanh

- `admin-frontend`: giao diện quản trị viên
- `customer-frontend`: giao diện người dùng mua hàng
- `backend`: API, nghiệp vụ, cơ sở dữ liệu, xác thực, sự kiện realtime
- `ai-services`: dịch vụ AI tìm kiếm và gợi ý sản phẩm

## Một số module nghiệp vụ nổi bật

- Quản lý sản phẩm, danh mục, màu sắc và hình ảnh
- Quản lý nhà cung cấp và nhập kho
- Quản lý tồn kho theo kho hàng
- Quản lý cấp tài khoản và hồ sơ đại lý
- Quản lý giỏ hàng, giao hàng và thanh toán
- Quản lý khuyến mãi và áp dụng giảm giá cho đơn hàng
- Quản lý đánh giá và phản hồi đánh giá
- Hệ thống nhắn tin giữa khách hàng và quản trị viên
- Hệ thống thông báo nội bộ theo thời gian thực
- Tìm kiếm, gợi ý sản phẩm bằng AI

## Cách chạy dự án

## 1. Chạy backend

Di chuyển vào thư mục `backend`:

```bash
cd backend
```

Cài đặt thư viện:

```bash
composer install
npm install
```

Tạo file môi trường và key ứng dụng:

```bash
copy .env.example .env
php artisan key:generate
```

Chạy migrate:

```bash
php artisan migrate
```

Chạy backend:

```bash
composer run dev
```

Hoặc chạy thủ công:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## 2. Chạy giao diện khách hàng

```bash
cd customer-frontend
npm install
npm run dev
```

## 3. Chạy giao diện quản trị

```bash
cd admin-frontend
npm install
npm run dev
```

## 4. Chạy AI Services

Di chuyển vào thư mục `ai-services` và chạy theo môi trường Python bạn đang cấu hình. Dịch vụ này hiện được tổ chức theo FastAPI với các nhóm API chính:

- `/search`
- `/recommend`
- `/events`

File khởi động chính:

```text
ai-services/main.py
```

## Yêu cầu môi trường

- PHP 8.2 trở lên
- Composer
- Node.js 20 trở lên
- npm
- Python 3
- Cơ sở dữ liệu phù hợp với cấu hình Laravel
- Redis hoặc dịch vụ tương đương nếu dùng queue/cache theo cấu hình thực tế
- Pusher hoặc hệ thống broadcasting tương thích

## Gợi ý cấu hình môi trường

Dự án có thể cần cấu hình các nhóm biến môi trường sau:

- Kết nối cơ sở dữ liệu
- URL backend API
- Sanctum / session / CORS
- Pusher / broadcasting
- Cloudinary
- Redis / queue
- VNPay
- Các khóa dịch vụ AI hoặc OpenAI nếu được tích hợp trong môi trường thực tế

## Đối tượng sử dụng

- Quản trị viên hệ thống
- Nhân sự vận hành kho và bán hàng
- Khách hàng mua văn phòng phẩm
- Đại lý hoặc tài khoản có cấp độ thành viên

## Hướng phát triển

- Hoàn thiện kiểm thử tự động cho các luồng nghiệp vụ quan trọng
- Bổ sung tài liệu triển khai môi trường production
- Hoàn thiện tài liệu API
- Mở rộng tính năng AI cho tìm kiếm và gợi ý thông minh hơn
- Tối ưu phân quyền và theo dõi lịch sử thao tác quản trị

## Ghi chú

README này mô tả dự án ở mức tổng thể cho toàn bộ hệ thống. Nếu cần triển khai hoặc phát triển từng phần riêng lẻ, nên đọc thêm mã nguồn trong từng thư mục thành phần để nắm cấu hình chi tiết hơn.
