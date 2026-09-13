# Hiệu năng — đo được gì và làm gì với nó

> Chạy `php artisan do:hieu-nang` để đo lại bất cứ lúc nào.
> Chạy `php artisan anh:toi-uu` sau mỗi lần thêm ảnh sản phẩm.

## Kết quả

| | Trước | Sau | |
|---|---|---|---|
| TTFB trang chủ | 781 ms | **135 ms** | −83% |
| TTFB chi tiết sản phẩm | 776 ms | **114 ms** | −85% |
| Tải xong toàn trang | 2947 ms | **260 ms** | −91% |
| Ảnh trang danh sách | 1152 KB | **339 KB** | −71% |

## Nguyên nhân chính: OPcache

PHP biên dịch lại ~7000 tệp ở **mỗi** request. Ba dòng trong `php.ini`:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.interned_strings_buffer=16
```

Đã bật sẵn trên máy này. Bản sao lưu php.ini gốc nằm cạnh nó, tên có đuôi
`.truoc-toi-uu-<ngày giờ>` — muốn quay lại thì chép đè.

`extension=gd` cũng đã bật, để lệnh `anh:toi-uu` chuyển ảnh sang WebP được.

## Khi đưa lên máy chủ thật

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan anh:toi-uu
```

Và trong `.env`: `APP_ENV=production`, `APP_DEBUG=false`.

Bật `APP_DEBUG` trên máy chủ thật vừa chậm (ghi lại mọi truy vấn) vừa lộ
thông tin hệ thống khi có lỗi.

## Ảnh

`php artisan anh:toi-uu` sinh bản WebP ở hai bề ngang (400px cho thẻ sản
phẩm, 800px cho trang chi tiết) vào `storage/app/public/rp/`. Ảnh gốc
không bị đụng tới.

Trong Blade dùng `<x-site.image :path="..." :alt="..." />` thay cho `<img>`
— nó tự chọn bản đúng cỡ, kèm `width`/`height` chống nhảy bố cục, và lùi
về ảnh gốc nếu chưa sinh bản WebP.

Ảnh nằm trong màn hình đầu tiên thì thêm `:eager="true"`.

## Những gì KHÔNG cần làm

Đo được: trang dựng xong trong 12–137 ms, truy vấn chậm nhất 12,5 ms,
không có N+1.

Nên **không** cần: progressive loading, skeleton, tách trang thành nhiều
lời gọi AJAX, cache recommendation, hay tối ưu truy vấn. Với TTFB đã ~100
ms, chia một trang 132 ms thành 5 lời gọi chỉ làm nó chậm đi.

Xem QĐ-94 trong `DOMAIN-DECISIONS.md` để biết chi tiết từng mục.

---

## Sau mỗi lần cập nhật giao diện: chạy `php artisan quan-tri:lam-nong`

**Triệu chứng người dùng báo:** "bấm các mục trong trang quản trị mở chậm hơn
trước khi cập nhật".

**Đo được, không đoán** — cùng cơ sở dữ liệu, mã trước và sau đợt cập nhật,
gọi thẳng HTTP kernel:

| Trang | Lần mở đầu tiên sau khi sửa view | Từ lần thứ hai |
|---|---|---|
| Tổng quan | 8.524ms | 64–73ms |
| Đơn hàng | 1.183ms | 23–31ms |
| Phân tích | 1.131ms | 85–103ms |
| Sản phẩm | 838ms | 22–28ms |

Lúc đã "nóng", mã mới **không chậm hơn** mã cũ (Tổng quan 64ms so với 73ms).
Cái chậm là **Blade biên dịch lại view ở lần mở đầu tiên** sau mỗi lần sửa
layout, thanh bên hay bộ icon — mọi trang dùng chúng đều phải biên dịch lại.

**Cách xử lý:** chạy một lần sau khi cập nhật

    php artisan quan-tri:lam-nong

Lệnh mở mọi trang GET không tham số trong khu quản trị bằng đúng đường của một
request thật, nên mọi view và component được biên dịch sẵn. Không thay được
bằng `view:cache` trên Windows (xem mục trên về lệch dấu phân cách đường dẫn).

Cùng đợt đo tìm ra một N+1 thật: trang Trả hàng nhà cung cấp gọi `soDaTra()`
cho từng dòng — **130 truy vấn**. Nay tính sẵn bằng một truy vấn gom nhóm:
15 truy vấn, 29ms.
