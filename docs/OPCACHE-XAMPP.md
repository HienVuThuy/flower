# Thêm một extension PHP xong, cả trang lỗi 500 — vì opcache

> Viết sau sự cố ngày 12/09/2026. Mọi con số dưới đây đo được trên chính
> máy này, không phải chép từ đâu.

## Triệu chứng

Mở bất kỳ trang nào của `php artisan serve` cũng ra lỗi 500:

```
ArgumentCountError
openssl_error_string() expects exactly 0 arguments, 6 given
vendor\laravel\framework\src\Illuminate\Encryption\Encrypter.php:195
```

Dòng 195 của tệp đó **không hề gọi** `openssl_error_string()`. Nó gọi:

```php
$decrypted = \openssl_decrypt(
    $payload['value'], strtolower($this->cipher), $key, 0, $iv, $tag ?? ''
);
```

Sáu tham số — khớp với `openssl_decrypt`, không khớp với hàm nào trong
thông báo lỗi.

**Tải lại trang thì thông báo đổi:**

```
openssl_public_decrypt() expects at most 4 arguments, 6 given
```

Cùng một lời gọi, mỗi lần rơi vào một hàm khác. Đó không phải lỗi lập
trình — đó là **bảng hàm bị trỏ lệch**.

## Đo, đừng đoán

Bốn phép thử tách được nguyên nhân ra khỏi những thứ bị nghi oan:

| Phép thử | Kết quả |
|---|---|
| `php -r` chạy đúng đoạn giải mã đó | **chạy đúng** |
| `php vendor/bin/phpunit` (864 bài) | **xanh hết** |
| `php -S` phục vụ một tệp PHP trần gọi `openssl_decrypt` 6 tham số | **chạy đúng** |
| `php artisan serve` phục vụ chính ứng dụng | **lỗi 500** |

Nên: mã đúng, khoá đúng, extension đúng. Chỉ khác nhau ở **SAPI**.

Thu hẹp thêm bằng một tệp dò trong `public/`:

```
sau khi khoi dong ung dung  => 'xin chao'     (openssl_decrypt trần: đúng)
Crypt::encryptString        => LOI: openssl_public_decrypt() ...
```

`openssl_decrypt` gọi trực tiếp thì đúng; gọi từ trong tệp đã biên dịch
của Laravel thì sai. Khác biệt duy nhất giữa hai chỗ là **mã đã được
opcache biên dịch sẵn**.

Hỏi thẳng máy chủ:

```
opcache.enable       = '1'
opcache.enable_cli   = '0'
opcache_enabled      = true      <-- dù enable_cli = 0
sapi                 = cli-server
```

Đây là mấu chốt: **SAPI `cli-server` đọc `opcache.enable`, không đọc
`opcache.enable_cli`.** Nên `php artisan serve` CÓ opcache, còn `php -r`
và `phpunit` thì KHÔNG — đúng bằng ranh giới giữa "hỏng" và "chạy".

## Nguyên nhân

Trước đó vài tiếng, `extension=zip` được thêm vào `php.ini` (để xuất tệp
Excel). Thêm một extension là **thêm hàm vào bảng hàm của PHP**, và mọi
hàm đăng ký sau đó dịch chỗ.

Mã đã biên dịch nằm trong opcache giữ **con trỏ tới ô cũ**. Sau khi bảng
dịch chỗ, `\openssl_decrypt` trỏ vào ô của hàng xóm — lúc thì
`openssl_error_string`, lúc thì `openssl_public_decrypt`.

Vì sao khởi động lại một máy chủ không hết: trên Windows, opcache dùng
**một vùng nhớ chia sẻ có tên**, và mọi tiến trình PHP cùng cấu hình đều
gắn vào đúng vùng đó. Chừng nào còn **một** tiến trình giữ vùng nhớ ấy,
tiến trình mới sinh ra cũng nhận lại đống mã đã hỏng. Lúc xảy ra sự cố
máy đang có **hai** `php artisan serve` cùng chạy.

## Cách sửa

Tắt **tất cả** tiến trình PHP đang phục vụ web, rồi bật lại một cái:

```powershell
Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
  Where-Object { $_.CommandLine -match 'artisan serve' -or $_.CommandLine -match '-S 127\.0\.0\.1' } |
  ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
```

```bash
php artisan serve
```

Đo lại sau khi làm: trang chủ **200**, `/san-pham` **200**, `/gio-hang`
**200**, `/admin/dashboard` **302** (chuyển sang đăng nhập — đúng, vì
chưa đăng nhập). Opcache **vẫn bật** — không cần tắt nó.

Quan trọng: phải tắt **hết**, không phải chỉ cái đang lỗi. Tắt một cái
trong khi cái kia còn sống thì vùng nhớ hỏng vẫn còn đó, và máy chủ mới
gắn lại vào đúng nó — đã thử, vẫn lỗi 500.

## Để lần sau không mất thời gian

**Đổi `php.ini` xong thì tắt hết máy chủ PHP rồi bật lại.** Thêm hoặc bỏ
extension, đổi phiên bản PHP — đều như vậy. Không phải "khởi động lại cho
chắc": có lý do cụ thể, là bảng hàm đã dịch chỗ.

**Đừng để hai `php artisan serve` cùng chạy.** Chỉ một cái chiếm được
cổng, cái kia nằm im nhưng vẫn giữ vùng nhớ chung — và chính nó làm cho
việc khởi động lại trông như vô tác dụng. Kiểm tra bằng:

```powershell
Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Select-Object ProcessId, CommandLine
```

**Thông báo lỗi vô lý thì đừng sửa theo chữ trong thông báo.** Ở đây mà
đi sửa `Encrypter.php` hay đi thay khoá `APP_KEY` thì vừa mất thời gian
vừa hỏng thêm. Tên hàm trong thông báo không phải tên hàm mã nguồn gọi —
đó chính là manh mối, không phải chỗ cần sửa.
