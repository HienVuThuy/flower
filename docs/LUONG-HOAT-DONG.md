# Luồng hoạt động — đi từ file nào đến file nào

Tài liệu này trả lời một câu duy nhất: **bấm vào đó thì code chạy qua
những tệp nào, theo thứ tự nào.**

Các luồng được mô tả ở đây:

1. [Xác thực email bằng mã OTP](#1-xác-thực-email-bằng-mã-otp)
2. [Giỏ hàng → đặt hàng](#2-giỏ-hàng--đặt-hàng)
3. [Thêm vào giỏ mà không tải lại trang](#3-thêm-vào-giỏ-mà-không-tải-lại-trang)
4. [Dòng thời gian đơn hàng](#4-dòng-thời-gian-đơn-hàng)
5. [Nhật ký thao tác quản trị](#5-nhật-ký-thao-tác-quản-trị)
6. [Khoá tài khoản](#6-khoá-tài-khoản)
7. [Sắp xếp và thao tác hàng loạt ở khu quản trị](#7-sắp-xếp-và-thao-tác-hàng-loạt-ở-khu-quản-trị)

---

# 1. Xác thực email bằng mã OTP

## 1.1. Toàn cảnh trong 6 dòng

```
Khách bấm "Đăng ký"
   → hệ thống tạo tài khoản, CHƯA đánh dấu đã xác thực
   → sinh mã 6 chữ số, lưu BĂM vào CSDL, gửi mã gốc qua email
   → đưa khách tới trang nhập mã
   → khách gõ đúng mã  → đánh dấu đã xác thực, xoá mã
   → khách gõ sai / hết hạn → báo lỗi, còn 4 lần thử
```

Chưa xác thực thì **không vào được các trang cá nhân** (hồ sơ, sổ địa
chỉ, lịch sử đơn, ví voucher, yêu thích, lịch chăm cây) — nhưng **vẫn
mua hàng bình thường**.

## 1.2. Bước 1 — Đăng ký

| # | Tệp | Làm gì |
|---|---|---|
| 1 | `resources/views/auth/register.blade.php` | Biểu mẫu đăng ký, `POST /register` |
| 2 | `routes/web.php` | Khớp route → gọi `AuthController@register` |
| 3 | `app/Http/Requests/Auth/RegisterRequest.php` | Kiểm tra tên / email / mật khẩu |
| 4 | `app/Http/Controllers/Auth/AuthController.php` → `register()` | Tạo `User`, gán `role = customer`, `Auth::login()` |
| 5 | `app/Services/Cart/CartService.php` → `mergeSessionCartInto()` | Gộp giỏ khách vãng lai vào tài khoản mới |
| 6 | `app/Services/Auth/EmailVerifier.php` → `send()` | Sinh mã, lưu băm, gọi gửi thư |
| 7 | `app/Models/EmailVerificationCode.php` | Ghi vào bảng `email_verification_codes` |
| 8 | `app/Mail/EmailVerificationMail.php` | Dựng lá thư |
| 9 | `resources/views/emails/auth/verify-email.blade.php` | Nội dung thư (mã in to, giãn chữ) |
| 10 | → chuyển hướng tới `/xac-thuc-email` | |

**Điểm cần nhớ:** CSDL **chỉ lưu băm** (`code_hash`), không lưu mã gốc.
Mã gốc chỉ tồn tại trong bộ nhớ đúng một lần, đủ để gửi đi.

## 1.3. Bước 2 — Nhập mã

| # | Tệp | Làm gì |
|---|---|---|
| 1 | `resources/views/auth/verify-email.blade.php` | Ô nhập 6 chữ số, `POST /xac-thuc-email` |
| 2 | `resources/css/components/auth.css` | Kiểu `.otp-input` (chữ to, giãn, phông đều nét) |
| 3 | `routes/web.php` | `verification.confirm`, chặn `throttle:10,1` |
| 4 | `app/Http/Controllers/Auth/EmailVerificationController.php` → `confirm()` | Kiểm `digits:6` |
| 5 | `app/Services/Auth/EmailVerifier.php` → `confirm()` | Kiểm hạn dùng → số lần sai → đối chiếu băm |
| 6 | `app/Models/User.php` → `markEmailAsVerified()` | Ghi `email_verified_at` |
| 7 | Xoá `email_verification_codes` | Mã đã dùng không dùng lại được |
| 8 | → `redirect()->intended()` | Về đúng trang khách đang muốn tới |

Gõ sai thì `EmailVerifier` ném `EmailVerificationException`
(`app/Services/Auth/EmailVerificationException.php`), controller bắt lại
và đưa vào `withErrors(['code' => ...])`.

## 1.4. Bước 3 — Gửi lại mã

```
resources/views/auth/verify-email.blade.php   (nút "Gửi lại mã")
   → routes/web.php  (verification.send, throttle:6,1)
   → EmailVerificationController@resend
   → EmailVerifier::send()      ← kiểm khoảng chờ 60 giây
   → EmailVerificationMail + view thư
```

Mã mới **ghi đè** mã cũ (`updateOrCreate` theo `user_id`), không thêm
dòng. Nút bị khoá và hiện "Gửi lại mã sau N giây" khi chưa hết chờ.

## 1.5. Đường thứ hai — bấm liên kết trong thư

Cách mặc định của Laravel, vẫn giữ nguyên:

```
Liên kết có chữ ký trong email
   → routes/web.php  (verification.verify, middleware 'signed')
   → EmailVerificationController@verifyLink
   → Illuminate\Foundation\Auth\EmailVerificationRequest::fulfill()
```

`EmailVerificationRequest` tự kiểm chữ ký, hạn của liên kết và kiểm rằng
`{id}` đúng là người đang đăng nhập.

## 1.6. Ai chặn khách chưa xác thực

```
Khách mở /tai-khoan
   → routes/web.php: ->middleware(['auth', 'verified'])
   → Illuminate\Auth\Middleware\EnsureEmailIsVerified
   → chuyển hướng tới route TÊN 'verification.notice'
   → EmailVerificationController@notice
```

Vì middleware tìm theo **tên route**, ba tên `verification.notice` /
`verification.verify` / `verification.send` **không được đổi**. Đường dẫn
thì đổi thoải mái (ở đây là tiếng Việt).

Các trang bị khoá, khai trong `routes/web.php`:

| Trang | Đường dẫn |
|---|---|
| Hồ sơ tài khoản | `/tai-khoan` |
| Sổ địa chỉ | `/dia-chi` |
| Lịch sử đơn | `/don-hang` |
| Yêu thích | `/yeu-thich` |
| Lịch chăm cây | `/lich-cham-cay` |
| Lưu voucher về ví | `POST /voucher/{coupon}/luu` |

**Giỏ hàng và thanh toán KHÔNG bị khoá** — cửa hàng cho khách vãng lai
đặt hàng, nên khoá giỏ với người đã đăng ký mà chưa xác thực là phạt
đúng nhóm khách thân thiết hơn.

## 1.7. Bốn lớp chặn

Tất cả nằm trong `app/Services/Auth/EmailVerifier.php`, trừ lớp 5:

| Lớp | Chặn cái gì | Con số |
|---|---|---|
| Hạn dùng | Email bị lộ về sau | 15 phút |
| Số lần gõ sai | Dò mã (bám **tài khoản**, đổi IP không thoát) | 5 lần |
| Khoảng chờ gửi lại | Dùng hệ thống làm máy gửi thư rác | 60 giây |
| Lưu băm | Ai đọc được CSDL cũng không xác thực hộ được | bcrypt |
| `throttle` theo IP | Một máy nện nghìn lần/phút | `routes/web.php` |

## 1.8. Cấu hình

`.env` (đã có, **không đưa lên Git**):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=<email chính chủ>
MAIL_PASSWORD=<mật khẩu ứng dụng 16 ký tự, viết liền>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="<email chính chủ>"
```

---

# 2. Giỏ hàng → đặt hàng

## 2.1. Toàn cảnh trong 6 dòng

```
Bấm "Thêm vào giỏ"       → CartService ghi vào bảng cart_items
Mở /gio-hang             → tick chọn món muốn mua
Bấm "Thanh toán"         → điền người nhận + giao hàng + mã giảm giá
Bấm "Xem lại đơn hàng"   → trang xác nhận
Bấm "Đặt hàng"           → OrderService ghi đơn, trừ kho, gửi thư
```

Có **hai nguồn hàng** cho một lần thanh toán:

- **Từ giỏ** — mặc định, chỉ tính những món đã tick
- **"Mua ngay"** — một món riêng, giữ trong session, **không đụng vào giỏ**

`app/Services/Checkout/CheckoutSource.php` là nơi quyết định lần này
dùng nguồn nào.

## 2.2. Thêm vào giỏ

| # | Tệp | Làm gì |
|---|---|---|
| 1 | `resources/views/shop/products/show.blade.php` | Nút "Thêm vào giỏ", `POST /gio-hang` |
| 2 | `routes/web.php` | → `CartController@store` |
| 3 | `app/Http/Controllers/Shop/CartController.php` → `store()` | Kiểm sản phẩm, số lượng, biến thể |
| 4 | `app/Services/Cart/CartService.php` → `add()` | Tìm/tạo giỏ, cộng dồn dòng trùng, kiểm tồn kho |
| 5 | `app/Models/Cart.php`, `app/Models/CartItem.php` | Ghi CSDL |
| 6 | → `back()` kèm lời nhắn | `resources/js/flash.js` tự ẩn sau vài giây |

> Trên trang chủ và trang danh sách, bước 6 **không tải lại trang** —
> `resources/js/add-to-cart.js` chặn lại và gọi bằng `fetch`. Xem
> [mục 3](#3-thêm-vào-giỏ-mà-không-tải-lại-trang).

Hết hàng thì `CartService` ném `CartException`
(`app/Services/Cart/CartException.php`), controller đổi thành lời nhắn
đỏ chứ không để trang lỗi.

**Giỏ của ai:** đăng nhập rồi thì `carts.user_id`; chưa đăng nhập thì
`carts.session_id`. Lúc đăng nhập / đăng ký, `mergeSessionCartInto()`
gộp giỏ vãng lai vào tài khoản.

## 2.3. Xem và sửa giỏ

| Thao tác | Đường dẫn | Tệp xử lý |
|---|---|---|
| Xem giỏ | `GET /gio-hang` | `CartController@index` → `resources/views/shop/cart/index.blade.php` |
| Đổi số lượng | `PATCH /gio-hang/{cartItem}` | `CartController@update` |
| Xoá dòng | `DELETE /gio-hang/{cartItem}` | `CartController@destroy` |
| Tick chọn món | `POST /gio-hang/chon` | `CartController@select` → `CartService::setSelection()` |

Từng dòng hàng vẽ bằng `resources/views/components/cart/line.blade.php`;
bảng tiền bên phải là `resources/views/components/cart/summary.blade.php`.
`resources/js/cart-select.js` chỉ lo phần tick chọn cho mượt — tắt
JavaScript thì vẫn bấm nút gửi biểu mẫu được.

**Mở trang giỏ = huỷ lượt "Mua ngay" đang dang dở.** Không làm vậy thì
trang giỏ tính một con số, trang thanh toán tính một con số khác.

## 2.4. Ai tính tiền

Ba lớp, mỗi lớp một việc, **không lớp nào tính lại việc của lớp kia**:

| Lớp | Trả lời câu hỏi |
|---|---|
| `app/Services/Pricing/PricingService.php` | Giá của **từng sản phẩm** sau khuyến mại |
| `app/Services/Coupon/CouponService.php` | Mã giảm giá trên **tổng tiền hàng** |
| `app/Services/Checkout/CheckoutBasket.php` | Cộng tất cả lại thành số cuối cùng |

Mọi phép tính tiền dùng `bcadd` / `bcsub` / `bcmul` / `bccomp`, **không
dùng số thực** — `0.1 + 0.2` trong máy tính không bằng `0.3`, và với tiền
thì sai một đồng cũng là sai.

Trình duyệt **chỉ gửi lên chuỗi mã giảm giá**. Số tiền giảm luôn do máy
chủ tính.

## 2.5. Thanh toán — bước 1: điền thông tin

| # | Tệp | Làm gì |
|---|---|---|
| 1 | `resources/views/shop/cart/index.blade.php` | Nút "Tiến hành thanh toán" |
| 2 | `routes/web.php` | `GET /thanh-toan` → `CheckoutController@details` |
| 3 | `app/Http/Controllers/Shop/CheckoutController.php` → `details()` | Lấy giỏ, sổ địa chỉ, ví voucher |
| 4 | `app/Services/Checkout/CheckoutSource.php` → `basket()` | Chọn nguồn (giỏ hay "mua ngay") |
| 5 | `app/Services/Checkout/CheckoutSource.php` → `autoApplyBestCoupon()` | Tự chọn mã lợi nhất **trong ví** |
| 6 | `app/Services/Coupon/BestCouponFinder.php` | So từng mã, lấy mã giảm nhiều nhất |
| 7 | `resources/views/shop/checkout/details.blade.php` | 3 khối đánh số + khối mã giảm giá |
| 8 | `resources/css/components/cart.css`, `components/voucher.css` | Kiểu hiển thị |

Gửi biểu mẫu:

```
POST /thanh-toan
   → app/Http/Requests/Shop/CheckoutDetailsRequest.php   (kiểm dữ liệu)
   → CheckoutController@storeDetails
   → lưu vào session 'checkout.form'
   → chuyển hướng /thanh-toan/xac-nhan
```

Ba nút mã giảm giá đều thuộc **cùng biểu mẫu** đó, chỉ đổi đích bằng
`formaction`:

| Nút | Đường dẫn | Hàm |
|---|---|---|
| Áp dụng | `POST /thanh-toan/ma-giam-gia` | `applyCoupon()` |
| Bỏ mã | `DELETE /thanh-toan/ma-giam-gia` | `removeCoupon()` |
| Chọn giúp tôi | `POST /thanh-toan/ma-giam-gia/tu-chon` | `autoCoupon()` |

Nhờ vậy bấm nút nào cũng **không mất thứ khách đang gõ dở**.

## 2.6. Thanh toán — bước 2: xác nhận và đặt hàng

| # | Tệp | Làm gì |
|---|---|---|
| 1 | `GET /thanh-toan/xac-nhan` → `CheckoutController@confirm` | Dựng lại giỏ, tính lại tiền |
| 2 | `resources/views/shop/checkout/confirm.blade.php` | Hiện đầy đủ để khách soát lại |
| 3 | `POST /thanh-toan/dat-hang` → `CheckoutController@place` | |
| 4 | `app/Services/Checkout/CheckoutGuard.php` | Khoá chống trùng — bấm hai lần không thành hai đơn |
| 5 | `app/Services/Order/OrderService.php` → `place()` | **Trong một transaction:** khoá dòng sản phẩm, kiểm tồn kho, trừ kho, ghi `orders` + `order_items` |
| 6 | `app/Services/Order/OrderNumberGenerator.php` | Sinh mã đơn dạng `FP-260826-XXXX` |
| 7 | `app/Services/Order/OrderRiskScorer.php` | Chấm điểm rủi ro để admin lọc |
| 8 | `app/Services/Coupon/CouponService.php` → `redeem()` | Ghi nhận lượt dùng mã — **sau** khi đơn tạo xong |
| 9 | `app/Services/Cart/CartService.php` → `clearSelected()` | Chỉ xoá món đã mua, **giữ lại** món để dành |
| 10 | `app/Services/Order/OrderMailer.php` + `app/Mail/OrderConfirmationMail.php` | Gửi thư xác nhận |
| 11 | → `/don-hang/{ma-don}` | Trang kết quả |

**Vì sao trừ kho nằm trong transaction:** hai người cùng mua món cuối
cùng thì phải có đúng một người mua được. Khoá dòng (`lockForUpdate`)
bắt người thứ hai đợi, và khi tới lượt thì kho đã là 0.

**Thứ tự cuối cùng có lý do:** ghi đơn xong mới ghi nhận lượt dùng mã —
thử mã rồi bỏ giỏ không được tính là đã dùng. Gửi thư đặt sau cùng và
lỗi gửi thư không được làm hỏng đơn đã tạo.

## 2.7. Sơ đồ rút gọn

```
[views/shop/products/show]
        │  POST /gio-hang
        ▼
[CartController@store] → [CartService::add] → cart_items
        │
        ▼  GET /gio-hang
[CartController@index] → [views/shop/cart/index] + [components/cart/line, summary]
        │  tick chọn: POST /gio-hang/chon → [CartService::setSelection]
        │
        ▼  GET /thanh-toan
[CheckoutController@details]
        ├─ [CheckoutSource::basket]        ← chọn nguồn hàng
        ├─ [CheckoutSource::autoApplyBestCoupon] → [BestCouponFinder]
        ├─ [PricingService] + [CouponService] → [CheckoutBasket]
        └─ [views/shop/checkout/details]
        │  POST /thanh-toan  → [CheckoutDetailsRequest] → session
        ▼  GET /thanh-toan/xac-nhan
[CheckoutController@confirm] → [views/shop/checkout/confirm]
        │  POST /thanh-toan/dat-hang
        ▼
[CheckoutController@place]
        ├─ [CheckoutGuard]        chống bấm hai lần
        ├─ [OrderService::place]  transaction: khoá kho → trừ kho → ghi đơn
        ├─ [CouponService::redeem]
        ├─ [CartService::clearSelected]
        └─ [OrderMailer] → [OrderConfirmationMail]
        │
        ▼
[views/shop/orders/show]
```

---

# 3. Thêm vào giỏ mà không tải lại trang

## 3.1. Vấn đề nó giải quyết

Khách lướt tới cuối trang chủ, thấy một chậu sen đá, bấm **Thêm vào giỏ**
— trang tải lại và ném họ về đầu trang. Muốn xem tiếp thì phải cuộn lại
từ đầu. Thêm ba món là ba lần cuộn lại.

## 3.2. Toàn cảnh trong 5 dòng

```
Bấm "Thêm vào giỏ"
   → JavaScript chặn việc gửi biểu mẫu
   → fetch() gọi CHÍNH route cũ, kèm header Accept: application/json
   → máy chủ trả JSON { ok, message, cartCount }
   → cập nhật huy hiệu giỏ + hiện thông báo nổi + nút nháy "Đã thêm"
```

**Trang không hề tải lại.** Vị trí cuộn giữ nguyên.

## 3.3. Đi qua những tệp nào

| # | Tệp | Làm gì |
|---|---|---|
| 1 | `resources/views/components/product/card.blade.php` | Thẻ sản phẩm trên trang chủ |
| 2 | `resources/views/components/product/actions.blade.php` | Biểu mẫu `class="product-buy"`, nút `data-add-to-cart` |
| 3 | `resources/js/app.js` | Gọi `initAddToCart()` |
| 4 | `resources/js/add-to-cart.js` | Chặn `submit`, gọi `fetch` |
| 5 | `routes/web.php` | `POST /gio-hang` → `CartController@store` (route CŨ, không thêm route mới) |
| 6 | `app/Http/Controllers/Shop/CartController.php` → `store()` | Nhận ra `expectsJson()` → trả JSON |
| 7 | `app/Services/Cart/CartService.php` → `add()` | Kiểm tra + ghi CSDL (y hệt đường cũ) |
| 8 | `resources/js/add-to-cart.js` → `updateBadge()` | Đổi số trên `[data-cart-badge]` ở thanh trên cùng |
| 9 | `resources/js/flash.js` → `showToast()` | Hiện thông báo nổi |
| 10 | `resources/css/components/toast.css` | Vị trí và kiểu của thông báo nổi |

## 3.4. Hai dạng trả lời, một luồng xử lý

`CartController@store` **không tách thành hai endpoint**. Mọi phép kiểm
tra (còn hàng, có bán trực tiếp không, số lượng hợp lệ) chỉ có MỘT bản:

```php
// Cùng một $this->cart->add(...) cho cả hai đường
return $request->expectsJson()
    ? response()->json(['ok' => true, 'message' => ..., 'cartCount' => ...])
    : back()->with('success', ...);
```

Thêm một `/api/gio-hang` riêng là mở đường cho hai bản luật, và bản ít
người dùng hơn sẽ là bản bị quên khi sửa.

## 3.5. Bốn điều phải đúng

### 1. Chỉ chặn nút "Thêm vào giỏ"

Nút **Mua ngay** nằm **chung một biểu mẫu** (đổi đích bằng `formaction`)
nhưng nó **cố ý** rời trang sang `/thanh-toan`. Chặn nó là làm hỏng đúng
thứ khách vừa yêu cầu.

```js
const button = event.submitter;          // nút NÀO vừa được bấm
if (!button || !button.hasAttribute('data-add-to-cart')) return;
```

### 2. Hỏng thì quay về cách cũ

Biểu mẫu vẫn là biểu mẫu thật. Mất mạng, máy chủ trả 500, trình duyệt
không có `fetch` → gửi biểu mẫu như thường. Khách vẫn thêm được hàng,
chỉ là trang có tải lại.

```js
submit(form, button).catch(() => form.submit());
```

`form.submit()` không bắn lại sự kiện `submit`, nên không có vòng lặp.

### 3. Số món trong giỏ lấy từ máy chủ

JSON trả kèm `cartCount`. Tự cộng thêm ở trình duyệt là sai ngay khi
khách mở hai tab, hoặc khi giỏ **gộp dòng trùng** thay vì thêm dòng mới.

### 4. Thông báo phải nằm trong tầm mắt

Thông báo của máy chủ nằm ở **đầu trang** (`layouts/app.blade.php`). Với
việc thêm-không-tải-lại thì khách đang ở giữa hoặc cuối trang — một dòng
chữ ở đầu trang là một dòng chữ họ không bao giờ thấy.

Vì thế có **thông báo nổi** neo theo khung nhìn:
`resources/css/components/toast.css`, góc dưới bên phải trên màn hình
rộng, kéo ngang hết bề rộng trên điện thoại.

## 3.6. Sơ đồ rút gọn

```
[components/product/card] → [components/product/actions]
        │  <form class="product-buy">
        │    ├─ nút [data-add-to-cart]  ← JS CHẶN
        │    └─ nút [formaction=/mua-ngay] ← JS BỎ QUA, rời trang
        ▼
[js/add-to-cart.js]  submit → preventDefault → fetch(POST /gio-hang)
        │                                   Accept: application/json
        ▼
[CartController@store] → [CartService::add] → cart_items
        │
        └─ JSON { ok, message, cartCount }
        │
        ▼
[js/add-to-cart.js]
        ├─ updateBadge()  → [data-cart-badge] ở site/header
        ├─ showToast()    → [js/flash.js] → [css/toast.css]
        └─ nút nháy "Đã thêm" 1,6 giây

   Lỗi bất kỳ ──► form.submit()  (đường cũ, có tải lại trang)
```

## 3.7. Đo được

| Phép thử | Kết quả |
|---|---|
| Cuộn tới cuối trang chủ rồi bấm | Vị trí cuộn **3609 → 3609**, không đổi |
| Huy hiệu giỏ | 0 (ẩn) → **1** (hiện) → 2 |
| `aria-label` của icon giỏ | "Giỏ hàng (trống)" → "Giỏ hàng (1 sản phẩm)" |
| Thông báo nổi | Nằm **trong khung nhìn**, xếp chồng khi thêm liên tiếp |
| Nút "Mua ngay" | Vẫn chuyển sang `/thanh-toan` |
| Sản phẩm không tồn tại | Thông báo **đỏ**, huy hiệu không đổi |
| Không có JavaScript | 200 → chuyển hướng về trang cũ + flash, huy hiệu vẫn tăng |
| Điện thoại 375px | Thông báo rộng 343px, không tràn ngang |

---

# 4. Dòng thời gian đơn hàng

## 4.1. Toàn cảnh trong 6 dòng

1. Khách đặt hàng → hệ thống ghi mốc đầu tiên **"Chờ xác nhận"**.
2. Nhân viên đổi trạng thái ở trang quản trị.
3. `OrderService::changeStatus()` khoá đơn, kiểm bước chuyển, ghi trạng
   thái mới **và ghi thêm một mốc** — tất cả trong cùng một transaction.
4. Khách mở trang đơn của mình → thấy đủ các bước đã đi qua.
5. Nhân viên mở trang đơn ở khu quản trị → thấy đúng các bước đó, **cộng
   thêm tên người đã thao tác**.
6. Cả hai trang dùng **chung một component**, khác nhau đúng một tham số.

## 4.2. Ghi mốc đầu tiên (lúc đặt hàng)

```
POST /thanh-toan/dat-hang
  └─ Shop/CheckoutController::place()
       └─ Services/Order/OrderService::place()
            └─ DB::transaction
                 └─ createOrder()
                      ├─ Order::create(...)
                      ├─ $order->items()->createMany(...)
                      ├─ OrderRiskScorer::apply()
                      ├─ CouponService::redeem()          ← nếu có mã
                      └─ OrderStatusEvent::create(...)    ← MỐC ĐẦU TIÊN
```

**Vì sao nằm trong transaction:** một đơn tồn tại mà lịch sử trống rỗng là
một đơn mà trang tra cứu hiện ra trắng trơn — khách hiểu là cửa hàng chưa
nhận được gì.

Mốc này **không ghi người thực hiện**: đơn do chính khách tạo, và trên dòng
thời gian thì "bạn đã đặt đơn" không cần ai đứng tên.

## 4.3. Ghi mốc khi đổi trạng thái

```
PATCH /admin/orders/{order}/status
  └─ Admin/OrderController::updateStatus()
       └─ Services/Order/OrderService::changeStatus()
            ├─ $truocDo = $order->status          ← nhớ để nhật ký còn so được
            └─ DB::transaction
                 ├─ Order::lockForUpdate()        ← khoá rồi ĐỌC LẠI
                 ├─ canTransitionTo()             ← kiểm trên bản vừa khoá
                 ├─ $order->save()
                 ├─ OrderStatusEvent::create(...) ← MỐC MỚI, cùng transaction
                 ├─ restoreStock()                ← nếu huỷ
                 └─ CouponService::release()      ← nếu huỷ
            ├─ ActivityLogger::logChange()        ← nhật ký NỘI BỘ, ngoài transaction
            ├─ CareScheduler::scheduleForOrder()  ← nếu đã giao
            └─ OrderMailer::sendStatusUpdate()
```

**Vì sao mốc nằm trong transaction còn nhật ký thì không:** mốc là *một
phần của đơn* — thiếu nó thì dòng thời gian nhảy cóc và nói sai. Nhật ký
nội bộ là *thông tin thêm* — hỏng thì không được kéo theo cả thao tác.

## 4.4. Hiển thị

| Nơi | File | Có tên người thao tác? |
|---|---|---|
| Trang đơn của khách | `resources/views/shop/orders/show.blade.php` | **Không** |
| Trang đơn ở quản trị | `resources/views/admin/orders/show.blade.php` | **Có** |
| Component dùng chung | `resources/views/components/order/timeline.blade.php` | tham số `showActor` |
| CSS | `resources/css/components/order-timeline.css` | |

Với khách, người bấm nút là "cửa hàng", không phải một cái tên: hiện tên
nhân viên trên trang công khai là lộ thông tin nội bộ mà không đem lại gì
cho người đọc.

`Admin/OrderController::show()` nạp sẵn `statusEvents.changedBy` — không
nạp thì mỗi mốc sinh một truy vấn.

---

# 5. Nhật ký thao tác quản trị

## 5.1. Toàn cảnh trong 5 dòng

1. Người quản trị làm một việc **ghi dữ liệu** (sửa giá, huỷ đơn, ẩn đánh
   giá, khoá tài khoản…).
2. Controller gọi `ActivityLogger` **ngay tại chỗ**, kèm câu mô tả viết sẵn.
3. `ActivityLogger` chụp lại: ai làm, tên họ lúc đó, việc gì, bản ghi nào,
   giá trị trước/sau, địa chỉ IP.
4. Hỏng thì **nuốt lỗi** và ghi ra log hệ thống — không được làm hỏng thao
   tác chính.
5. Người quản trị mở `/admin/nhat-ky` để tra, lọc theo nhóm việc / người /
   khoảng ngày / từ khoá.

## 5.2. Đường đi

```
Controller quản trị  (Product, Category, Coupon, Promotion, Review, User, Order)
  └─ trait Http/Controllers/Admin/Concerns/LogsAdminActivity
       ├─ audit()      → Services/Audit/ActivityLogger
       └─ logCrud()    → câu mô tả dựng sẵn cho thêm / sửa / xoá
            └─ Services/Audit/ActivityLogger::log()
                 └─ Models/ActivityLog::create()

GET /admin/nhat-ky
  └─ Admin/ActivityLogController::index()
       └─ resources/views/admin/activity-logs/index.blade.php
```

## 5.3. Những chỗ đang được ghi

| Mã việc | Khi nào |
|---|---|
| `order.status_changed` | đổi trạng thái đơn (kể cả khách tự huỷ) |
| `order.payment_changed` | đánh dấu đã trả / hoàn tiền / gỡ đánh dấu |
| `product.created` / `.updated` / `.deleted` | thêm, sửa, xoá sản phẩm |
| `product.bulk_updated` / `.bulk_deleted` | thao tác hàng loạt |
| `category.*`, `coupon.*`, `promotion.*` | thêm, sửa, xoá |
| `review.replied` / `.reply_removed` | phản hồi công khai của cửa hàng |
| `review.hidden` / `.shown` (+ `bulk_`) | ẩn / hiện đánh giá |
| `user.role_changed` | đổi vai trò |
| `user.locked` / `.unlocked` | khoá / mở khoá tài khoản |

**`product.updated` ghi cả giá cũ:** bản ghi sản phẩm chỉ giữ giá hiện tại,
không chụp lại thì con số hôm qua mất vĩnh viễn.

**Ghi TRƯỚC khi xoá:** sau khi xoá thì khoá chính không còn trỏ tới đâu.

## 5.4. Không có đường xoá

`routes/web.php` chỉ khai đúng một route `GET admin/nhat-ky`. Model
`ActivityLog` đặt `UPDATED_AT = null` — bảng không có cột đó, nên không có
chỗ để sửa. Một bài kiểm tra canh riêng việc này
(`ActivityLogTest::khong_co_duong_nao_xoa_duoc_nhat_ky`).

## 5.5. Nhật ký thấy gì và KHÔNG thấy gì

Nhật ký chỉ ghi những thay đổi **đi qua ứng dụng** — tức là qua màn hình
quản trị. Nó **không thấy**:

- sửa thẳng bằng phpMyAdmin hoặc câu lệnh SQL;
- `php artisan tinker`;
- các lệnh `db:seed`, `migrate:fresh`.

Đây không phải thiếu sót sửa được ở tầng ứng dụng. Kể cả model observer
cũng không cứu: `User::whereKey($id)->update([...])` là câu lệnh của query
builder, **không sinh sự kiện model nào**. Muốn bắt được cả những đường đó
thì phải dùng công cụ ở tầng cơ sở dữ liệu (binlog, trigger) — ngoài phạm
vi đồ án này.

**Vì sao phải nói ra:** người đọc mặc định hiểu nhật ký là bản ghi **đầy
đủ**, rồi kết luận rằng thứ không có ở đây thì đã không xảy ra. Một nhật ký
nói thiếu mà người đọc tưởng là đủ còn dẫn sai hơn không có nhật ký.

Đã gặp đúng cảnh đó ngay trong lúc dựng tính năng: nhật ký ghi một tài
khoản bị hạ quyền và bị khoá, nhưng việc trả lại nguyên trạng làm bằng lệnh
ghi thẳng nên không để lại dòng nào — đọc nhật ký thì tưởng tài khoản vẫn
đang bị khoá, trong khi thực tế nó vẫn dùng bình thường. Câu lưu ý trên
trang `/admin/nhat-ky` sinh ra từ chuyện này.

---

# 6. Khoá tài khoản

## 6.1. Toàn cảnh trong 5 dòng

1. Quản trị bấm **Khoá** ở `/admin/users`, nhập lý do.
2. `UserController::updateLock()` kiểm ba chốt, rồi ghi `locked_at` + `lock_reason`.
3. Người bị khoá **đang mở phiên** → bị đá ra ở request kế tiếp.
4. Họ thử **đăng nhập lại** → bị chặn, kèm đúng lý do đã nhập.
5. Mở khoá là gỡ `locked_at` và **xoá luôn lý do cũ**.

## 6.2. Hai chỗ chặn

```
(a) Lúc đăng nhập
POST /login
  └─ Http/Requests/Auth/LoginRequest::authenticate()
       ├─ Auth::attempt()            ← kiểm mật khẩu TRƯỚC
       └─ nếu user->isLocked():
            ├─ Auth::logout()        ← attempt() đã đăng nhập họ rồi
            └─ ValidationException kèm lock_reason

(b) Với phiên đang mở  ← CHỖ QUAN TRỌNG HƠN
mọi request thuộc nhóm 'web'
  └─ Http/Middleware/EnsureUserIsNotLocked
       ├─ Auth::logout()
       ├─ session()->invalidate()    ← huỷ CẢ phiên, không chỉ đăng xuất
       └─ redirect /login + thông báo
```

Chỉ có (a) thì khoá **không có tác dụng với đúng người đang cần chặn**: họ
đã đăng nhập rồi, phiên sống nhiều ngày, và cứ thế gửi tiếp đánh giá rác.

Kiểm ở (a) đặt **sau** `Auth::attempt()`: kiểm trước thì thông báo "tài
khoản đã bị khoá" trở thành cách để người lạ dò xem email nào tồn tại.

## 6.3. Ba chốt chặn

| Chốt | Chặn gì |
|---|---|
| Tự đổi vai trò / tự khoá mình | Admin tự khoá mình ra ngoài, không ai mở lại được |
| Còn ít nhất một admin dùng được | Hạ/khoá nốt admin cuối cùng — đếm **bỏ qua** admin đang bị khoá |
| Ghi nhật ký | "Ai phong admin cho tài khoản kia" phải trả lời được sau nhiều tháng |

## 6.4. File liên quan

| Việc | File |
|---|---|
| Cột `locked_at`, `lock_reason` | `database/migrations/2026_09_15_030000_add_lock_to_users_table.php` |
| `isLocked()` | `app/Models/User.php` |
| Chặn lúc đăng nhập | `app/Http/Requests/Auth/LoginRequest.php` |
| Chặn phiên đang mở | `app/Http/Middleware/EnsureUserIsNotLocked.php` |
| Đăng ký middleware | `bootstrap/app.php` |
| Đổi vai trò / khoá | `app/Http/Controllers/Admin/UserController.php` |
| Giao diện | `resources/views/admin/users/index.blade.php` |

---

# 7. Sắp xếp và thao tác hàng loạt ở khu quản trị

## 7.1. Sắp xếp theo cột

```
Người dùng bấm đầu cột
  └─ components/admin/sort-header.blade.php
       └─ sinh URL ?sap=<khoa>&huong=tang|giam   ← GIỮ NGUYÊN mọi bộ lọc đang có
            └─ Controller::index()
                 └─ trait Admin/Concerns/SortsAdminList::applySort()
                      ├─ khoá KHÔNG có trong danh sách trắng → thứ tự mặc định
                      ├─ hướng khác 'giam' → 'asc'
                      └─ orderBy(cột thật) + orderBy(khoá chính)
```

Danh sách trắng khai ngay trong `index()` của từng controller. **Tên cột
không bao giờ đến từ URL.**

| Trang | Sắp được theo |
|---|---|
| Sản phẩm | tên, giá, tồn kho, trạng thái |
| Đơn hàng | mã đơn, tổng tiền, thanh toán, trạng thái, ngày đặt |
| Mã giảm giá | mã, lượt dùng, hiệu lực, trạng thái |
| Tài khoản | tên, email, số đơn, đã chi, ngày tham gia |

## 7.2. Thao tác hàng loạt

```
components/admin/bulk-bar.blade.php     ← <form id="bulk-form">, KHÔNG bọc bảng
   ↑ ô tích trong bảng trỏ về bằng thuộc tính form="bulk-form"
   
POST /admin/products/hang-loat
  └─ Admin/ProductController::bulk()
       └─ trait Admin/Concerns/HandlesBulkAction::validateBulk()
            ├─ viec  ∈ danh sách trắng
            ├─ ids   là mảng, mỗi phần tử phải exists
            └─ array_unique
       ├─ Product::whereKey($ids)->update(...)   ← MỘT câu lệnh cho cả nhóm
       └─ ActivityLogger::log('product.bulk_updated', ...)

resources/js/admin/bulk-actions.js   ← chỉ TĂNG CƯỜNG: chọn tất cả, bộ đếm, hỏi lại
```

Không có JavaScript vẫn dùng được: tích tay từng ô rồi bấm Thực hiện.

| Trang | Việc làm được |
|---|---|
| Sản phẩm | Đang bán / Tạm ẩn / Bản nháp / Xoá (xoá **mềm**) |
| Đánh giá | Ẩn / Hiện lại — **không có xoá**, nội dung là của khách |

---

## Xem thêm

- `docs/DOMAIN-DECISIONS.md` — vì sao mỗi chỗ làm như vậy
- `docs/KIEM-THU.md` — bộ kiểm thử tự động cho các luồng này
