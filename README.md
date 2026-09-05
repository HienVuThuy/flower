# Angevil — Hoa tươi & Cây cảnh

Website thương mại điện tử bán hoa và cây cảnh. Laravel 13 + Blade +
Bootstrap 5, không dùng framework JavaScript nào ở phía giao diện.

## Chạy trên máy

Cần: PHP 8.4, MariaDB/MySQL (XAMPP), Node 20+, Composer.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Tạo cơ sở dữ liệu tên `btlar` trong phpMyAdmin trước, rồi:
php artisan migrate --seed
php artisan storage:link

npm run build
php artisan serve
```

Mở http://127.0.0.1:8000

> **Đừng mở qua đường `localhost/btlar/public`.** Dự án chạy bằng
> `php artisan serve`; mở theo đường thư mục con của XAMPP thì mọi liên
> kết sinh ra bằng `route()` đều thiếu tiền tố và trang con sẽ 404.

Tài khoản quản trị mẫu: `admin@flowerplant.test` / `Admin@12345`
(**chỉ dùng khi phát triển** — xem `database/seeders/AdminUserSeeder.php`).

## Kiểm thử

```bash
php artisan test
```

432 bài, chạy trên SQLite trong bộ nhớ nên không đụng tới dữ liệu thật.

Trong đó có hai lưới an toàn đáng chú ý:

- `tests/Feature/Journal/JournalPrivacyTest.php` — bọc `DB::listen()`
  quanh bộ máy gợi ý và cố vấn giá, khẳng định **không câu SQL nào** chạm
  vào ba bảng nhật ký cá nhân.
- `tests/Feature/Smoke/RouteSmokeTest.php` — mở gần 50 đường dẫn GET (cả
  khách lẫn quản trị) và bắt mọi lỗi 5xx. Hết khoảng hai giây.

## Tài liệu

- `docs/DOMAIN-DECISIONS.md` — **đọc trước khi sửa bất cứ thứ gì.** 155
  quyết định nghiệp vụ đã chốt, mỗi mục ghi rõ: quyết định là gì, vì sao,
  và điều gì KHÔNG được làm.
- `ASSETS.md` — nguồn và giấy phép của toàn bộ ảnh. Mọi ảnh đều tải về
  máy, không hotlink từ website khác.

## Đưa lên GitHub

Kho Git đã khởi tạo sẵn ở máy (nhánh `main`, commit đầu đã có). Chưa có
kho trên GitHub — bước đó cần đăng nhập nên phải tự chạy.

**Cách 1 — qua giao diện web (không cần cài gì thêm):**

1. Vào https://github.com/new
2. Repository name: `angevil` · **để Private** · **KHÔNG** tích thêm
   README, .gitignore hay license (kho ở máy đã có sẵn)
3. Bấm *Create repository*, rồi chạy:

```bash
git remote add origin https://github.com/<tên-tài-khoản>/angevil.git
git push -u origin main
```

**Cách 2 — bằng GitHub CLI** (nếu đã cài `gh`):

```bash
gh auth login
gh repo create angevil --private --source=. --remote=origin --push
```

> **Nên để Private.** Kho có ảnh chụp màn hình và dữ liệu mẫu của cửa
> hàng. `.env` đã nằm trong `.gitignore` nên token GHN và mật khẩu thư
> không bị đẩy lên — nhưng đừng bao giờ `git add -f .env`.

Nếu Git hỏi tài khoản khi push, dùng **Personal Access Token** thay cho
mật khẩu: Settings → Developer settings → Personal access tokens →
Tokens (classic) → Generate new token → tích quyền `repo`.

## Những chỗ dễ hiểu nhầm

- **Nhật ký cá nhân là riêng tư tuyệt đối.** Không bộ máy gợi ý, phân
  tích hay định giá nào được đọc ba bảng `journals`, `journal_entries`,
  `journal_metrics`. Xem QĐ-123 và QĐ-143.
- **Giá chỉ được tính ở một nơi**: `App\Services\Pricing\PricingService`.
  Không view hay controller nào tự tính giảm giá.
- **Tiền chỉ được định dạng ở một nơi**: `<x-site.money>`. Không gọi
  `number_format()` trực tiếp. Xem QĐ-120.
- **Chưa có dữ liệu thì nói là chưa có**, không hiện 0% và không ước
  lượng. Xem QĐ-127 và QĐ-136.
