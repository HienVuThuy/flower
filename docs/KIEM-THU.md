# Kiểm thử tự động

## Chạy

```bash
composer test
```

hoặc chạy thẳng:

```bash
php artisan test
```

Chạy một nhóm:

```bash
php artisan test --filter=CouponRemovalTest
```

**Không cần bật MySQL.** Bài kiểm tra chạy trên SQLite trong bộ nhớ
(`phpunit.xml` đặt `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), dựng
lại toàn bộ lược đồ từ migration mỗi lần chạy. Cơ sở dữ liệu thật
(`btlar`) **không bị đụng tới** — chạy kiểm thử không làm mất dữ liệu mẫu
đang có.

Cả bộ mất khoảng **17 giây** (213 bài, 648 phép khẳng định).

---

## Có gì trong này

| Tệp | Số bài | Canh chừng điều gì |
|---|---|---|
| `Checkout/CouponRemovalTest.php` | 8 | Nút "Bỏ mã" và việc tự chọn mã |
| `Checkout/CouponChoiceTest.php` | 13 | Ví voucher và danh sách chọn mã |
| `Checkout/PlaceOrderTest.php` | 10 | Đi hết luồng đặt hàng, và con số tiền |
| `Checkout/CartSelectionTest.php` | 5 | Mua riêng từng món trong giỏ |
| `Checkout/BuyNowTest.php` | 2 | "Mua ngay" không lẫn với giỏ hàng |
| `Coupon/CouponServiceTest.php` | 12 | Luật của mã giảm giá |
| `Auth/EmailVerificationTest.php` | 23 | Xác thực email bằng mã OTP |
| `Cart/AddToCartTest.php` | 10 | Thêm vào giỏ — cả đường có và không có JavaScript |
| `Security/OwnershipTest.php` | 11 | Tài khoản này không chạm được dữ liệu tài khoản kia |
| `Checkout/CouponDroppedNoticeTest.php` | 5 | Mã tự rụng thì phải nói cho khách biết, và nói **đúng một lần** |
| `Payment/VietQrTest.php` | — | Mã QR chuyển khoản |
| `Order/TransactionSafetyTest.php` | 7 | Đua giữa hai request: huỷ hai lần, mã hết lượt giữa chừng, hàng vừa ngừng bán |
| `Order/OrderTimelineTest.php` | 9 | Dòng thời gian đơn hàng — đủ mốc, đúng người, không sót |
| `Catalog/PriceSortTest.php` | 6 | Sắp theo giá **khách thấy**, và khớp với `PricingService` |
| `Admin/ListFilterTest.php` | — | Tìm kiếm và bộ lọc ở các trang danh sách |
| `Admin/OrderPaymentTest.php` | — | Luật chuyển trạng thái thanh toán |
| `Admin/ReviewReplyTest.php` | — | Phản hồi công khai của cửa hàng |
| `Admin/DashboardTodoTest.php` | 6 | Khối "việc cần làm" phải nói đúng sự thật |
| `Admin/ProductDeleteTest.php` | 3 | Xoá mềm **không** được xoá file ảnh |
| `Admin/ProductClassificationTest.php` | 7 | Ba trường phân loại phải khớp nhau |
| `Admin/ActivityLogTest.php` | 8 | Nhật ký ghi đủ, ghi đúng người, và **không xoá được** |
| `Admin/SortAndBulkTest.php` | 13 | Sắp xếp và thao tác hàng loạt — phần lớn canh giá trị **bịa đặt** |
| `Admin/UserManagementTest.php` | 12 | Đổi vai trò và khoá tài khoản — phần lớn canh đường **phải bị chặn** |

`Checkout/CheckoutTestCase.php` là nền chung, không chứa bài kiểm tra.

### Ba nhóm bài đáng chú ý

**Đua giữa hai request** (`Order/TransactionSafetyTest.php`). PHPUnit chạy
một luồng nên không dựng được hai request đồng thời. Thay vào đó, mỗi bài
**dựng sẵn đúng cái trạng thái mà request thứ hai sẽ nhìn thấy**, rồi hỏi:
đoạn mã có kiểm lại không, hay vẫn tin vào thứ nó đọc từ trước?

**Đối chiếu hai bản chép của một luật** (`Catalog/PriceSortTest.php`). Biểu
thức SQL sắp theo giá là bản chép lại luật của `PricingService`. Hai bản
chép thì sớm muộn cũng trôi khỏi nhau. Một bài so trực tiếp thứ tự do cơ sở
dữ liệu trả về với thứ tự tính bằng chính `PricingService` — và **chính bài
đó đã bắt được việc thiếu hai chốt chặn ngay khi viết**.

**Canh đường phải bị chặn** (`Admin/UserManagementTest.php`,
`Admin/SortAndBulkTest.php`). Ở những màn hình mà một cú bấm sai không chữa
được — khoá chính mình ra ngoài, ghi dữ liệu cho ba mươi bản ghi — phần lớn
các bài kiểm tra **giá trị bịa đặt phải bị từ chối**, chứ không kiểm đường
đi thành công. Mỗi nhóm vẫn có một bài chạy đường đúng, đặt **trước** các
bài chặn: không có nó thì "chặn được mọi thứ" cũng làm cả nhóm xanh.

---

## Vì sao bộ này bắt đầu từ luồng thanh toán

Không phải vì nó dễ nhất, mà vì **ba lỗi liên tiếp đều rơi vào đúng đây**,
và đây là nơi tiền đi qua. Thứ tự viết kiểm thử nên đi theo thiệt hại khi
sai, không theo thứ tự tính năng được làm.

Mỗi bài trong `CouponRemovalTest` và `CouponChoiceTest` là **một lỗi đã xảy
ra thật**, không phải tình huống tưởng tượng:

| Bài | Lỗi gốc | Quyết định |
|---|---|---|
| `bo_ma_roi_tai_lai_trang_thi_ma_khong_quay_lai` | Bấm "Bỏ mã" xong mã tự áp lại — nút không bao giờ hoạt động | QĐ-44 |
| `bo_ma_khong_lam_mat_thong_tin_dang_nhap_do` | Bấm "Bỏ mã" giữa chừng là mất sạch tên, địa chỉ vừa gõ | QĐ-45 |
| `vi_trong_thi_khong_co_ma_nao_duoc_tu_ap` | Trang ghi "Ví voucher đang trống" mà vẫn có mã được áp | QĐ-47, QĐ-51 |
| `khong_tu_ap_ma_cua_su_kien_khi_khach_chua_luu_ve_vi` | Mã riêng của sự kiện bị phát cho mọi khách | QĐ-48 |
| `mo_lai_trang_gio_hang_thi_phien_mua_ngay_bi_go` | Giỏ hiện 105.000₫, thanh toán hiện 520.000₫ | QĐ trước |
| `go_sai_ma_KHONG_duoc_dat_lai_dong_ho_cho_gui_lai` | Gõ sai mã OTP là bị chặn xin mã mới thêm 60 giây | QĐ-56 |
| `dang_nhap_xoa_ve_xem_don_cua_khach_vang_lai` | Máy dùng chung: người sau đọc được đơn của người trước | QĐ-57 |

---

## Ba nguyên tắc đã theo khi viết

### 1. Đi qua HTTP, không gọi thẳng service

Cả năm lỗi trên đều nằm ở chỗ **ghép nối** — biểu mẫu gửi đi đâu, session
còn gì sau khi chuyển hướng, trang hiện ra cái gì — chứ không nằm trong
phép tính. Gọi thẳng service thì cả năm vẫn xanh.

### 2. Đọc session, không dò chuỗi trong HTML

`appliedCoupon()` đọc `session('checkout.coupon')`. Dò lớp CSS thì bài
kiểm tra hỏng mỗi lần đổi giao diện, và người ta sẽ học cách bỏ qua nó.

Ngoại lệ: những bài **đang kiểm chính câu chữ khách nhìn thấy**
(`assertSee('Chưa có mã nào dùng được cho đơn này.')`) thì phải dò chuỗi —
vì đó mới đúng là thứ đang được canh.

### 3. Bài kiểm tra phải tự chứng minh mình có tác dụng

Sau khi viết `CouponRemovalTest`, tôi **cố ý làm lại lỗi cũ** (đổi
`declineAutoCoupon()` về `clearCoupon()`) và chạy lại: 2 bài đỏ đúng chỗ.
Rồi mới khôi phục.

Một bài kiểm tra chưa từng đỏ là một bài chưa biết mình canh cái gì.

**Lần thứ hai, cách này bắt được một bài kiểm tra dối.** Bài
`trang_chu_co_du_thuoc_tinh_cho_javascript_bam_vao` ban đầu viết bằng
`assertSee('data-add-to-cart')`. Đổi thuộc tính thành
`data-add-to-cart-cu` thì bài vẫn **XANH** — vì `assertSee` khớp chuỗi
con. Đã đổi sang `assertMatchesRegularExpression` với dạng đầy đủ
`data-add-to-cart="{id}"`; thử lại thì đỏ đúng lúc phải đỏ.

---

## Một chỗ không kiểm được bằng bộ này

**Luồng nhiều bước của khách vãng lai.** Giỏ hàng của khách chưa đăng
nhập được nhận diện bằng `session()->getId()`, mà `TestCase` của Laravel
**không mang cookie phản hồi sang request sau** — nên mỗi request trong
bài kiểm tra sinh ra một phiên mới, và một phiên mới nghĩa là một giỏ
mới.

Đã thử pin cookie phiên (`withUnencryptedCookie`) và đổi `SESSION_DRIVER`
sang `database`: **không cái nào giải quyết được**, vì gốc rễ nằm ở việc
cookie không được mang đi.

Nên phần luật dành cho khách vãng lai được kiểm ở mức dịch vụ
(`BestCouponFinder::find(null, ...)` phải trả về `null`), còn luồng nhiều
bước thì **phải thử tay**. Ghi ra đây thay vì để nó thành một khoảng
trống không ai biết.

---

## Chỗ thứ hai không kiểm được: GÕ SAI TÊN CỘT

Bộ kiểm thử chạy trên **SQLite**, còn ứng dụng chạy trên **MySQL**. Có
một khác biệt giữa hai cái làm cả 905 bài mù trước một loại lỗi:

> SQLite coi định danh trong nháy kép mà **không khớp cột nào** là một
> **chuỗi ký tự**, và không báo lỗi.

Chứng minh được trong bốn dòng:

```php
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE t (id INTEGER, name TEXT)');
$db->query('select "id", "name", "sku" from t')->fetch();
// => ['id' => 1, 'name' => 'abc', '"sku"' => 'sku']   — KHÔNG lỗi
```

Laravel bọc mọi định danh trong nháy kép khi sinh SQL cho SQLite. Nên:

```php
Product::get(['id', 'name', 'sku', 'stock_quantity']);
```

**đi lọt qua toàn bộ bộ kiểm thử** dù bảng `products` không hề có cột
`sku` (cột thật tên `product_code`), rồi nổ `SQLSTATE[42S22]` trên MySQL
lúc người dùng mở trang. Đã xảy ra thật ở trang Tồn đầu kỳ.

**Không tắt được bằng cấu hình.** Cơ chế đó gỡ được bằng cờ biên dịch
`SQLITE_DQS=0` của SQLite, không có PRAGMA nào bật/tắt lúc chạy, và bản
SQLite đi kèm PHP thì bật sẵn.

**Cách đã dùng để bù:** một kịch bản soát toàn bộ `app/`, rút mọi tên cột
viết tay trong `get([...])`, `select([...])`, `pluck()`, `sum()`,
`orderBy()`… rồi đối chiếu với lược đồ **thật của MySQL**. Chạy một lần
tìm ra đúng một lỗi (`sku`) trên 309 tệp; 20 kết quả còn lại đều là bí
danh từ `selectRaw(... as total)`, đã kiểm tay từng cái.

Nên nhớ khi thêm cột hay đổi tên cột: **bài xanh không có nghĩa là tên
cột đúng.** Mở thật một lần trên MySQL, hoặc chạy lại kịch bản soát.

---

## Còn thiếu

Cố ý ghi ra thay vì lờ đi:

- **Chức năng quản trị** chưa có bài nào cho phần NGHIỆP VỤ (tạo sản
  phẩm, đổi trạng thái đơn, sửa khuyến mại). Phần PHÂN QUYỀN thì đã có
  trong `Security/OwnershipTest`.
- **Tìm kiếm mờ** (`fuzzy search`) chưa có bài nào.
- **Thư xác nhận ĐƠN HÀNG** chưa có bài nào. (Thư xác thực email thì đã
  có: `Auth/EmailVerificationTest` dùng `Mail::fake()` và kiểm cả nội
  dung mã lẫn việc CSDL chỉ lưu băm.)
- **Giao diện** không có bài nào và sẽ không có: bố cục phải nhìn bằng
  mắt, viết kiểm thử cho nó chỉ tạo cảm giác an toàn giả.
