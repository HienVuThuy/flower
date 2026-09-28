# Angevil — Hoa tươi & Cây cảnh

Website thương mại điện tử bán hoa và cây cảnh. Laravel 13 + Blade +
Bootstrap 5, không dùng framework JavaScript nào ở phía giao diện.

---

# Cài đặt trên máy mới (dành cho thành viên nhóm)

Làm lần lượt từ bước 1 đến bước 7. Cả quá trình khoảng 15–20 phút, phần
lớn là chờ `composer install` và `npm install` tải gói.

## 1. Cài sẵn những thứ này

| Cần | Bản | Lấy ở đâu |
|---|---|---|
| XAMPP (Apache + MariaDB) | PHP **8.3 trở lên** | https://www.apachefriends.org |
| Composer | 2.x | https://getcomposer.org/download |
| Node.js | 20 trở lên | https://nodejs.org (bản LTS) |
| Git | bất kỳ | https://git-scm.com |

Mở **Command Prompt** (hoặc PowerShell) kiểm tra:

```bash
php -v
composer -V
node -v
git --version
```

Nếu `php -v` báo "không nhận lệnh", nghĩa là PHP của XAMPP chưa nằm trong
PATH. Thêm `D:\xampp\php` (đổi theo ổ đĩa của bạn) vào biến môi trường
Path, rồi mở lại cửa sổ dòng lệnh.

**Bật các phần mở rộng PHP** — mở `D:\xampp\php\php.ini`, bỏ dấu `;` ở
đầu các dòng sau nếu đang có:

```
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=zip
extension=gd
extension=curl
```

Lưu lại rồi khởi động lại Apache. Thiếu `zip` thì `composer install` dừng
giữa chừng; thiếu `gd` thì không xuất được phiếu PDF và không tối ưu được
ảnh.

## 2. Lấy mã nguồn

Đặt dự án **ngoài** thư mục `htdocs` cũng được — dự án chạy bằng
`php artisan serve`, không chạy qua Apache.

```bash
git clone https://github.com/rin5/Angevil.git
cd Angevil
```

## 3. Cài thư viện

```bash
composer install
npm install
```

## 4. Tạo tệp cấu hình `.env`

```bash
copy .env.example .env
php artisan key:generate
```

`.env` **không** nằm trong kho Git (mỗi người một bản, chứa mật khẩu và
khoá dịch vụ), nên bước này bắt buộc.

Mở `.env` bằng Notepad, sửa khối cơ sở dữ liệu thành MySQL:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=btlar
DB_USERNAME=root
DB_PASSWORD=
```

(Mặc định trong `.env.example` là `sqlite` — dùng để chạy kiểm thử, không
dùng để chạy web.)

## 5. Tạo cơ sở dữ liệu

Bật **MySQL** trong XAMPP Control Panel, vào http://localhost/phpmyadmin,
bấm *New*, đặt tên `btlar`, bảng mã `utf8mb4_unicode_ci`, bấm *Create*.

Rồi chạy:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

- `migrate --seed` tạo toàn bộ bảng và đổ dữ liệu mẫu: danh mục, sản
  phẩm, thuế, thuộc tính cây, dịp tặng, tài khoản quản trị.
- `storage:link` tạo lối tắt `public/storage` — **không chạy thì mọi ảnh
  sản phẩm đều vỡ**.
- `npm run build` dựng CSS/JS. Thiếu bước này trang báo lỗi
  "Vite manifest not found".

## 6. Chạy

```bash
php artisan serve
```

Mở http://127.0.0.1:8000

> **Đừng mở qua đường `localhost/Angevil/public`.** Dự án chạy bằng
> `php artisan serve`; mở theo đường thư mục con của XAMPP thì mọi liên
> kết sinh ra bằng `route()` đều thiếu tiền tố và trang con sẽ 404.

Khi cần **sửa giao diện** (CSS/JS đổi là thấy ngay, không phải build
lại), mở thêm một cửa sổ dòng lệnh thứ hai:

```bash
npm run dev
```

Tài khoản quản trị mẫu: `admin@flowerplant.test` / `Admin@12345`
(**chỉ dùng khi phát triển** — xem `database/seeders/AdminUserSeeder.php`).
Trang quản trị ở `/admin`.

## 7. Khoá của dịch vụ bên ngoài (không bắt buộc)

Chạy học tập thì **để nguyên cũng dùng được**; phần nào chưa có khoá thì
giao diện tự báo là chưa cấu hình, không gọi ra ngoài và không bịa dữ liệu.

| Mục trong `.env` | Không điền thì sao | Muốn thử thật |
|---|---|---|
| `MOMO_*` | vẫn thanh toán thử được — `.env.example` đã có sẵn bộ khoá **môi trường thử** do MoMo công bố công khai | số thẻ thử ghi ngay trong `.env.example` |
| `GHN_TOKEN`, `GHN_SHOP_ID` | không tính được phí giao, không tạo được vận đơn | đăng ký ở https://5sao.ghn.dev rồi chạy `php artisan ghn:tra-dia-chi "Hà Nội"` để lấy `GHN_FROM_DISTRICT_ID` |
| `GEMINI_API_KEY` | khung chat hiện "Trợ lý AI chưa được cấu hình" | lấy khoá ở Google AI Studio |
| `MAIL_MAILER=log` | thư chỉ ghi vào `storage/logs/laravel.log`, không gửi đi đâu — **nên để nguyên khi học** | đổi sang `smtp` rồi điền thông tin nhà cung cấp |

**Không bao giờ commit `.env`.** Tệp này đã nằm trong `.gitignore`; đừng
dùng `git add -f .env`.

## Tiến trình chạy nền (chỉ khi cần)

Các việc tự động (nhắc tưới cây, đồng bộ vận đơn GHN, bật/tắt khuyến mại
theo ngày, sao lưu) chỉ chạy khi có một tiến trình lịch:

```bash
php artisan schedule:work
```

Nếu đặt `MAIL_QUEUE=true` thì phải chạy thêm `php artisan queue:work`,
không thì thư nằm im trong bảng `jobs` mãi mãi. Xem `docs/TU-DONG-HOA.md`.

## Khi gặp lỗi

| Hiện tượng | Nguyên nhân thường gặp |
|---|---|
| `Vite manifest not found` | chưa chạy `npm run build` |
| Ảnh sản phẩm vỡ hết | chưa chạy `php artisan storage:link` |
| `SQLSTATE[HY000] [1049] Unknown database 'btlar'` | chưa tạo database ở phpMyAdmin (bước 5) |
| `could not find driver` | chưa bật `extension=pdo_mysql` trong `php.ini` |
| Trang con 404 hết | đang mở qua `localhost/...` thay vì `php artisan serve` |
| MySQL trong XAMPP không khởi động được | đọc `docs/MYSQL-XAMPP.md` — **đừng cài lại XAMPP**, gần như luôn chỉ là bảng phân quyền bị hỏng |
| Sửa `.env` mà không thấy đổi gì | `php artisan config:clear` |

---

# Làm việc chung trên GitHub

Kho: https://github.com/rin5/Angevil — nhánh chính `main`.

**Lần đầu trên máy mới**, khai báo tên để commit ghi đúng người:

```bash
git config --global user.name "Tên của bạn"
git config --global user.email "email-github-cua-ban@example.com"
```

**Mỗi lần bắt đầu làm**, lấy phần người khác đã đẩy lên:

```bash
git pull --rebase origin main
```

**Làm xong một việc thì commit:**

```bash
git status
git add <những tệp đã sửa>
git commit -m "Mô tả ngắn việc đã làm"
git push origin main
```

Đừng dùng `git add .` cho nhanh — nó kéo theo cả tệp rác và cấu hình
riêng của máy bạn. Xem `git status` rồi thêm từng tệp.

Khi Git hỏi mật khẩu lúc push: GitHub **không** nhận mật khẩu tài khoản
nữa. Vào Settings → Developer settings → Personal access tokens → Tokens
(classic) → Generate new token, tích quyền `repo`, rồi dán chuỗi token đó
vào ô mật khẩu.

Tránh hai người cùng sửa một tệp. Nếu `git pull` báo xung đột, mở tệp,
xoá các dấu `<<<<<<<`, `=======`, `>>>>>>>` và giữ lại phần đúng, rồi
`git add` tệp đó và chạy `git rebase --continue`.

---

# Kiểm thử

```bash
php artisan test
```

1400 bài, chạy trên SQLite trong bộ nhớ nên không đụng tới dữ liệu thật.
Hết khoảng 7 phút. Muốn chạy riêng một nhóm:

```bash
php artisan test --filter=Momo
```

Trong đó có hai lưới an toàn đáng chú ý:

- `tests/Feature/Journal/JournalPrivacyTest.php` — bọc `DB::listen()`
  quanh bộ máy gợi ý và cố vấn giá, khẳng định **không câu SQL nào** chạm
  vào ba bảng nhật ký cá nhân.
- `tests/Feature/Smoke/RouteSmokeTest.php` — mở gần 50 đường dẫn GET (cả
  khách lẫn quản trị) và bắt mọi lỗi 5xx. Hết khoảng hai giây.

# Tài liệu

- `docs/DOMAIN-DECISIONS.md` — **đọc trước khi sửa bất cứ thứ gì.** 322
  quyết định nghiệp vụ đã chốt, mỗi mục ghi rõ: quyết định là gì, vì sao,
  và điều gì KHÔNG được làm.
- `docs/HUONG-DAN-DOC-CODE.txt` — đọc mã nguồn thì bắt đầu từ đâu.
- `docs/BAO-CAO-CHUC-NANG.txt` — danh sách chức năng, dùng khi viết báo cáo.
- `docs/TU-DONG-HOA.md` — việc nào chạy nền, chạy lúc nào.
- `docs/MYSQL-XAMPP.md` — MySQL trong XAMPP hỏng thì sửa thế nào.
- `docs/KIEM-THU.md` — cách viết kiểm thử trong dự án này.
- `ASSETS.md` — nguồn và giấy phép của toàn bộ ảnh. Mọi ảnh đều tải về
  máy, không hotlink từ website khác.

# Những chỗ dễ hiểu nhầm

- **Nhật ký cá nhân là riêng tư tuyệt đối.** Không bộ máy gợi ý, phân
  tích hay định giá nào được đọc ba bảng `journals`, `journal_entries`,
  `journal_metrics`. Xem QĐ-123 và QĐ-143.
- **Giá chỉ được tính ở một nơi**: `App\Services\Pricing\PricingService`.
  Không view hay controller nào tự tính giảm giá.
- **Tiền chỉ được định dạng ở một nơi**: `<x-site.money>`. Không gọi
  `number_format()` trực tiếp. Xem QĐ-120.
- **Chưa có dữ liệu thì nói là chưa có**, không hiện 0% và không ước
  lượng. Xem QĐ-127 và QĐ-136.
- **Ảnh trong bài Cẩm nang phải tải lên qua trang quản trị.** Thân bài
  chỉ nhận ảnh nằm trong kho của cửa hàng; dán đường dẫn ảnh của trang
  khác vào thì bộ lọc bỏ đi. Xem QĐ-322.
