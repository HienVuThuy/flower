# Quyết định nghiệp vụ

Ghi lại những quyết định đã chốt để lần sau không ai sửa ngược lại vì
đọc Guide một cách máy móc. Mỗi mục nêu rõ: quyết định là gì, vì sao,
và điều gì KHÔNG được làm.

---

## QĐ-01 — "Cây để bàn" và "Bonsai" là Category, không phải hình thức bán

**Chốt ngày:** 23/08/2026 · **Người quyết:** chủ dự án

### Bối cảnh

Guide.docx **tự mâu thuẫn** ở hai chỗ:

- **§4.3** liệt kê "Cây để bàn", "Cây bonsai" trong danh sách *hình thức bán*.
- **§24** lại lấy chính "Cây để bàn", "Cây bonsai" làm ví dụ *Category*.

### Quyết định

Theo **§24**: chúng là **Category**. Giữ nguyên cách triển khai hiện tại.
Không chuyển thành `selling_form`.

### Lý do

Ba trục khái niệm khác nhau, không được trộn:

| Trục | Trả lời câu hỏi | Ví dụ |
|---|---|---|
| `category` | Khách đang tìm **nhóm sản phẩm** nào? | Hoa hồng, Hoa cưới, Cây để bàn, Bonsai, Sen đá |
| `product_type` | **Bản chất** sản phẩm là gì? | `flower`, `plant` |
| `selling_form` | Bán dưới **quy cách** nào? | `bouquet`, `pot`, `basket`, `box` |

Hai ví dụ đầy đủ:

```
Category: Cây để bàn   | product_type: plant  | selling_form: pot
Category: Hoa hồng     | product_type: flower | selling_form: bouquet
```

"Cây để bàn" và "Bonsai" là thứ khách **duyệt và tìm kiếm**, nên thuộc
Category là tự nhiên. Ép chúng thành hình thức bán sẽ khiến một sản phẩm
vừa là "cây để bàn" vừa là "cây chậu" — hai giá trị cho một trường.

### KHÔNG được làm

- Không chuyển "Cây để bàn"/"Bonsai" sang `selling_form` chỉ vì §4.3 liệt kê chúng ở đó.
- Không dùng một trường kiêm nhiều ý nghĩa.
- Không bỏ `product_type` để nhét vào `category`.

---

## QĐ-02 — Thông tin chăm sóc phụ thuộc hình thức bán

**Chốt ngày:** 23/08/2026 · **Căn cứ:** Guide §4.4

Cây chậu và bó hoa **không dùng chung** bộ thuộc tính chăm sóc.
`App\Enums\SellingForm::careProfile()` ánh xạ hình thức sang một trong ba
hồ sơ ở `App\Enums\CareProfile`:

| Hồ sơ | Áp dụng cho | Trường |
|---|---|---|
| `living_plant` | `pot`, `original` | ánh sáng, nước, đất, phân bón, nhiệt độ, vị trí, tần suất, độ khó, lưu ý |
| `cut_flower` | `bouquet`, `branch`, `basket`, `box`, `arrangement` | thay nước, cắt gốc, nơi đặt, độ bền, lưu ý |
| `minimal` | `set`, `gift`, `other` | lưu ý |

Lọc thực hiện ở **server** (`prepareForValidation`), JavaScript chỉ ẩn/hiện
cho tiện — không phải hàng rào.

### KHÔNG được làm

- Không dựng hệ thống thuộc tính động (EAV). Guide §27 chống over-engineering;
  ba hồ sơ cố định là đủ.
- Không hiện đủ 9 ô của cây chậu cho bó hoa.

---

## QĐ-03 — Loại khuyến mại chưa hỗ trợ không được xuất hiện trong giao diện

`combo` và `buy_x_get_y` đã khai trong `PromotionType` nhưng
`PricingService` **chưa tính được**. Vì vậy `PromotionType::selectable()`
loại chúng khỏi cả `<select>` lẫn `Rule::in`.

Khi nào implement xong phép tính thì mới cho `isImplemented()` trả về true.
Không được để admin chọn một kiểu mà backend xử lý như kiểu khác.

---

## QĐ-04 — Email xác nhận đơn: gửi thẳng, và không nói dối khách

**Bối cảnh.** Guide §X yêu cầu gửi email xác nhận sau khi đặt hàng. Môi trường
hiện tại: `MAIL_MAILER=log` và `QUEUE_CONNECTION=database` nhưng **không có tiến
trình `queue:work` nào chạy**.

**Quyết định 1 — Mailable KHÔNG dùng `ShouldQueue`.**
Nếu đưa vào hàng đợi, job sẽ nằm im trong bảng `jobs` và email không bao giờ
được xử lý. Khi nào cửa hàng chạy worker thật thì thêm `implements ShouldQueue`
vào `OrderConfirmationMail` là đủ — không phải sửa chỗ nào khác.

**Quyết định 2 — Lỗi gửi mail KHÔNG được ném ra ngoài.**
`OrderMailer::sendConfirmation()` bắt mọi `Throwable` và ghi log. Lúc gọi tới đó,
đơn đã ghi vào CSDL và kho đã trừ. Ném lỗi ra sẽ cho khách xem trang lỗi trong
khi đơn thật sự đã tạo — khách đặt lại, cửa hàng có hai đơn trùng.

**Quyết định 3 — Chỉ báo "đã gửi email" khi transport là thật.**
`OrderMailer::deliversForReal()` trả về `false` với các mailer `log`, `array`,
`null`. Trang đơn hàng chỉ hiện câu "Xác nhận đơn đã được gửi tới …" khi hàm này
đúng. Kiến trúc Mailable dựng đầy đủ, nhưng giao diện không giả vờ rằng thư đã
tới hộp thư khách khi nó chỉ nằm trong `storage/logs`.

**Cấu hình.** Bật gửi thật bằng cách sửa `MAIL_MAILER`/`MAIL_HOST`/… trong
`.env` — không có khoá nào viết cứng trong mã nguồn.

---

## QĐ-05 — Khách được tự huỷ đơn tới đâu

**Bối cảnh.** Máy trạng thái (`OrderStatus::nextStates()`) cho phép chuyển sang
`Cancelled` từ mọi trạng thái chưa kết thúc, kể cả `Đang giao`. `Order::isCancellable()`
phản ánh đúng điều đó và **dành cho admin** — cửa hàng cần quyền huỷ rộng khi khách
gọi điện.

**Quyết định.** Khách tự bấm nút thì hẹp hơn: chỉ `Chờ xác nhận` và `Đã xác nhận`
(`Order::isCancellableByCustomer()`).

Lý do là đặc thù hàng hoa tươi:

- `Đang chuẩn bị` — bó hoa đang được cắt và gói. **Hoa đã cắt không ghép lại được**;
  huỷ lúc này là cửa hàng mất trắng nguyên liệu.
- `Đang giao` — shipper đã xuất phát, chi phí giao đã phát sinh.

Từ hai trạng thái đó trở đi khách phải gọi cửa hàng. Đó là chuyện thương lượng giữa
người với người, không phải một nút bấm.

**Hệ quả về mã giảm giá.** Từ khi khách tự huỷ được, có một đường mới để đốt lượt
dùng của mã: đặt rồi huỷ, lặp lại. Vì vậy huỷ đơn nay **trả lại** `used_count`
(`CouponService::release()`), gọi từ `OrderService::changeStatus()` để admin huỷ và
khách huỷ đi qua đúng một đoạn mã.

**Rủi ro đã biết.** Ai có mã đơn + số điện thoại đều huỷ được, và `Cancelled` là
trạng thái kết thúc — không hoàn tác. Đây là hệ quả của việc cho đặt hàng không cần
tài khoản. Nếu cửa hàng thấy rủi ro, cách xử lý đúng là gửi OTP về số điện thoại,
không phải siết trạng thái cho phép huỷ.

---

## QĐ-06 — Ai được đánh giá sản phẩm

**Quyết định.** Ba điều kiện, thiếu một là không được (`Review::eligibleOrders()`):

1. Đơn thuộc về tài khoản đang đăng nhập.
2. Đơn ở trạng thái **`Đã giao`**.
3. Đơn có chứa sản phẩm đó.

Bỏ điều kiện 2 thì mục đánh giá thành nơi ai đặt hàng cũng viết được, kể cả
người vừa bấm đặt xong đã vào chấm một sao. Vì cột `order_id` là bắt buộc mới
gắn được nhãn "Đã mua hàng", nhãn đó **không thể** là lời quảng cáo suông.

**Một đánh giá cho mỗi (người, sản phẩm, đơn)** — ràng buộc UNIQUE ở cơ sở dữ
liệu, không chỉ bằng câu lệnh `if`. Mua lại ở đơn khác thì viết được bài mới:
lần mua sau có thể là trải nghiệm khác hẳn.

**Khách vãng lai không đánh giá được** dù đặt hàng được. Không có tài khoản thì
không có cách nào để họ sửa hay gỡ bài của chính mình, và ai biết mã đơn cũng
viết được bài đứng tên người mua.

**Hiện ngay, không duyệt trước.** Bắt duyệt từng bài sẽ khiến mục đánh giá trống
trơn khi chủ cửa hàng bận, mà đánh giá trống thì vô dụng. Cửa hàng gỡ bài bằng
`is_visible` (Quản trị → Đánh giá); khách tự gỡ thì xoá hẳn, vì nội dung là của
họ. `is_visible` **không** nằm trong `$fillable` — chỉ mã phía quản trị đổi được.

**Che tên người viết.** Trang đánh giá là trang công khai; "Nguyễn Thị Kiểm Thu"
hiển thị thành "Nguyễn T. K. Thu". Trang quản trị vẫn thấy tên đầy đủ.

---

## QĐ-07 — Yêu thích bắt buộc đăng nhập, và nằm ngoài `features.cart`

Danh sách yêu thích lưu trong bảng `wishlists`, không lưu session: phải còn
nguyên khi khách đăng nhập lại từ máy khác. Đây là chỗ **duy nhất** trong dự án
bắt khách phải có tài khoản.

Route yêu thích đặt **ngoài** khối `config('features.cart')` — lưu sản phẩm để
xem sau không liên quan tới việc có bán hàng hay không. Route đánh giá thì đặt
**trong**, vì quyền đánh giá dựa trên đơn hàng đã giao.

---

## QĐ-08 — `product_type` chỉ còn trả lời "đây là cái gì"

**Bối cảnh.** Cột `products.product_type` đang chứa lẫn hai câu trả lời khác nhau:

| Giá trị | Trả lời câu hỏi | Số dòng |
|---|---|---|
| `flower`, `plant` | món hàng này **là** gì | 11 |
| `event`, `wedding`, `gift` | mua để **làm gì** (dịp) | 4 |

QĐ-01 đã chốt ba trục tách biệt, và trục **dịp** đã có nhà riêng là **Category**
("Hoa cưới", "Hoa quà tặng"). Để chung vào `product_type` là ghi cùng một sự thật
ở hai nơi, và hậu quả nhìn thấy được ngay trên trang sản phẩm:

```
Danh mục      : Hoa cưới
Loại sản phẩm : Hoa cưới      ← trùng từng chữ
```

Đo trên dữ liệu thật: **11/15 sản phẩm** có "Loại sản phẩm" lặp lại đúng tên
"Danh mục". Dòng duy nhất nó khác là `lang-hoa-khai-truong` ("Hoa sự kiện" vs
"Hoa") — tức đúng chỗ cột này đang bị dùng như trục dịp.

**Quyết định.**

1. `product_type` thu về đúng ba giá trị bản chất: `flower`, `plant`, `other`
   (`App\Enums\ProductType`).
2. **KHÔNG tạo cột `occasion` mới.** 3 trong 4 dòng có dịp thì Category đã ghi
   rồi; thêm cột nữa là nhân bản lần thứ ba.
3. Trang sản phẩm **bỏ dòng "Loại sản phẩm"** — nó lặp lại "Danh mục". Cột vẫn
   giữ trong trang quản trị vì đó là phân loại nội bộ có thật.
4. Cả `product_type` lẫn `selling_form` **được cast sang enum**. Đổi lại là mọi
   giá trị lạ trong cơ sở dữ liệu thành lỗi chết người, nên migration
   `2026_08_27_010000` có bước quét dọn bắt buộc chạy TRƯỚC khi cast được bật.

**Chuyển dữ liệu.** Theo `slug` chứ không theo `id` (id không giống nhau giữa các
máy), nhờ vậy `down()` đảo ngược chính xác từng dòng — đã thử `migrate` →
`rollback` → `migrate` và phân bố về đúng như cũ.

| slug | cũ | mới | dịp còn ở đâu |
|---|---|---|---|
| `hoa-cam-tay-co-dau` | `wedding` | `flower` | Category "Hoa cưới" |
| `hop-hoa-hong-pastel` | `gift` | `flower` | Category "Hoa quà tặng" |
| `set-qua-cay-de-ban-kem-thiep` | `gift` | `plant` | Category "Hoa quà tặng" |
| `lang-hoa-khai-truong` | `event` | `flower` | **chưa có category tương ứng** |

**Việc còn lại cho chủ cửa hàng.** Riêng "Lẵng hoa khai trương" mất nhãn "Hoa sự
kiện" vì chưa có category nào tên như vậy. Cửa hàng nên tạo category "Hoa khai
trương / sự kiện" và xếp sản phẩm này vào — nhất quán với "Hoa cưới" và "Hoa quà
tặng". **Cố ý không tự tạo**: tạo category là quyết định kinh doanh, không phải
việc của một lượt dọn nợ kỹ thuật.

---

## QĐ-09 — Bốn việc treo, đã chốt

### 1. Menu tài khoản dùng `<details>`, không dùng dropdown Bootstrap

Sau nút tài khoản là **lối đi duy nhất** tới Đơn hàng, Sổ địa chỉ và Yêu thích.
Dropdown của Bootstrap cần JavaScript mới mở được — JS hỏng hoặc chưa tải xong
là khách mất đường vào, không còn chỗ nào khác để bấm.

`<details>` mở/đóng bằng chính trình duyệt, có sẵn bàn phím và trạng thái đóng-mở
cho trình đọc màn hình. `resources/js/account-menu.js` chỉ **thêm** hai tiện nghi
(bấm ra ngoài / bấm Esc thì đóng). Đã kiểm chứng bằng Chrome `--disable-javascript`:
menu vẫn hiện đủ 4 liên kết.

### 2. Ẩn dòng tagline dưới 420px — cho quy tắc chạy thật

Quy tắc `@media (max-width: 420px) { display: none }` đã có từ trước nhưng **nằm
sai chỗ**: đặt trước quy tắc `display: block` không điều kiện. Media query không
cộng thêm độ ưu tiên, nên quy tắc viết sau thắng và nó chưa bao giờ có tác dụng.

Đo ở 320px trước khi quyết: khối logo bị ép còn **67px** trong khi dòng tagline
cần **151px**, và nút "Đăng ký" bị cắt mất. Vậy ý đồ ban đầu là đúng — chỉ cần
chuyển quy tắc xuống dưới. Đã xác nhận: 320px → `none`, 430px → `block`.

### 3. Tạo danh mục "Hoa khai trương & sự kiện"

Bù đúng chỗ QĐ-08 còn thiếu. Ba trong bốn sản phẩm giữ được thông tin dịp nhờ
Category đã có sẵn; riêng "Lẵng hoa khai trương" thì không. Nay trục dịp đủ bộ:

```
Hoa cưới (50)  →  Hoa khai trương & sự kiện (55)  →  Hoa quà tặng (60)
```

Danh mục này **có nghiệp vụ thật**, không phải thêm cho đủ: cửa hàng đã có sẵn
tính năng "Sự kiện & số lượng lớn" nhận yêu cầu báo giá.

Migration `2026_08_28_010000` đảo ngược được: `down()` trả sản phẩm về "Hoa"
trước rồi mới xoá danh mục, và **chỉ xoá khi danh mục không còn sản phẩm nào** —
cửa hàng có thể đã xếp thêm hàng vào đó sau khi migration chạy.

### 4. `MAIL_FROM_ADDRESS` — chặn lỗi ngay lúc nó gây hại

Không thể tự đặt tên miền cho cửa hàng, nhưng có thể không để lỗi trôi qua im
lặng. `OrderMailer::deliversForReal()` nay xét **hai** điều kiện:

1. mailer là loại gửi thật (không phải `log`/`array`/`null`);
2. địa chỉ người gửi thuộc một tên miền **có thật**.

Điều kiện 2 bắt đúng cái bẫy dễ mắc nhất khi lên máy chủ: đổi `MAIL_MAILER=smtp`
nhưng quên `MAIL_FROM_ADDRESS` vẫn là `hello@example.com`. Lúc đó Laravel gửi
bình thường, không báo lỗi, nhưng Gmail chặn vì SPF không khớp — thư vào Spam
hoặc bị trả về. Nếu chỉ xét mailer thì trang đơn hàng sẽ khoe "đã gửi xác nhận"
trong khi khách chẳng nhận được gì.

Tên miền đầy đủ (`example.com`) so khớp **chính xác** hoặc theo tên miền con;
chỉ các đuôi dành riêng (`.test`, `.invalid`, `.example`, `.localhost`) mới xét
theo phần kết thúc. Tách hai loại là cần thiết: dùng `str_ends_with` cho
`example.com` sẽ báo nhầm **`notexample.com`** — một tên miền hợp lệ ai đó có
thể sở hữu.

---

## QĐ-10 — Chống đặt trùng đơn hàng

**Đo trước khi sửa.** Ba tình huống người dùng nêu, kiểm bằng request thật:

| Tình huống | Kết quả đo TRƯỚC khi sửa |
|---|---|
| Hai người giành món cuối cùng | ✅ đã an toàn — `lockForUpdate()`, kiểm 3 vòng × 5 người đồng thời: luôn đúng 1 đơn, tồn kho về 0, không âm |
| Bấm "Đặt hàng" nhiều lần | ⚠️ ra 1 đơn nhưng **do tình cờ**, và khách bị đẩy về **trang giỏ hàng kèm lỗi** |
| Thoát giữa chừng | chưa có cổng thanh toán ngoài nên chưa phát sinh |

**Vì sao "tình cờ" là không đủ.** `SESSION_DRIVER=database` đang vô tình xếp hàng
các request cùng phiên, và request sau thấy dữ liệu thanh toán đã bị dọn nên
trượt `requireStep(2)`. Hai vấn đề:

1. Đó là tác dụng phụ của trình điều khiển phiên, không phải điều dự án tự bảo
   đảm — đổi driver là mất.
2. Khách bấm hai lần **thấy trang giỏ hàng kèm lỗi**, tưởng đặt hỏng nên đặt
   lại lần nữa — đúng cái ta đang muốn tránh.

**Giải pháp: khoá chống trùng dùng một lần, hai lớp.**

- **Lớp 1 (nhanh)** — `CheckoutGuard::existingOrder()` hỏi trước xem khoá của
  phiên đã sinh ra đơn chưa. Xử lý trường hợp tuần tự: bấm Quay lại, tải lại.
- **Lớp 2 (chắc)** — ràng buộc `UNIQUE` trên `orders.idempotency_key`. Hai
  request song song đều vượt lớp 1 vì cùng đọc thấy "chưa có đơn"; chỉ cơ sở dữ
  liệu mới phân xử được. Kẻ thua bắt `QueryException` mã 23000, tra ra đơn kẻ
  thắng vừa tạo và trả về — transaction đã cuộn lại nên **kho không bị trừ hai lần**.

**Cột nằm trên `orders`, không tạo bảng riêng.** Ghi khoá và tạo đơn phải là MỘT
thao tác nguyên tử; tách hai bảng chính là chỗ sinh ra lỗi "khoá đã ghi nhưng
đơn chưa tạo".

**Chỉ nuốt đúng lỗi trùng `idempotency_key`.** Bảng `orders` còn một ràng buộc
UNIQUE nữa là `order_number` — trùng cái đó là sự cố thật, không được giấu.

### Lỗi có sẵn phát hiện trong lúc làm

`SESSION_KEY` từng là `'checkout'` — **nhánh cha** của `checkout.placed`,
`checkout.direct`, `checkout.coupon`. Laravel hiểu dấu chấm là mảng lồng nhau,
nên `session()->forget('checkout')` sau khi đặt hàng **xoá sạch cả nhánh**.

Đo được: khoá chống trùng vừa ghi xong đã bị xoá ngay trong cùng request, nên
lớp 1 không bao giờ tra ra đơn. Đã đổi thành `'checkout.form'` để `forget()` chỉ
dọn đúng dữ liệu biểu mẫu.

### Còn lại cho cổng thanh toán trực tuyến

Khi nối VNPay/MoMo, khoá này dùng lại được làm mã tham chiếu giao dịch. Lúc đó
cần thêm: trạng thái `chờ thanh toán`, đối soát khi khách thoát giữa chừng, và
thời hạn giữ hàng. Hiện chỉ có COD nên chưa phát sinh.

---

## QĐ-11 — Quên mật khẩu

**Dùng Password broker của Laravel, không tự viết.** Broker đã lo sẵn bốn thứ mà
tự viết rất dễ làm sai: token sinh bằng nguồn ngẫu nhiên an toàn, lưu trong cơ sở
dữ liệu dưới dạng **mã băm bcrypt** (đã kiểm: token trong bảng bắt đầu bằng `$2y$`),
hạn dùng 60 phút, và **dùng một lần** — xoá ngay khi đổi mật khẩu thành công.

### Bốn lớp chống lạm dụng

| Lớp | Cơ chế | Chặn gì |
|---|---|---|
| 1 | Câu trả lời **luôn giống nhau** | Dò xem email nào đã đăng ký |
| 2 | Broker chặn theo **email** (60 giây) | Dội liên tục vào một địa chỉ |
| 3 | `throttle:5,1` theo **IP** | Quét hàng nghìn địa chỉ khác nhau |
| 4 | Xoay `remember_token` sau khi đổi | Kẻ chiếm tài khoản còn giữ cookie "ghi nhớ" |

Lớp 1 là lý do **không dùng `exists:users,email`** trong FormRequest và **không
hiện mã trạng thái** mà `Password::sendResetLink()` trả về. Hàm đó phân biệt
"đã gửi" với "không tìm thấy người dùng"; hiện ra là biến biểu mẫu thành công cụ
dò danh sách khách hàng.

Lớp 4 dễ bị bỏ sót nhất. Người đặt lại mật khẩu thường vì **nghi tài khoản bị
chiếm**; nếu kẻ kia còn giữ cookie "ghi nhớ đăng nhập" thì đổi mật khẩu xong họ
**vẫn vào được**. Đã kiểm: đặt `remember_token` thành một giá trị biết trước rồi
đặt lại mật khẩu — chuỗi đó bị thay bằng 60 ký tự mới.

### Không dùng notification mặc định

Thư mặc định của Laravel là tiếng Anh, mang thương hiệu Laravel, và gửi thẳng qua
`Mail::send` **không đi qua lớp kiểm tra "có gửi thật được không"** của dự án.
Đã ghi đè `User::sendPasswordResetNotification()` để đi qua `PasswordResetMailer`.

### Gộp phần dùng chung

`OrderMailer` và `PasswordResetMailer` đều cần biết "hệ thống có gửi thật được
không". Logic đó chuyển sang `App\Services\Mail\MailTransport` để cả hai cùng gọi
mà không lớp nào phụ thuộc lớp kia. Đã kiểm hành vi `OrderMailer` giữ nguyên 5/5
sau khi tách.

Tương tự, quy tắc mật khẩu chuyển về `Password::defaults()` khai một lần ở
`AppServiceProvider`. Chép sang màn hình đặt lại thì sớm muộn hai nơi sẽ lệch —
và lệch theo hướng nguy hiểm: đặt lại mật khẩu **dễ hơn** lúc đăng ký.

---

## QĐ-12 — Hồ sơ tài khoản

**Đặt trong `Shop/`, không phải `Auth/`.** Đây là khu vực tài khoản của khách,
cùng nhóm với Sổ địa chỉ và Đơn hàng của tôi. `Auth/` dành cho việc ra vào hệ
thống (đăng nhập, đăng ký, đặt lại mật khẩu).

**Hai biểu mẫu tách riêng, không gộp.** Đổi tên và đổi mật khẩu có ràng buộc bảo
mật khác hẳn nhau. Gộp chung thì hoặc phải bắt nhập mật khẩu cho cả việc sửa tên
(phiền vô ích), hoặc để lọt việc đổi mật khẩu mà không cần xác thực.

**Đổi email bắt nhập mật khẩu hiện tại; đổi tên thì không.** Email là tên đăng
nhập, và cũng là nơi nhận liên kết đặt lại mật khẩu. Ai mượn được máy đang mở sẵn
phiên chỉ cần đổi email sang địa chỉ của họ rồi bấm "quên mật khẩu" là **chiếm
hẳn tài khoản**. Đổi mỗi tên thì rủi ro thấp.

### `AccountSecurity` — ba việc phải làm đủ khi mật khẩu đổi

1. ghi mật khẩu mới (đã băm);
2. xoay `remember_token` → mọi cookie "ghi nhớ" cũ vô hiệu;
3. **xoá các phiên đăng nhập khác** của người này.

Gom vào một lớp vì ba chỗ cần đúng bộ ba này: đặt lại qua email, đổi trong hồ sơ,
và sau này admin buộc đổi. Chép ba lần thì lần thứ ba sẽ quên việc số 3 — đúng
việc quan trọng nhất mà không ai nhìn thấy.

Xoá tường minh bản ghi trong bảng `sessions` **chắc chắn hơn** middleware
`AuthenticateSession`: middleware chỉ đá người dùng ra ở request tiếp theo của họ,
còn xoá bản ghi là cắt ngay lập tức.

### Lỗ hổng phát hiện trong lúc kiểm — `regenerate()` để lại phiên cũ

`$request->session()->regenerate()` mặc định là `regenerate(false)`: cấp id mới
nhưng **để nguyên bản ghi phiên cũ** trong bảng `sessions`, và bản ghi đó vẫn còn
dữ liệu đăng nhập.

Đo trực tiếp sau khi đổi mật khẩu: còn **hai** hàng phiên, giải mã payload thì
**cả hai đều chứa khoá `login_web_*`** — tức id cũ vẫn vào được, đúng cái mà đoạn
mã tưởng đã chặn. Đã đổi thành `regenerate(true)`; kiểm lại còn đúng **một** hàng.

---

## QĐ-13 — Trang Phân tích

**Hai nguồn dữ liệu, tuyệt đối không trộn.**

| Nguồn | Trả lời câu hỏi | Dùng cho |
|---|---|---|
| `user_events` | Điều gì đã xảy ra | Phễu, top sản phẩm, từ khoá, biểu đồ ngày |
| `orders` | Sự thật hiện tại của cửa hàng | Doanh thu, số đơn, bán chạy |

Lý do phải tách: hiện có **8 sự kiện `purchase` nhưng 0 đơn hàng** — các đơn thử
nghiệm đã bị xoá còn nhật ký thì không. Trong `meta` của sự kiện có sẵn
`quantity` và `unit_price`, nên tính doanh thu từ đó thì rất tiện — và **sai**:
đó là doanh thu của những đơn không còn tồn tại. Doanh thu chỉ đọc từ `orders`.

Khi chưa có đơn nào, khối doanh thu **nói thẳng là chưa có** và giải thích vì sao
phễu bên trên vẫn có số ở bước "Đặt hàng". Không có con số nào được bịa ra.

**Phễu đếm theo PHIÊN, không theo lượt.** Đếm lượt cho ra tỷ lệ vô nghĩa: một
người xem đi xem lại một sản phẩm 20 lần rồi mua 1 lần thành "chuyển đổi 5%",
trong khi thực tế người đó mua 100%. Phễu phải trả lời "bao nhiêu phiên đi được
tới bước này".

**`average = null` khác `average = 0`.** Chưa có đơn đã giao nào thì không có giá
trị trung bình để tính; giao diện hiện "chưa tính được" thay vì "0đ" — hai điều
khác hẳn nhau.

**Biểu đồ điền đủ cả ngày không có dữ liệu.** Chỉ vẽ những ngày có sự kiện thì
biểu đồ nối liền ngày 1 với ngày 5 như thể chúng liền nhau — nhìn tưởng hoạt động
đều, thực tế có ba ngày chết.

**Không nạp thư viện đồ thị.** 14 cột vẽ bằng CSS thuần. Thêm một thư viện vài
trăm KB cho việc này là không đáng, và mỗi thư viện là một thứ phải bảo trì.

**Mọi truy vấn gom về `AnalyticsService`** để Bảng điều khiển và Phân tích dùng
chung một định nghĩa cho cùng một chỉ số — hai màn hình không bao giờ nói hai con
số khác nhau cho cùng một câu hỏi. Đo được **21 truy vấn** cho 12 khối, không N+1
(tra sản phẩm và danh mục bằng `whereIn`, không lặp trong vòng).

**Ghi chú về chất lượng dữ liệu.** Chủ dự án đã đồng ý giữ lại dữ liệu sự kiện
phát sinh trong quá trình kiểm thử (139/186 bản ghi), vì đây là bài tập lớn khó
có lưu lượng thật. Các con số trên trang vì thế phản ánh cả việc kiểm thử. Cách
xử lý khi cần số liệu sạch: xoá bảng `user_events` rồi để dữ liệu tích lại.

---

## QĐ-14 — Gợi ý cá nhân hoá

**Không dùng lọc cộng tác ("người mua X cũng mua Y").** Đó là cách đúng khi có
hàng chục nghìn phiên. Đo được ở đây: nhiều nhất **7 sự kiện cho một tài khoản**
và **4 cho một phiên**. Với lượng đó, lọc cộng tác chỉ khuếch đại nhiễu — và tệ
hơn là nó **trông như** một hệ thống thông minh trong khi kết quả gần như ngẫu
nhiên.

**Cách làm: dựa trên hành vi của chính người đang xem.**

1. Chấm điểm **danh mục** và **hình thức bán** từ những gì họ vừa xem / thêm giỏ
   / thích / mua. Trọng số: mua 5, thích 4, thêm giỏ 3, xem 1 — mức cam kết giảm
   dần, xem có thể chỉ là bấm nhầm còn mua thì không.
2. Lấy sản phẩm khớp hai trục đó, bỏ những thứ họ đã xem.
3. Thiếu thì bù bằng sản phẩm phổ biến — và **nói rõ đó là hàng bù**.

**Hình thức bán là trục thứ hai, không chỉ danh mục.** Guide §4.3 nhấn mạnh đây
là đặc thù ngành hoa: người tìm "bó hoa" và người tìm "cây chậu" có nhu cầu khác
hẳn nhau dù cùng danh mục "Hoa". Kiểm chứng: phiên xem 4 cây chậu được gợi ý
"Lưỡi hổ mini để bàn — *Cùng hình thức Cây chậu*"; phiên xem 3 loại hoa được gợi
ý hoa hồng và hướng dương với lý do *Cùng hình thức Bó hoa*.

**Chạy cho cả khách vãng lai.** Nhận cả `user_id` lẫn `session_id`. Phần lớn
người vào xem chưa đăng nhập; gợi ý chỉ cho người có tài khoản thì tính năng gần
như không bao giờ chạy. Người đã đăng nhập được xét cả hành vi lúc chưa đăng nhập
trong cùng phiên — cắt đôi lịch sử là mất tín hiệu.

**Nói thật khi chưa biết gì về người xem.** Tiêu đề đổi theo việc gợi ý có thật
sự dựa trên hành vi hay không: *"Gợi ý cho bạn / Dựa trên những sản phẩm bạn vừa
xem"* so với *"Được quan tâm nhiều / Những sản phẩm nhiều người đang xem nhất"*.
Gọi một danh sách phổ biến là "gợi ý riêng cho bạn" thì chỉ cần hai người ngồi
cạnh nhau mở máy là lộ ngay.

**Mỗi thẻ kèm LÝ DO** ("Vì bạn quan tâm Hoa cưới", "Cùng hình thức Bó hoa"). Đây
vừa là UX tốt vừa là ràng buộc lên chính mình: không viết được lý do thật thì
không có quyền gợi ý.

Hiệu năng: trang chủ **14 truy vấn** khi chưa có lịch sử, **22** khi có — không
N+1 (nạp sẵn `category`, `promotions`, điểm đánh giá trong truy vấn nền).

---

## QĐ-15 — Chuẩn hoá tỉnh/thành

**Vấn đề.** `orders.shipping_province` và `addresses.province` là ô chữ tự do, nên
"Hà Nội", "Ha Noi", "HN" là ba tỉnh khác nhau khi thống kê — và cũng không tính
được phí giao theo vùng về sau.

**Danh sách phải TRA CỨU, không được nhớ.** Đây là dữ liệu thật ngoài đời. Nghị
quyết Quốc hội ngày 12/6/2025 sắp xếp lại đơn vị hành chính cấp tỉnh: từ 63 xuống
**34 đơn vị (6 thành phố trực thuộc trung ương + 28 tỉnh)**, chính quyền mới hoạt
động từ 1/7/2025.

**Đã đối chiếu chéo hai nguồn.** Nguồn thứ nhất tự mâu thuẫn: ghi "5 thành phố"
trong khi tiêu đề nói 34 đơn vị, đánh số tới 29 tỉnh nhưng chỉ liệt kê 28, và
**thiếu Thành phố Huế** — tổng chỉ 33. Nguồn thứ hai đủ và nhất quán 6 + 28 = 34,
với 28 tỉnh trùng khớp hoàn toàn nguồn đầu. Lấy theo nguồn thứ hai.

**Để ở `config/`, không tạo bảng.** Đây là dữ liệu tham chiếu của nhà nước, admin
không có quyền và cũng không có lý do sửa. Guide §27 chống tạo bảng khi chưa có
nghiệp vụ rõ ràng.

**Sáp nhập sau này không làm hỏng đơn cũ.** `orders.shipping_province` là BẢN CHỤP
tại thời điểm đặt — đơn giao về "Hà Tây" năm 2007 vẫn phải đọc được là "Hà Tây".
Vì vậy `Provinces::isValid()` chỉ dùng cho dữ liệu MỚI NHẬP, không dùng để đọc dữ
liệu cũ. Component `<x-form.province-select>` giữ lại giá trị cũ thành một mục
riêng có ghi chú — nếu chỉ in 34 lựa chọn thì `<select>` tự nhảy về mục đầu và
khách bấm Lưu là địa chỉ bị đổi sang tỉnh khác mà không hề hay biết.

**Chặn ở máy chủ, không chỉ ở giao diện.** `Rule::in(Provinces::all())` trong cả
hai FormRequest. Ô `<select>` chỉ là tiện lợi — sửa HTML là gửi được giá trị bất kỳ.

---

## QĐ-16 — Email theo mốc đơn hàng

**Chỉ bốn mốc, không phải sáu.** `OrderStatus::notifiesCustomer()` quyết định.

| Trạng thái | Gửi | Vì sao |
|---|---|---|
| Chờ xác nhận | không | đã có email lúc đặt hàng |
| Đã xác nhận | **có** | khách yên tâm cửa hàng đã nhận đơn |
| Đang chuẩn bị | không | việc nội bộ, khách không làm gì với thông tin đó |
| Đang giao | **có** | cần có mặt để nhận hàng |
| Đã giao | **có** | đối chiếu, và là lúc hợp lý để mời đánh giá |
| Đã huỷ | **có** | biết ngay, nhất là khi không phải họ huỷ |

Gửi đủ sáu bước là biến hộp thư khách thành nơi nhận thông báo rác — mà rác thì
người ta bỏ qua, kể cả cái quan trọng.

**MỘT Mailable cho cả bốn mốc.** Bốn lớp gần giống hệt nhau chỉ khác vài câu chữ
thì sửa bố cục email phải sửa bốn chỗ, và chắc chắn có chỗ bị quên. Phần khác
nhau — tiêu đề và lời giải thích — nằm ở `OrderStatus`, cùng nơi định nghĩa vòng
đời đơn hàng.

**GỬI SAU KHI TRANSACTION COMMIT, không gửi bên trong.** Bên trong transaction
thì email có thể đã bay đi trong khi transaction sau đó bị cuộn lại — khách nhận
thư "đơn đã giao" cho một đơn thật ra vẫn đang chờ. **Thư gửi rồi không rút lại
được, còn dữ liệu thì rollback được**, nên thứ không rút lại được phải đi sau cùng.

**Đặt trong `OrderService::changeStatus()`, không đặt ở controller.** Cả admin đổi
trạng thái lẫn khách tự huỷ đều đi qua hàm này. Để ở controller là hai bản sao, và
bản thứ hai sẽ quên.

**Nuốt lỗi gửi thư.** Lúc gọi tới đó trạng thái đã ghi và kho đã hoàn. Ném lỗi ra
sẽ cho admin xem trang lỗi trong khi thao tác **thật sự đã thành công** — họ bấm
lại, và lần bấm thứ hai bị máy trạng thái từ chối vì đơn đã ở trạng thái đó rồi.

Thư huỷ đơn hiện thêm lý do huỷ và **bỏ phần địa chỉ giao** — địa chỉ không còn ý
nghĩa với đơn đã huỷ.

---

## QĐ-17 — Trả nợ kỹ thuật tồn đọng

Rà lại toàn bộ mục "Chưa làm" và "Rủi ro" của các báo cáo trước. Tám mục là nợ
thật và đã xử lý; phần còn lại hoặc là quyết định có chủ đích, hoặc cần chủ cửa
hàng cung cấp dữ liệu.

### 1. Email cảnh báo đổi mật khẩu

**Đây là thư CẢNH BÁO, không phải thư xác nhận.** Người tự đổi thì đã biết rồi.
Thư này tồn tại cho trường hợp ngược lại: kẻ chiếm tài khoản đổi mật khẩu để khoá
chính chủ ra ngoài. Không có nó thì nạn nhân chỉ phát hiện khi lần sau đăng nhập
không được — lúc đó đã muộn.

Gửi tới địa chỉ email **ghi nhận trước khi đổi**: kẻ tấn công thường đổi email
trước rồi mới đổi mật khẩu.

**KHÔNG kèm liên kết đặt lại mật khẩu.** Thư cảnh báo có nút bấm là mẫu quen
thuộc của thư lừa đảo ("tài khoản bị xâm nhập, bấm vào đây"). Dạy khách bấm theo
là dạy họ mắc bẫy lần sau.

### 2. Luồng quên mật khẩu chưa huỷ phiên — lỗi phát hiện khi rà

QĐ-12 nói `AccountSecurity` gom ba việc để dùng chung, nhưng
`PasswordResetController` vẫn **tự ghi mật khẩu** bằng `forceFill` — tức chỉ đổi
`remember_token`, còn **các phiên đang mở của kẻ chiếm tài khoản vẫn sống**. Người
đặt lại mật khẩu thường vì nghi bị chiếm, nên đây đúng là chỗ nguy hiểm nhất bị bỏ
sót. Đã cho đi qua `AccountSecurity::changePassword()` với `keepSessionId = null`.

### 3. Thư báo cửa hàng khi khách tự huỷ đơn

Khách huỷ là đơn biến mất khỏi danh sách cần giao mà không ai bấm gì trong trang
quản trị. Nếu đã cắt hoa hoặc đã hẹn shipper thì phải biết ngay.

Đặt ở **controller**, không ở `changeStatus()`: hàm đó dùng chung cho cả admin huỷ
lẫn khách huỷ, đặt vào đó thì cửa hàng tự huỷ cũng gửi thư báo cho chính mình.
`replyTo` là email của khách để nhân viên bấm Trả lời là liên hệ được ngay.

### 4. Gợi ý không đề xuất hàng hết

Trang danh sách vẫn hiện hàng hết vì khách **chủ động** vào tìm. Còn gợi ý là
**cửa hàng chủ động mời** — mời một thứ không mua được là lãng phí chỗ. Sản phẩm
không quản lý tồn kho (hoa làm theo đơn) luôn được coi là còn.

### 5. Trang ghi công ảnh `/nguon-anh`

CC BY và CC BY-SA **bắt buộc** ghi tên tác giả "theo cách hợp lý với phương tiện".
Với website thì ghi trong `ASSETS.md` là chưa đủ — người xem không bao giờ thấy
tệp đó. Trang đọc thẳng từ các tệp `credits.json` do script tải ảnh sinh ra, nên
luôn khớp ảnh thật đang dùng; chép tay sang Blade là tải ảnh mới thì trang lạc
hậu ngay, mà lạc hậu ở đây nghĩa là thiếu ghi công cho một tác giả.

### 6. Chỉnh chỉ mục

Thêm `user_events(event_type, created_at)` — trang Phân tích luôn lọc cả hai cột
cùng lúc. Thứ tự cột quan trọng: cột so bằng trước, cột so khoảng sau.

Bỏ `products.product_type` — sau QĐ-08 cột này chỉ còn 3 giá trị trên 15 dòng và
không câu truy vấn nào lọc theo nó.

### 7. Dọn token đặt lại mật khẩu

`Schedule::command('auth:clear-resets')->daily()`. Ghi rõ trong `routes/console.php`
rằng phải có `schedule:work` hoặc cron thì lịch mới chạy — giống hệt chuyện hàng
đợi email, khai lịch không có nghĩa là xong.

### 8. Nén ảnh sản phẩm — và một lỗi tự gây ra

`tools/optimize-product-photos.mjs`: cắt 4:5, tối đa 900px, JPEG chất lượng 78.
Tổng **3092 KB → 1412 KB (giảm 55%)**.

Lần chạy đầu **làm hai ảnh NẶNG THÊM 20–26%** vì phóng to ảnh gốc nhỏ hơn 900px —
thêm byte mà không thêm chi tiết, ảnh còn nhoè vì nội suy. Đã thêm
`withoutEnlargement: true`. Hệ quả: ảnh nhỏ giữ nguyên tỉ lệ gốc thay vì cắt sẵn
4:5 — không sao, `.product-card__image` đã có `aspect-ratio: 4/5` kèm
`object-fit: cover`.

---

## QĐ-18 — Tìm kiếm mờ (fuzzy search)

### 1. Chỉ mục là CỘT THẬT, không tính lúc truy vấn

`products.search_name` (chỉ tên, dùng xếp hạng) và `products.search_text` (tên +
mã + mô tả ngắn + tên danh mục + nhãn hình thức bán, dùng tìm ra). Cả hai lưu bản
**đã bỏ dấu, chữ thường**.

Bỏ dấu ngay trong SQL nghĩa là gọi hàm lên từng dòng mỗi lần tìm — không chỉ mục
nào dùng được, và MariaDB 10.4 cũng không có chỉ mục theo biểu thức để cứu.

Hai cột **dựng lại được hoàn toàn** bằng `php artisan search:reindex`, nên không
phải nguồn sự thật, không có nguy cơ lệch dữ liệu vĩnh viễn. Observer trên
`Product` (khi lưu) và `Category` (khi đổi tên) giữ chúng luôn đúng.

### 2. Khớp theo ĐẦU TIẾNG, không khớp chuỗi con

Bản đầu dùng `LIKE '%hong%'`. Kết quả: gõ "hồng" ra cả cây Monstera, vì mô tả của
nó có "**kh**ô**ng** gian" và "**ph**ò**ng** khách". Tiếng Việt bỏ dấu có rất
nhiều tiếng lồng vào nhau như vậy.

Nay: `LIKE 'hong%' OR LIKE '% hong%'`. Vẫn giữ được cách gõ tự nhiên ("mons" ra
"monstera") vì người ta gõ dở chừng từ **đầu** chứ không gõ khúc giữa.

### 3. Ba lớp, chỉ hạ xuống lớp sau khi lớp trước không ra gì

| Lớp | Khi nào | Ví dụ |
|---|---|---|
| Bỏ dấu | luôn luôn | `hoa hong` → Hoa hồng đỏ Ecuador |
| Sửa lỗi gõ | từ khoá KHÔNG có trong catalog | `hoaa`, `bosai`, `montera` |
| Nới lỏng AND→OR | không sản phẩm nào khớp đủ mọi từ | `hoa bonsai` |

Sửa lỗi gõ **chỉ chạy khi từ khoá không có hàng**, nên gõ đúng thì không bao giờ
bị đoán sai thành thứ khác.

### 4. Ngưỡng Levenshtein tăng theo độ dài, và từ ngắn phải trùng chữ đầu

`≤2 ký tự → 1 lỗi · 3–4 → 1 · 5–7 → 2 · ≥8 → 3`. Với từ 2 chữ thì sai 1 chữ đã là
một nửa từ ("ha" cách "ba", "ma", "la" đúng 1) — nên từ dưới 5 ký tự **bắt buộc
trùng chữ cái đầu**. Người ta hiếm khi gõ sai chữ đầu: đó là chữ được nghĩ tới
trước và gõ chậm nhất.

### 5. "Gợi ý thêm" khác hẳn "sửa lỗi"

Gõ `ha` thì đúng là có "Bó tulip **Hà** Lan" — không có gì sai để mà sửa. Nhưng
"hoa" chỉ cách một chữ và có ở bảy sản phẩm. Việc đúng là **vẫn trả kết quả cho
"ha"** và hỏi thêm "Bạn có muốn tìm «hoa»?", chứ không lặng lẽ đổi từ khoá.

Hai luật chặn hỏi bừa:
- **Đang gõ dở một từ có thật thì im lặng.** "mon" là phần đầu của "monstera",
  "bon" của "bonsai" — khách chưa gõ xong chứ không gõ sai.
- **Từ gợi ý phải phổ biến gấp đôi** thì mới hỏi.

### 6. Không bao giờ đổi từ khoá sau lưng người dùng

Mỗi lần hệ thống can thiệp đều hiện một dòng nói rõ đã can thiệp gì
(`x-product.search-notice`, ba biến thể). Tìm kiếm mờ mà im lặng khiến khách gõ
"hoaa", thấy một trang đầy hoa, và tưởng cửa hàng có đúng thứ tên đó.

### 7. Endpoint gợi ý nằm trong `routes/web.php`, không tạo `routes/api.php`

Dự án là ứng dụng Blade dùng session. Thêm `routes/api.php` kéo theo cả tầng xác
thực bằng token (Sanctum) cho **một** endpoint công khai chỉ đọc. Đường dẫn vẫn
mang tiền tố `/api/` để đọc `route:list` là biết ngay nó trả JSON.

`throttle:60,1` là bắt buộc: mỗi lượt gọi là một lần quét bảng sản phẩm cộng một
lượt duyệt từ điển, và ô gợi ý gọi theo từng phím gõ.

### 8. Rủi ro đã biết

`LIKE '%...%'` không dùng được chỉ mục B-tree → quét bảng. Với 15 sản phẩm không
thấy gì; tới hàng chục nghìn thì lời giải đúng là FULLTEXT hoặc máy tìm kiếm
riêng. **Không** thêm FULLTEXT bây giờ vì `innodb_ft_min_token_size` mặc định là 3
— nó sẽ âm thầm bỏ qua mọi từ khoá 2 ký tự, và đổi tham số đó cần khởi động lại
máy chủ CSDL.

---

## QĐ-19 — Trả nợ đợt 2

### 1. Hàng đợi thư: cơ chế có sẵn, MẶC ĐỊNH TẮT

`MAIL_QUEUE=true` đẩy thư ra bảng `jobs` thay vì gửi trong request. Mặc định
`false` **có cân nhắc**: bật hàng đợi mà quên chạy `queue:work` thì thư nằm im mãi
mãi — không lỗi, không cảnh báo, chỉ là khách không nhận được thư. Hỏng kiểu im
lặng tệ hơn chậm vài giây.

`MailTransport::deliver()` là **nơi duy nhất** quyết định gửi ngay hay xếp hàng,
nên không có loại thư nào bị bỏ sót.

**Đo được và phải sửa:** xếp thư vào hàng đợi mà thiếu `SerializesModels`, PHP
serialize cả model vào cột `payload` — **kèm chuỗi băm mật khẩu và
`remember_token`** (payload 3988 byte, có `$2y$`). Đã thêm trait cho cả 5 mailable;
payload còn 1356 byte và không còn hai thứ đó.

### 2. Tắt nhận thư: chỉ tắt được thư đổi trạng thái đơn

- Thư **xác nhận đơn** là biên nhận mua hàng → không cho tắt.
- Thư **bảo mật** tồn tại đúng cho lúc tài khoản bị chiếm → cho tắt là mở sẵn cửa
  cho kẻ tấn công tắt hộ nạn nhân.
- Khách **vãng lai** không có tài khoản nên không có nơi bày tỏ ý muốn → vẫn gửi.

### 3. Phí giao theo vùng — 4 vùng, số tiền là MẪU

`config/shipping.php`. Vùng mặc định là `far` chứ **không** phải vùng rẻ nhất: sót
một tỉnh mà tính giá nội thành thì cửa hàng lỗ mà không ai phát hiện.

Tỉnh được gắn vào giỏ ở `CheckoutSource::basket()` — **một chỗ duy nhất**, nên
màn hình thanh toán, tóm tắt tiền và `OrderService` lúc ghi đơn không thể lệch
nhau. Trang giỏ hàng chưa biết địa chỉ nên ghi rõ **"tạm tính, chốt khi nhập địa
chỉ"** thay vì im lặng rồi nhảy giá ở bước sau.

`php artisan shipping:zones` soát tên tỉnh lệch giữa hai tệp config — nó đã tìm ra
"Hà Giang", một tỉnh đã nhập vào Tuyên Quang năm 2025 và không bao giờ khớp nữa.

### 4. KHÔNG tự sửa tỉnh cũ trong sổ địa chỉ

`addresses:check-provinces` **chỉ báo cáo**. Sáp nhập không phải lúc nào cũng
một-đối-một: có tỉnh bị chia về nhiều nơi tuỳ huyện. Máy tự đoán rồi ghi đè là làm
hàng đi nhầm nơi mà không có cách nào lần lại. Bảng `orders` thì tuyệt đối không
đụng — `shipping_province` ở đó là bản chụp lịch sử.

### 5. Đo hiệu quả gợi ý — thêm `?ref=`, KHÔNG thêm loại sự kiện

Bấm vào thẻ trong khối gợi ý mang theo `?ref=goi-y:home`, ghi vào `meta` của sự
kiện `product_view`. Thêm loại sự kiện riêng sẽ làm mọi phép đếm lượt xem hiện có
thiếu mất phần này. Giá trị `ref` đến từ thanh địa chỉ nên bị chặn theo
`^[a-z0-9:_-]{1,40}$`.

### 6. So sánh kỳ trước: null ≠ 0

Kỳ trước bằng 0 thì mọi con số dương đều là "tăng vô hạn" — in "+∞%" hay "+100%"
đều là bịa. `AnalyticsService::change()` trả `null` và giao diện nói "kỳ trước
chưa có dữ liệu". Kỳ `all` không có kỳ trước → phần so sánh tự ẩn.

Mốc kết thúc dùng `<` chứ không `<=`: mốc cuối của kỳ trước **chính là** mốc đầu
của kỳ này, `<=` sẽ đếm trùng bản ghi rơi đúng vào giây đó.

### 7. Túi lỗi riêng cho từng biểu mẫu

Trang Hồ sơ có ba biểu mẫu và **hai** trong số đó có ô tên `current_password`. Túi
mặc định dùng chung khiến một câu "Mật khẩu hiện tại không đúng" in ra **hai lần**
ở hai chỗ. `$errorBag` riêng + tham số `bag` của `x-form-error` sửa việc đó.

Đồng thời phát hiện: component ô mật khẩu chỉ **tô viền đỏ** chứ không in lý do,
nên trang đặt lại mật khẩu và biểu mẫu đổi mật khẩu trước nay nuốt lỗi hoàn toàn —
nhập sai chỉ thấy trang nạp lại trống trơn.

### 8. Bẫy Blade: `<x-...>` trong CHÚ THÍCH vẫn bị biên dịch

Viết `<x-form-error>` trong một chú thích PHP bên trong tệp Blade làm hỏng cả
trang (`Undefined variable $component`). Trình biên dịch component quét toàn bộ
tệp, không phân biệt chú thích. Trong chú thích phải viết không có dấu ngoặc nhọn.

---

## QĐ-20 — Ô tìm kiếm bung ngang thanh header

Bấm kính lúp thì ô nhập **phủ ngang cả `.site-header__bar`** thay vì thả xuống một
hộp nhỏ ở góc phải. Đo được: ô nhập từ ~414px lên **1121px**.

Trong lúc khách đang gõ thì logo và menu không còn việc gì để làm, nên mượn đúng
chỗ đó là hợp lý — và gõ xong đóng lại là mọi thứ về nguyên trạng, thanh header
không phải nhường vĩnh viễn một chỗ nào.

Ba chi tiết bắt buộc:
- `.site-header__bar { position: relative }` và `.header-search { position: static }`
  — panel phải neo vào **thanh**, không neo vào cái nút kính lúp.
- Danh sách gợi ý nằm **trong `.header-search__field`**, không trong panel: neo vào
  panel thì gợi ý trải dài cả nghìn pixel, lệch hẳn khỏi ô nhập nó thuộc về.
- **Nút đóng là bắt buộc.** Panel phủ kín thanh nên nút vừa bấm đã nằm khuất; không
  có nó thì cách duy nhất để đóng là Esc hoặc bấm ra ngoài — hai thao tác người
  dùng phải biết trước mới dùng.

---

## QĐ-21 — Ví voucher

### 1. Hai cột mới trên `coupons`, mỗi cột một câu hỏi khác nhau

- `is_public` — có hiện ở trang Voucher không. Mã in trên tờ rơi hay gửi riêng cho
  một khách **vẫn nhập tay được** nhưng không được hiện công khai. Thiếu cột này
  thì mọi mã nội bộ lộ ra cho tất cả mọi người.
- `per_user_limit` — **một khách** dùng được mấy lần. `usage_limit` sẵn có là giới
  hạn **tổng toàn hệ thống**; không có cột thứ hai thì một người dùng hết sạch 100
  lượt của chương trình vẫn hoàn toàn hợp lệ.

### 2. `UNIQUE(user_id, coupon_id)` là phần quan trọng nhất

Bấm "Lưu" hai lần nhanh, hoặc mở hai tab cùng bấm, gửi hai request song song. Kiểm
tra bằng PHP ("đã lưu chưa?" rồi mới ghi) không chặn được — cả hai đều đọc thấy
"chưa" trước khi bên nào kịp ghi. Chỉ ràng buộc ở tầng CSDL mới thật sự chặn, cùng
cách đã dùng cho `orders.idempotency_key`.

### 3. Đếm lượt dùng đặt trong `CouponService::redeem()`, không ở controller

Mọi đường tạo đơn đều đi qua đó, nên không có lối nào tăng bộ đếm chung mà quên bộ
đếm riêng. `markUsed()` dùng upsert nên khách **nhập tay** một mã chưa từng lưu vào
ví vẫn được đếm — chỉ đếm mã đã lưu thì giới hạn thành vô nghĩa với đúng những
người không dùng nút Lưu.

Huỷ đơn trả lại **cả hai** bộ đếm, có `GREATEST(...,0)` chặn âm.

### 4. Xem được khi chưa đăng nhập, lưu thì phải đăng nhập

Voucher là một trong những lý do chính khiến người ta chịu tạo tài khoản; giấu cả
trang sau màn đăng nhập là bỏ mất đúng tác dụng đó.

`?redirect=` đưa khách quay lại đúng chỗ sau khi đăng nhập. **Chỉ nhận đường dẫn
nội bộ** — không kiểm thì đây là lỗ hổng chuyển hướng mở: link
`/login?redirect=https://trang-gia.example` khiến khách đăng nhập thật xong bị đẩy
sang trang giả và nhập lại mật khẩu ở đó. Đã thử: `evil.example` và
`//evil.example` đều bị chặn về trang chủ.

---

## QĐ-22 — Gợi ý theo nhu cầu, không chỉ theo hành vi

### 1. `PlantAdvisor` KHÔNG thay thế `RecommendationService`

| | Suy từ | Chạy được khi |
|---|---|---|
| RecommendationService | hành vi (đã xem, đã thích) | khách đã xem vài sản phẩm |
| PlantAdvisor | điều kiện khách tự khai | ngay lượt truy cập đầu tiên |

Người mua cây lần đầu — nhóm đông nhất và bối rối nhất — **không có hành vi nào để
mà suy**. Họ chỉ biết "tôi có cái ban công đầy nắng" hoặc "tôi hay quên tưới".

### 2. Một bảng `product_traits` cho ba loại nhãn

`placement`, `feng_shui`, `accessory_for` có cấu trúc hoàn toàn giống nhau và được
truy vấn theo đúng một kiểu. Ba bảng riêng nghĩa là ba migration, ba model, ba đoạn
mã lưu — tất cả chỉ khác tên bảng.

**Điều kiện để cách này không thành bảng rác:** giá trị của mỗi loại phải nằm trong
một enum đóng, và `ProductTrait::isValid()` chặn trước khi ghi. Cột là varchar nên
CSDL không chặn được — `Product::syncTraits()` là nơi ghi duy nhất.

Không dùng JSON trên `products`: MariaDB 10.4 không đánh chỉ mục vào trong JSON, nên
mọi câu "tìm sản phẩm có nhãn X" phải quét toàn bảng.

### 3. Phong thuỷ: ghi nhận nhu cầu, không khẳng định thay khách

Rất nhiều khách mua cây cảnh ở Việt Nam hỏi "cây này hợp mệnh gì" — bỏ qua là bỏ
mất một nhu cầu có thật. Nhưng trình bày như một chỉ số kỹ thuật thì thành ra cửa
hàng khẳng định một điều mình không có tư cách khẳng định.

Cách xử lý: gọi đúng tên là **"theo quan niệm phong thuỷ dân gian"** ngay trên
nhãn, và **admin tự gán** — hệ thống không suy ra mệnh từ tên hay màu cây. Cây nào
không có quy ước dân gian nào thì để trống, thà thiếu còn hơn gán bừa.

### 4. Mua kèm: phụ kiện tự khai, không nối tay từng cặp

Bắt admin vào từng cây chọn "chậu nào hợp" là công việc nhân lên theo cấp số nhân,
và cây nhập về sau sẽ không có phụ kiện nào cho tới khi ai đó nhớ ra.

Thay vào đó phụ kiện mang nhãn `accessory_for = pot / bouquet / all`. Gán một lần,
áp dụng cho mọi cây cùng hình thức bán, kể cả cây chưa tồn tại.

Phụ kiện mang `selling_form = other`, **không phải `pot`** — nếu không thì chậu sứ
rỗng sẽ tự gợi ý chính nó làm phụ kiện cho nó.

`all` chỉ dành cho thứ **thật sự** dùng với mọi loại hàng. Bình tưới và phân bón đã
phải đổi từ `all` sang `pot/original/set` vì bó hoa cắt cành không bón phân được —
đo được ở lần chạy đầu.

### 5. Hai bẫy Blade gặp lại trong đợt này

- `Đã lưu@if(...)` — Blade chỉ nhận directive khi ký tự ngay trước `@` không phải
  chữ cái. `@if` bị bỏ qua nhưng `@endif` vẫn biên dịch → lỗi cú pháp PHP cả tệp.
- `$product->exists` trong `_form.blade.php` — trang **Thêm sản phẩm** không truyền
  `$product`; cả biểu mẫu dựa vào `??` để nuốt biến chưa tồn tại. Viết
  `$product->exists` không kiểm tra trước thì trang tạo lỗi 500 còn trang sửa vẫn
  chạy — sai một nửa nên rất dễ lọt.

---

## QĐ-23 — Nhắc lịch chăm cây (Personalization)

### 1. Đây là tính năng chỉ ngành cây cảnh mới có

Bán một cái áo là xong. Bán một cái cây thì mới bắt đầu: khách mang về,
quên tưới hai tuần, cây chết, và họ kết luận **"tôi không trồng được cây"**
chứ không mua lại. Nhắc đúng lúc là giữ cả cái cây lẫn người khách.

### 2. Hai ô SỐ NGÀY, tách khỏi hai ô lời khuyên sẵn có

`care_info['water']` là chữ viết cho người đọc ("tưới 2 lần/tuần"), mỗi sản
phẩm một cách diễn đạt — máy không đọc được để tính ngày nhắc.
`care_info['water_days']` là số, chỉ có một việc.

Đúng nguyên tắc "không dùng một trường kiêm nhiều ý nghĩa". Cả hai cùng đi
vào thư nhắc: con số quyết định *khi nào* gửi, lời khuyên là *nội dung*.

### 3. KHÔNG BỊA CHU KỲ

Cây nào admin chưa khai `water_days` thì không sinh lịch nào. Đoán "chắc là
3 ngày" rồi nhắc sai là làm hỏng cây của khách bằng chính tính năng sinh ra
để cứu nó. Đo được: bó hoa cắt cành không sinh lịch nào — đúng, hoa tươi
không có chu kỳ chăm định kỳ.

### 4. Sinh lịch khi đơn ĐÃ GIAO, không phải khi đặt

Lúc đặt thì cây còn ở cửa hàng. Nhắc tưới một cái cây chưa tới tay là thông
báo rác. Kỳ đầu tính từ hôm nhận **cộng nguyên chu kỳ** — cây vừa giao
thường đã được tưới ở cửa hàng.

### 5. `advance()` tính từ HÔM NAY, không cộng dồn từ mốc cũ

Máy chủ nghỉ một tuần rồi chạy lại: cộng dồn từ `next_due_at` cũ sẽ ra một
ngày **vẫn nằm trong quá khứ**, và lệnh gửi thư lại ở lần chạy kế tiếp, rồi
lại lần nữa, cho tới khi đuổi kịp hiện tại. Khách nhận một loạt thư nhắc
tưới cùng một cây.

### 6. Gom theo khách — một thư cho nhiều việc

Bốn cây cùng đến hạn tưới hôm nay → **một** thư liệt kê bốn cây. Bốn thư
riêng trong một phút là cách nhanh nhất để bị đánh dấu spam — và một khi bị
đánh dấu thì thư xác nhận đơn hàng cũng rơi vào hộp rác theo.

### 7. Gửi hỏng thì KHÔNG dời hạn

Dời `next_due_at` khi thư chưa đi được là làm mất hẳn lần nhắc đó: khách
không được nhắc mà hệ thống tưởng đã nhắc rồi.

### 8. Ba mức tắt, không phải một

- công tắc **tổng** trong Hồ sơ (`users.notify_care_reminders`)
- tắt **riêng từng cây từng việc** ở trang Lịch chăm cây
- nút **"Vừa làm xong"** dời hạn khi khách tưới sớm

Tách khỏi `notify_order_updates`: thư đơn hàng là việc mua bán, thư nhắc
chăm là dịch vụ sau bán. Có người muốn cái này mà không muốn cái kia.

`firstOrCreate` chứ không `updateOrCreate` khi mua lại cùng một cây — nếu
không thì lịch khách đã tự tắt sẽ bị bật lại sau lưng họ.

---

## QĐ-24 — Giá linh hoạt theo thời điểm (Dynamic Pricing)

### 1. Hai cột trên `promotions`, không tạo bảng mới

`starts_at`/`ends_at` chỉ khai được **một khoảng liên tục**. Không khai được
"mỗi ngày 19:00–22:00" — mà đó chính là chương trình xả hàng cuối ngày, thứ
phải lặp lại hằng ngày.

- `daily_start_time` / `daily_end_time` — khung giờ trong ngày
- `weekdays` — mảng thứ (1=T2…7=CN, ISO-8601)

Bỏ trống = hành vi cũ, nên mọi chương trình đang chạy không đổi gì.

Đây là **điều kiện áp dụng** của một chương trình, không phải thực thể có
đời sống riêng — nên là cột, không phải bảng.

### 2. Khung qua nửa đêm phải chạy được

"22:00 → 02:00" có giờ bắt đầu **lớn hơn** giờ kết thúc. Bỏ qua trường hợp
này thì khung đó không bao giờ đúng và admin tưởng chương trình hỏng.
Vì vậy cũng **không** dùng `after:daily_start_time` để kiểm tra — quy tắc đó
sẽ chặn đúng trường hợp cần dùng nhất.

### 3. Không cast `daily_*_time` sang datetime

Chúng là **giờ trong ngày**, không phải mốc thời gian. Cast sang datetime
thì Carbon gắn thêm 01/01/1970 và mọi phép so sánh với "bây giờ" đều sai.
Giữ chuỗi `HH:MM:SS` rồi so chuỗi — hợp lệ vì độ dài cố định và đã đệm số 0.

### 4. TĂNG GIÁ dịp cao điểm làm thế nào

Không có "khuyến mại âm". Dịp cao điểm thì cửa hàng đặt `base_price` theo
giá cao điểm và chạy chương trình **giảm** vào khung giờ/ngày thấp điểm.
Cách này giữ được luật duy nhất *"giá cuối ≤ giá gốc"* mà `PricingService`
đang bảo vệ — bỏ luật đó là mở đường cho giá nhảy lung tung vì cấu hình sai.

### 5. LỖI PHÁT HIỆN KHI LÀM: khuyến mại có thể LÀM GIÁ TĂNG

`bestPromotionFor()` trước đây chọn theo `priority` cao nhất. Đo được:

```
Hoa hồng đỏ Ecuador — giá gốc 650.000đ
  "Giáng sinh"        ưu tiên 10 -> 250.000đ
  "Xả hàng cuối ngày" ưu tiên 50 -> 455.000đ  (giảm 30%)
Kết quả cũ: trong khung "xả hàng", khách trả 455.000đ thay vì 250.000đ.
```

Gần như không xảy ra khi mỗi sản phẩm chỉ nằm trong một chương trình. Nhưng
giá theo khung giờ khiến **chồng chương trình thành chuyện bình thường**
(một chương trình mùa vụ chạy nền + một chương trình xả hàng buổi tối), nên
phải sửa cùng lúc.

**Nay chọn theo GIÁ THẤP NHẤT**, `priority` chuyển sang vai phá hoà (quyết
chương trình nào được hiện tên và banner). **Vẫn không cộng dồn** — hàm chọn
đúng một chương trình, đúng như quyết định cũ.

---

## QĐ-25 — Chấm điểm rủi ro đơn hàng (Fraud Detection)

### 1. Chấm điểm để CON NGƯỜI xem, không để máy chặn

Đây là quyết định quan trọng nhất của tính năng. Hệ thống gắn cờ và xếp đơn
nghi ngờ lên đầu; **quyết định gọi xác nhận hay từ chối là của người**. Tự
động chặn dựa trên vài dấu hiệu thống kê sẽ đuổi nhầm khách thật — và khách
bị từ chối oan thì không quay lại, còn cửa hàng không bao giờ biết mình vừa
mất ai. Không có ngưỡng "tự động huỷ" ở bất kỳ đâu trong mã.

### 2. Vì sao ngành hoa cần thứ này hơn ngành khác

Với hàng công nghiệp, shipper trả về kho là xong. Với hoa tươi thì bó hoa
đã cắt, đã bó, đã đi đường — không bán lại được cho ai. **Mỗi đơn bùng là
mất trắng toàn bộ giá vốn.**

### 3. `risk_flags` là bắt buộc, không phải trang trí

Một con số 60 trần trụi thì nhân viên không biết phải kiểm tra gì. Ghi rõ
"đơn COD giá trị cao" và "số này từng huỷ 3 đơn" thì họ biết cần hỏi gì khi
gọi.

### 4. Trọng số phản ánh mức độ chắc chắn của từng dấu hiệu

| Dấu hiệu | Điểm | Vì sao |
|---|---|---|
| Từng huỷ đơn | 20/lần, trần 40 | dấu hiệu **duy nhất** dựa trên hành vi đã xảy ra thật |
| COD giá trị cao | 25 | mất trắng giá vốn nếu bùng |
| Nhiều đơn cùng số/24h | 20 | nhưng có thể là thật (một dịp lễ, giao nhiều nơi) |
| Khách vãng lai | 10 | đặt không cần đăng nhập là tính năng cửa hàng **cố ý** mở |
| Không có email | 10 | mất một đường liên hệ khi giao thất bại |

Chuyển khoản giá trị cao **không** cộng điểm: tiền đã về, khách không nhận
cũng không mất giá vốn. Đã kiểm: đơn 2 triệu chuyển khoản = 0 điểm.

Trần 40 cho lịch sử huỷ: không kẹp thì khách huỷ mười đơn (có thể vì lý do
chính đáng) sẽ bị cờ đỏ vĩnh viễn.

### 5. Chụp tại thời điểm đặt, không tính lại

Điểm phản ánh những gì hệ thống biết **lúc đó**. Tính lại sau ba tháng cho
con số khác, và không ai còn đối chiếu được với quyết định đã làm.

Màu **cảnh báo (vàng)**, không phải nguy hiểm (đỏ): đỏ ở dự án này nghĩa là
"đã hỏng, phải sửa". Dùng đỏ thì nhân viên hoặc hoảng và từ chối oan, hoặc
quen mắt rồi bỏ qua cả cảnh báo đỏ thật ở chỗ khác.

---

## QĐ-26 — Credit Scoring: KHÔNG LÀM, và vì sao

Bạn đã tự nhận định đúng: *"ngành hoa & cây cảnh đa số là bán lẻ giá trị
vừa và nhỏ"*.

Chấm điểm tín dụng chỉ có nghĩa khi cửa hàng **cho nợ** — trả góp, công nợ
doanh nghiệp, hoặc giao trước thu tiền sau theo hợp đồng. Hiện hệ thống chỉ
có COD và chuyển khoản, cả hai đều **thu đủ tiền trước khi rời quyền kiểm
soát hàng hoá**. Không có khoản nợ nào để mà chấm điểm.

Dựng sẵn một hệ thống chấm điểm tín dụng lúc này là đoán mò hình dạng của
một nghiệp vụ chưa tồn tại — và nó sẽ được thiết kế sai, vì chưa ai biết
cửa hàng sẽ cho nợ theo điều kiện gì.

**Khi nào cần làm lại:** bán bonsai/cây cổ thụ hàng trăm triệu và mở trả
góp. Lúc đó cần thêm: hạn mức tín dụng, lịch trả, theo dõi quá hạn, và quy
trình thu hồi — bốn thứ độc lập với việc chấm điểm.

Phần đã làm được của bài toán này là `OrderRiskScorer` (QĐ-25): lịch sử huỷ
đơn theo `user_id` và theo số điện thoại chính là nền của một điểm tín dụng
nội bộ sau này.

---

## QĐ-27 — Chatbot: dọn chỗ, không dựng khung rỗng

Cố ý **không** đặt sẵn một ô chat lên giao diện. Ô chat trả lời "xin lỗi,
tôi chưa hiểu" cho mọi câu hỏi thì tệ hơn hẳn không có ô chat — nó tiêu mất
lần thử đầu tiên của khách, và lần đó không lấy lại được.

Cũng không viết sẵn interface/service rỗng: mã không ai gọi là mã chết, và
nó sẽ được thiết kế sai vì chưa biết chatbot thật cần gì.

"Dọn chỗ" đúng nghĩa ở giai đoạn này là **rà xem dữ liệu cần thiết đã có
chưa** — xem `docs/CHATBOT-PLAN.md`. Kết quả: **3/5 mảnh đã tồn tại và
đang chạy** (`ProductSearch`, `PlantAdvisor`, `care_info`), vì chúng được
xây cho các tính năng khác.

Hai việc đáng làm trước, tự nó đã có ích, không phụ thuộc AI:
- nhập đủ `care_info` cho mọi sản phẩm;
- thêm trục **"dịp"** (`TraitType::Occasion`) — hiện dịp nằm lẫn trong tên
  danh mục, xem QĐ-08.

---

## QĐ-28 — Chọn từng món trong giỏ để thanh toán

### 1. Vấn đề của giỏ hàng cũ

Thanh toán là lấy **sạch** giỏ. Khách để dành ba món chờ lương và muốn mua trước
một bó hoa sinh nhật thì phải xoá ba món kia, mua xong lại đi tìm và thêm lại.

### 2. Mặc định `is_selected = true`

Giữ nguyên hành vi cũ cho người không quan tâm: thêm hàng, bấm thanh toán, xong.
Mặc định `false` sẽ bắt **mọi** khách thêm một bước để phục vụ một thiểu số.

### 3. Lọc ở `CheckoutSource::cartBasket()`, không ở controller

Mọi nơi tính tiền đều đi qua đó (trang giỏ, trang thanh toán, lúc ghi đơn), nên
không có chỗ nào tính nhầm trên cả giỏ.

### 4. `clearSelected()` chứ không `clear()` sau khi đặt hàng

Đây là điểm mấu chốt. Đơn chỉ gồm món đã tích, nên chỉ được xoá đúng những món
đó — gọi `clear()` là cuốn sạch cả thứ khách cố ý để lại, mất dữ liệu không lấy
lại được. Đã kiểm: đặt 1/3 món → đơn có 1 dòng, giỏ còn 2.

### 5. Một biểu mẫu cho cả giỏ, không phải mỗi dòng một request

Ô đánh dấu **chỉ gửi lên những cái được tích**, nên "bỏ tích" không sinh request
nào. Nhận từng dòng một thì hệ thống không bao giờ biết khách vừa bỏ tích cái gì.
`setSelection()` ghi cả hai chiều trong một lượt.

Ô nằm trong `<div class="cart-line">` vốn đã có hai `<form>` (sửa số lượng, xoá),
mà HTML không cho lồng form — dùng thuộc tính `form=""` trỏ ra biểu mẫu ngoài,
đúng cách đã dùng cho ô nhập mã giảm giá.

Không JavaScript: tích xong bấm "Cập nhật lựa chọn". Có JavaScript: tự gửi, **và
nút bị gỡ đi** — để lại một nút bấm vào chẳng thay đổi gì là để lại một nút vô
nghĩa.

### 6. Phân biệt "giỏ trống" với "chưa tích món nào"

Hai tình huống khác nhau cho ra cùng một giỏ thanh toán rỗng. Báo chung một câu
thì khách nhìn giỏ đầy hàng và kết luận website hỏng.

---

## QĐ-29 — Tách "Vật tư chăm sóc" khỏi "Phụ kiện"

Phân bón trước đây nằm chung danh mục "Phụ kiện". Nhưng cái chậu mua một lần dùng
nhiều năm, còn gói phân bón hết là phải mua lại — **hai hành vi mua khác hẳn
nhau**.

| Danh mục | Bản chất | Ví dụ |
|---|---|---|
| Phụ kiện | đồ dùng **bền** | chậu, đĩa lót, bình tưới, kéo, bình cắm |
| Vật tư chăm sóc | thứ **tiêu hao** | phân bón, đất, viên đất nung, dưỡng hoa, thuốc |

Khác biệt đó có hệ quả thật: vật tư tiêu hao là nhóm bán lặp lại, đáng được nhắc
mua lại và theo dõi tồn kho chặt hơn. Trộn chung thì không phân tích riêng được.

`accessory_for = 'all'` cũng đã sửa: bình tưới và phân bón đổi sang
`pot/original/set` vì **bó hoa cắt cành không bón phân được**.

---

## QĐ-30 — Ba đợt sửa giao diện

### 1. Thông báo tự biến mất

"Đã cập nhật thông tin" nằm mãi trên đầu trang tới lần tải sau; khách sang trang
khác rồi quay lại vẫn thấy và bắt đầu tự hỏi vừa cập nhật cái gì.

Ba điều phải đúng:
- **Lỗi sống lâu hơn thành công** (9s vs 5s). "Đã lưu" đọc lướt là đủ; "Mật khẩu
  hiện tại không đúng" cần thời gian đọc và hiểu phải làm gì.
- **Rê chuột hoặc Tab vào thì dừng đếm**, rời ra đếm **lại từ đầu** — người đọc
  vừa quay lại, cho họ trọn thời gian.
- **Thanh thời gian** chạy bằng CSS animation. Không có nó thì thông báo đột ngột
  mất đi trông như trang bị lỗi.

`prefers-reduced-motion` chỉ **ẩn thanh**, không tắt tính năng: người bật tuỳ chọn
đó vẫn muốn thông báo tự dọn đi.

### 2. Nút tìm kiếm trông như một ô nhập thu gọn

Biểu tượng kính lúp trần lẫn vào hàng nút giỏ hàng/tài khoản, và không có gì để
đoán bấm vào sẽ ra cái gì. Nút hình viên thuốc (106×31px) có viền nhạt, nền
`surface-alt` và chữ "Tìm kiếm" thì mượn đúng hình dáng của `.form-control` — nhìn
là biết chỗ để gõ. Dưới 992px thu về icon (33px), `aria-label` giữ nguyên.

### 3. Thanh khuyến mại nói đủ ba thứ

Bản cũ chỉ có tên chương trình + "còn 7 ngày": nghe hay nhưng khách không biết
được giảm bao nhiêu nên không có lý do bấm.

Nay: **"Giảm đến 20%"** (con số thật, tính từ dữ liệu) → tên → khung giờ nếu có →
thời gian còn lại → **"Xem 2 sản phẩm"**.

- `headlineDiscount()` nói **"đến"** vì mỗi sản phẩm có thể có mức riêng; nói
  "giảm 30%" khi chỉ một món được 30% là hứa quá lời. Trả `null` với Combo /
  FixedPrice — không quy về một con số được thì không bịa.
- `endsInText()` nói đúng mức cấp bách: **giờ** khi còn dưới một ngày, "hôm nay là
  ngày cuối", chứ không phải "còn 0 ngày".
- **Đóng được**, nhớ ở `localStorage`. Khoá gồm slug **và** ngày kết thúc nên
  chương trình gia hạn là thanh hiện lại. Thanh mặc định `hidden`, JS mới mở —
  ngược lại sẽ nhấp nháy một cái ở mỗi lần tải trang với người đã đóng.

---

## QĐ-31 — Trục thứ tư: HÀNG CHÍNH hay HÀNG PHỤ TRỢ

### 1. Vấn đề

Trang chủ sắp theo "mới nhất", nên năm gói vật tư seed sau cùng đẩy hết hoa
xuống dưới. Khách mở một trang bán hoa và thấy đầu tiên là **"Kéo cắt cành mũi
cong"** — đúng kỹ thuật, sai hoàn toàn về nghiệp vụ.

Đây là cửa hàng hoa và cây cảnh. Chậu, đất, phân bón đều cần, nhưng không ai vào
đây để mua một gói đất — họ mua đất **vì** vừa mua một cái cây.

### 2. `categories.kind` không trùng với trục nào đã có

| Trục | Trả lời câu | Ví dụ |
|---|---|---|
| `category` | nhóm hàng để duyệt | "Hoa cưới", "Sen đá" |
| `selling_form` | hàng ở dạng gì | bó / chậu / giỏ |
| `product_type` | bản chất sinh học | hoa / cây / khác |
| **`kind`** | **vai trò trong cửa hàng** | **chính / phụ trợ** |

Ba trục đầu đều không trả lời được *"thứ này có đáng lên trang chủ không"*.

### 3. Mặc định `plant`, và `NULL` cũng tính là `plant`

Mọi danh mục có trước migration đều là hoa và cây cảnh. Mặc định ngược lại thì
toàn bộ catalog biến mất khỏi trang chủ cho tới khi ai đó sửa tay từng danh mục.

### 4. Ranh giới áp dụng

`mainCatalog()` dùng ở **mọi nơi trưng hàng cho khách duyệt** — trang chủ, trang
sản phẩm, gợi ý cá nhân hoá. **KHÔNG** dùng ở khối "mua kèm": chỗ đó tồn tại đúng
để bán vật tư, và khách ở đó **đã** chọn cây.

Trang `/phu-kien` cố ý đơn giản hơn: không lọc phong thuỷ, không lọc độ khó, mặc
định sắp theo **giá tăng dần** chứ không phải "mới nhất" — người mua vật tư đã
biết mình cần gì và quan tâm giá, không cần được tư vấn.

---

## QĐ-32 — Trang sự kiện (landing page)

Nút cũ **"Xem 2 sản phẩm"** dẫn tới `/san-pham?promotion=slug` — đúng hàng nhưng
**không có gì của sự kiện**. Khách bấm vào banner Giáng sinh rồi rơi vào một lưới
sản phẩm bình thường: không biết chương trình là gì, giảm bao nhiêu, tới bao giờ,
hay có mã nào để lấy.

`/su-kien/{slug}` gom đủ ba thứ một sự kiện phải có: **bối cảnh** (tên, mô tả,
banner, thời gian, mức giảm) + **hàng** + **mã riêng của sự kiện**.

- Màu khối đầu trang lấy từ `promotions.theme_key`, nên trang Giáng sinh khác
  trang Tết mà **không cần CSS riêng cho từng dịp** — thêm chương trình mới chỉ
  là thêm một dòng dữ liệu.
- Banner làm **nền mờ 28%**, không phải nội dung: ảnh admin tải lên có đủ mọi
  tỉ lệ và độ sáng, phủ tối là cách duy nhất bảo đảm chữ luôn đọc được.
- **Vẫn mở được sau khi chương trình kết thúc** — link đã chia sẻ khắp nơi, chết
  link là mất khách. Trang nói rõ "đã kết thúc" ngay dòng đầu. Chỉ chương trình
  còn **nháp** mới trả 404.

`coupons.promotion_id` + `whereNull('promotion_id')` ở `claimableFor()`: mã của
sự kiện **chỉ** phát trong trang sự kiện. Để nó hiện cả ở trang Voucher chung thì
trang sự kiện mất luôn thứ duy nhất chỉ nó mới có. Mã **đã lưu** vào ví vẫn hiện
bình thường ở khối "Ví của tôi" — lúc đó nó đã là tài sản của khách.

---

## QĐ-33 — Điều kiện voucher: trang riêng, không popup

Thẻ voucher chỉ đủ chỗ cho mức giảm và một dòng điều kiện. Mã thật luôn có cả
danh sách: hạn dùng, đơn tối thiểu, giảm tối đa, giới hạn mỗi tài khoản, hình
thức thanh toán, phạm vi sản phẩm. Nhét hết vào thẻ thì không đọc được thẻ nào;
giấu hết đi thì khách bị từ chối ở bước thanh toán mà không hiểu vì sao.

**Trang riêng, không popup JavaScript:** nội dung dài, cần cuộn, cần chia sẻ được
bằng đường dẫn, và phải đọc được cả khi JavaScript hỏng. Đây là điều khoản — thứ
không được phép biến mất.

**Công khai, không cần đăng nhập:** khách phải đọc được điều kiện **trước** khi
quyết định lưu. Mã nội bộ (`is_public = false`) trả 404 — trang này sẽ biến mọi
mã nội bộ thành mã ai cũng dò ra được bằng cách gõ mã vào URL.

**Mỗi mục chỉ hiện khi có dữ liệu thật.** Mã không giới hạn hình thức thanh toán
thì không in ra dòng "áp dụng mọi hình thức" — chữ thừa làm loãng mấy điều kiện
thật sự quan trọng.

### `payment_methods` được CHẶN THẬT, không chỉ ghi cho có

Kiểm ở **lúc đặt hàng**, không phải lúc áp mã: khách áp mã ở bước 2 khi chưa chắc
đã chọn xong hình thức thanh toán, chặn ở đó là chặn oan. Đo được: mã chỉ cho
chuyển khoản + chọn COD → *"Mã NOELEVENT chỉ áp dụng khi thanh toán bằng: Chuyển
khoản ngân hàng"*, và bị đưa về **bước 2** (nơi có cả hai ô) chứ không về giỏ.

Điều kiện ghi trên giấy mà không ai kiểm thì tệ hơn không ghi: khách đọc "chỉ áp
dụng khi chuyển khoản", chọn COD, vẫn được giảm — lần sau họ không tin bất cứ
điều kiện nào nữa.

---

## QĐ-34 — Thanh điều hướng: ô tìm kiếm cố định + menu "Khác"

### Ô tìm kiếm bỏ hẳn cơ chế bung ra

Bản trước bung ra phủ ngang cả thanh header. Hai vấn đề đo được:
- **lúc đóng:** nút 106px nằm lệch bên phải, giữa thanh còn một khoảng trống lớn;
- **lúc mở:** ô nhập 1121px cho một dòng chữ ngắn, che sạch logo lẫn menu.

Nay ô nhập nằm sẵn, **260px** (280px trên màn hình ≥1400px), bo tròn, kính lúp
nằm hẳn bên trong lề trái, **không có nút "Tìm" rời** (Enter là đủ, tiết kiệm
~70px cho chính ô nhập).

Đo lại sau khi sửa: logo 16–175, menu 207–723, ô tìm kiếm 739–999 — **không chồng
lấn, không tràn ngang, menu một dòng**.

Danh sách gợi ý rộng **352px** (rộng hơn ô nhập) và neo **mép phải**: mỗi dòng có
ảnh 40px + tên + danh mục + giá, ép vào 260px thì tên nào cũng bị cắt.

**Dưới 992px ẩn hẳn** — thanh header ở khổ đó phải nhường chỗ cho menu, và trang
danh sách sản phẩm đã có ô tìm kiếm riêng trong bộ lọc.

### Menu "Khác"

Năm liên kết ngang hàng, nay thêm "Phụ kiện & vật tư" nữa là sáu — không đủ chỗ.
Gom **hai mục ít dùng nhất** (Phụ kiện & vật tư, Sự kiện & số lượng lớn) cùng
Voucher vào một menu. Bốn mục còn lại là đường đi chính của phần lớn khách nên
giữ phẳng — giấu chúng sau một lần bấm là làm chậm mọi người để tiết kiệm chỗ cho
thiểu số.

---

## QĐ-35 — Hai lỗi tính tiền và hiển thị sai sự thật

### 1. Giỏ hàng và thanh toán ra hai con số khác nhau

Tái hiện được đúng con số bạn báo:
1. thêm "Đất trồng 5kg" (55.000đ) vào giỏ;
2. bấm **"Mua ngay"** ở "Bó tulip Hà Lan" (520.000đ);
3. quay lại trang giỏ hàng.

→ Giỏ hiện **105.000đ**, bấm "Tiến hành thanh toán" thì trang sau hiện
**520.000đ**. Cùng một nút, hai con số — và khác về **tiền**.

Nguyên nhân: trang giỏ đọc `cartBasket()` (chỉ giỏ), trang thanh toán đọc
`basket()` — hàm này **ưu tiên phiên "mua ngay"** nếu đang có. Phiên đó sống
trong session và không có gì dọn nó.

Sửa: **mở trang giỏ hàng = huỷ lượt "Mua ngay"**, và **nói ra**. Huỷ im lặng thì
khách quay lại trang thanh toán và không hiểu vì sao món vừa bấm mua biến mất.
Thêm loại thông báo `info` (trung tính) cho đúng việc này — nhét vào `success`
đọc như lời khen cho việc khách không làm, nhét vào `error` làm họ hoảng.

### 2. "Được yêu thích gần đây" chưa bao giờ nhìn vào wishlist

Khối này mang tiêu đề "Được yêu thích gần đây" nhưng truy vấn chỉ là
`latest()->take(8)` — **hàng mới nhất**, không hề đụng bảng `wishlists`. Không ai
thích sản phẩm nào mà trang chủ vẫn khẳng định là "được yêu thích", và đứng đầu
lại là mấy gói vật tư seed sau cùng.

Nay đếm thật từ `wishlists`. Chưa ai thích gì thì **không bịa**: trả về hàng mới
nhất và **đổi luôn tiêu đề** thành "Hàng mới về" — nói đúng thứ đang hiện.

---

## QĐ-36 — Lỗi component icon: hai thuộc tính `class`

`icon.blade.php` viết `<svg class="icon" ... {{ $attributes }}>`. Khi nơi gọi
truyền thêm class, thẻ `<svg>` có **hai** thuộc tính `class`. HTML quy định
thuộc tính trùng thì lấy **cái đầu tiên** — nên class của nơi gọi bị vứt đi
hoàn toàn, **âm thầm, không lỗi, không cảnh báo**.

Hậu quả nhìn thấy được: kính lúp trong ô tìm kiếm mất `.header-search__icon`
nên mất luôn `position: absolute`, rơi ra ngoài khung và nằm lệch hẳn lên trên.

Bug ảnh hưởng **10 chỗ** dùng icon có class trong dự án — mấy chỗ dùng flex
thì trông vẫn tạm ổn nên không ai để ý. Sửa một dòng bằng
`$attributes->merge(['class' => 'icon'])`.

---

## QĐ-37 — Bốn nhu cầu thành bốn trang hướng dẫn

Bốn thẻ "Chọn theo nhu cầu" ở trang chủ trước đây chỉ là liên kết tới
`/san-pham?selling_form=bouquet`. Khách bấm **"Người mới bắt đầu trồng cây"**
và rơi vào một lưới sản phẩm đã lọc — đúng hàng, nhưng không trả lời câu hỏi
thật của họ: *bắt đầu từ đâu, cần mua thêm gì, chăm thế nào để cây không chết
trong hai tuần*.

Nay mỗi nhu cầu là `/nhu-cau/{slug}` với bốn khối: **dẫn nhập → hướng dẫn →
hàng gợi ý → dụng cụ mua kèm**.

**Hướng dẫn đi TRƯỚC lưới hàng.** Khách bấm vào đây vì *chưa biết* chọn gì —
biết rồi thì đã vào thẳng trang sản phẩm. Đưa lưới hàng lên trước là trả lời
câu hỏi họ chưa kịp hỏi.

**Mỗi nhu cầu một tiêu chí lọc khác hẳn**, và đó là lý do có trang riêng thay
vì một bộ lọc dùng chung:

| Nhu cầu | Lọc theo |
|---|---|
| Quà tặng | hình thức đã gói sẵn (bó, hộp, giỏ, set) |
| Trang trí | nhãn **vị trí đặt** trong nhà — không phải hình thức bán, vì bonsai sân vườn cũng là "cây chậu" |
| Sự kiện | lẵng/kệ + danh mục hoa sự kiện |
| Người mới | dùng lại nguyên `PlantAdvisor` với độ khó Dễ |

**Nội dung hướng dẫn nằm trong Blade, không trong CSDL:** đây là bài viết có
cấu trúc, không phải dữ liệu để lọc hay đếm. Nhét vào bảng thì admin sửa được
cái tên nhưng không sửa được cái quan trọng, còn người đọc mã phải mở hai chỗ
mới hiểu một trang.

---

## QĐ-38 — Gộp bước 1 và 2 của thanh toán

### Vì sao gộp

Phí giao phụ thuộc **TỈNH**, mà tỉnh nhập ở bước 1 — nên tổng tiền chỉ đúng
từ bước 2 trở đi. **Khách điền xong bước 1 vẫn chưa biết mình phải trả bao
nhiêu**, và đó chính là lúc nhiều người bỏ giỏ hàng.

Ba bước → hai bước: *Thông tin & thanh toán* → *Xác nhận*.

Đường dẫn cũ `/thanh-toan/van-chuyen` trả **301** về bước 1 chứ không 404:
khách có thể còn tab đang mở hoặc đã lưu dấu trang.

### Tự áp mã giảm giá tốt nhất

`BestCouponFinder` duyệt mã trong ví + mã công khai đang chạy, tính bằng
chính `CouponService` (không viết lại phép tính), chọn mã cho số tiền giảm
lớn nhất.

**Ba ràng buộc, quan trọng hơn cả thuật toán:**

1. **KHÔNG BAO GIỜ ghi đè lựa chọn của khách.** Cờ `checkout.coupon_auto`
   phân biệt mã hệ thống chọn với mã khách chọn. Khách tự chọn một mã giảm ít
   hơn thì đó là quyết định của họ — có thể họ giữ mã kia cho đơn sau, hoặc
   mã kia sắp hết hạn. Hệ thống không biết, và không được đoán.
   Đã kiểm: chọn CHAOBAN thủ công → tải lại 4 lần → **vẫn là CHAOBAN**.
2. **Chỉ xét mã khách có quyền dùng.** Mã nội bộ chưa lưu vào ví thì không
   tự áp — cửa hàng gửi riêng cho một người, tự áp cho tất cả là phát nhầm.
3. **Chạy lại mỗi lần mở trang**, vì giỏ có thể vừa đổi và mã tốt nhất đổi theo.

Giao diện **nói rõ** mã do hệ thống chọn (*"tự chọn giúp bạn"*). Tự áp mà
không nói là đổi số tiền sau lưng khách.

### Tóm tắt đơn chi tiết

Liệt kê từng món (**tên × số lượng — đơn giá = thành tiền**) — chỉ ghi thành
tiền thì khách không kiểm tra được đơn giá có đúng với giá đã thấy trên trang
sản phẩm.

**Phí giao luôn in con số gốc, kể cả khi được miễn**, rồi thêm một dòng **âm**
ngay bên dưới:

```
Tạm tính (3 sản phẩm đã chọn)        1.220.000đ
Mã NOEL2026 · tự chọn giúp bạn        −183.000đ
Phí giao hàng                           50.000đ
Miễn phí giao hàng (đơn từ 500.000đ)   −50.000đ
Tổng thanh toán                      1.037.000đ
```

Chỉ in "Miễn phí" thì khách không biết mình vừa được miễn bao nhiêu, và các
dòng không cộng lại đúng bằng dòng tổng. Đây là cách mọi trang thương mại
điện tử thật vẫn làm.

Danh sách từng món **chỉ bật ở bước thanh toán** (`:itemized="true"`): trang
giỏ hàng đã liệt kê hàng ngay bên trái, lặp lại là thừa.

---

## QĐ-39 — "Được yêu thích gần đây" là khối riêng, dựng từ dữ liệu thật

Tách khỏi khối nổi bật đầu trang (nay là **"Hàng mới về"**, nói đúng thứ nó
hiện). Đặt **sau** khối gợi ý cá nhân hoá: gợi ý nói về *riêng* khách đang
xem, khối này nói về *cả cửa hàng* — đi từ hẹp ra rộng.

**"Gần đây" hiểu đúng nghĩa:** xếp theo `MAX(wishlists.created_at)`, không
phải theo tổng số lượt thích. Một sản phẩm được 50 người thích từ năm ngoái
không còn là tin tức; một sản phẩm được 3 người thích sáng nay thì có.

**Khối biến mất hoàn toàn khi chưa ai thích gì.** Đây là chỗ dễ bịa nhất trên
cả trang chủ — rất dễ đổ đại vài sản phẩm vào cho đỡ trống, và khách sẽ tin
rằng chúng được yêu thích thật. Đã kiểm: xoá hết wishlist → khối biến mất.

---

## QĐ-40 — Tải trước trang thay vì đổi sang SPA

Xem `docs/HIEU-NANG.md` cho toàn bộ số đo. Tóm tắt:

- PHP dựng trang mất **47–58ms**; trình duyệt nhận được sau **250–350ms**.
- 6 request song song / tuần tự = tỉ lệ **0.89** → `php artisan serve` xử lý
  gần như một request một lúc, trong khi mỗi trang cần **28 tệp con**.
- OPcache **tắt**. `config:cache` + `route:cache` + `view:cache` **gần như
  không đổi gì** → nút thắt không nằm ở mã ứng dụng.

Thêm **Speculation Rules API** — chuẩn của trình duyệt, không phải thư viện.
Rê chuột dừng trên liên kết ~200ms là trình duyệt tải sẵn trang đó.

- `eagerness: "moderate"` chứ không `eager`: `eager` tải trước **mọi** liên
  kết ngay khi trang hiện ra — 12 lượt tải cho một trang danh sách mà khách
  chỉ bấm một, trên máy chủ một luồng là tự làm chậm chính mình.
- `prefetch` chứ không `prerender`: `prerender` chạy luôn JavaScript của
  trang đích, nghĩa là mỗi lần rê chuột là một lượt ghi `product_view` và
  `view_count` tăng cho sản phẩm khách chưa hề mở — số liệu phân tích sẽ sai.

**KHÔNG đổi sang SPA.** PHP chỉ mất 50ms; React không sửa được máy chủ dev
một luồng lẫn OPcache tắt, mà đổi lại là mất nút Back, mất URL chia sẻ được,
mất khả năng chạy khi tắt JavaScript.

---

## QĐ-41 — Gộp logic tải ảnh Openverse vào một nơi

Nay có **hai** script tải ảnh (sản phẩm và danh mục). Luật giấy phép, danh
sách từ khoá cấm và cách kiểm tra tệp tải về là **một**, nên chỉ được có một
bản — chép sang tệp thứ hai thì sớm muộn hai bản sẽ lệch, và lệch ở đây nghĩa
là một nhánh âm thầm nhận ảnh **không đủ điều kiện dùng thương mại**.

`tools/lib/openverse.mjs` giữ phần chung; `fetch-product-photos.mjs` rút từ
**239 → 87 dòng**.

**Tham số `shape` là điểm khác thật giữa hai nơi:** thẻ sản phẩm là khung
**đứng 4:5** nên ảnh panorama bị cắt cụt hai đầu; thẻ danh mục là khung
**ngang** nên ảnh dọc mới là thứ bị cắt. Lọc từ phía Openverse rẻ hơn nhiều
so với tải về rồi mới phát hiện.

`categories:link-photos` kiểm tra **tệp có thật** trước khi ghi vào CSDL —
`credits.json` do script Node ghi ra, tệp ảnh có thể đã bị xoá tay sau đó, và
một đường dẫn chết cho ra ô ảnh vỡ, tệ hơn hẳn hình lá giữ chỗ.

---

## QĐ-42 — Sắp xếp lại trang Thông tin & thanh toán

### 1. Ba khối có đánh số, thay cho một cột dài

Sau khi gộp hai bước, cột trái là một biểu mẫu **liền mạch cao 1184px** với
bốn tiêu đề trôi nổi. Khách cuộn qua mà không biết còn bao nhiêu việc nữa mới
xong.

Nay ba thẻ có số thứ tự: **Giao đến đâu → Giao khi nào → Thanh toán thế nào**.

Số thứ tự dùng thẻ `<span>` chứ **không** dùng CSS counter: counter đếm theo
thứ tự xuất hiện trong DOM, mà khối sổ địa chỉ ẩn/hiện tuỳ khách đã đăng nhập
hay chưa — số sẽ nhảy.

Khối "Giao khi nào" mang nhãn **"không bắt buộc"** ngay trên tiêu đề: khách
nhìn thấy thì lướt qua được mà không áy náy, thay vì dừng lại nghĩ xem có phải
điền không.

### 2. Mã giảm giá chuyển sang cột phải

Trước đây nó kẹp giữa "Thời gian giao" và "Hình thức thanh toán" — **cách xa
con số mà nó thay đổi hơn nửa màn hình**. Khách áp mã rồi phải đi tìm xem tổng
tiền có đổi không.

Đặt ngay trên bảng tiền thì áp mã xong nhìn xuống là thấy. Đây cũng là chỗ mọi
trang thương mại điện tử thật đặt nó.

### 3. Thanh tổng tiền dính đáy — chỉ trên điện thoại

Trên màn hình rộng, bảng tóm tắt ở cột phải luôn nhìn thấy được. Trên điện
thoại hai cột **xếp chồng**: biểu mẫu trước, tóm tắt sau — nghĩa là nút gửi
nằm **TRÊN** bảng tiền, và khách bấm "Xem lại đơn hàng" trước khi kịp nhìn
thấy mình phải trả bao nhiêu.

Thanh dính đáy lặp lại tổng tiền và nút gửi, luôn trong tầm mắt. Nút dùng
`form="checkout-details-form"` nên bấm ở đây hay bấm nút trong biểu mẫu đều
như nhau — không nhân đôi biểu mẫu.

`padding-bottom: 72px` trên `.checkout-layout` (thanh cao 67px) để nó không
che nút cuối trang. Đặt trên layout chứ không trên `body`: chỉ trang này có
thanh đó.

---

## QĐ-43 — Dữ liệu mẫu: thêm hàng phải thêm cả thông tin

Bốn danh mục chỉ có **đúng một** sản phẩm (Cây để bàn, Bonsai, Hoa cưới, Sen
đá). Với một sản phẩm thì không kiểm được lưới hiển thị, không kiểm được phân
trang, không kiểm được sắp xếp theo giá — và khách bấm vào danh mục thấy một
món lẻ loi thì nghĩ cửa hàng sắp đóng.

Thêm **13 sản phẩm**, mỗi sản phẩm có **đủ**: mô tả ngắn + mô tả dài, `care_info`
đúng hồ sơ của hình thức bán (Guide §4.4), chu kỳ tưới/bón để lịch nhắc chăm
cây chạy được, và nhãn vị trí đặt / mệnh **chỉ khi có căn cứ**.

Sản phẩm thiếu `care_info` thì trang chi tiết trống một nửa và tính năng nhắc
lịch không có gì để chạy. **Thêm hàng mà không thêm thông tin là làm đầy con số
chứ không làm đầy nội dung.**

`stock = null` nghĩa là **hàng làm theo đơn** → `track_inventory = false`, chứ
không phải để số 0. Hoa cưới và hoa sự kiện đều thuộc nhóm này: cửa hàng làm
khi có đơn nên không có khái niệm "còn mấy cái", trong khi **0 nghĩa là hết
hàng** — hai chuyện khác hẳn nhau.

---

## QĐ-44 — Nút "Bỏ mã" phải thật sự bỏ được mã

### Lỗi

`removeCoupon()` gọi `clearCoupon()`, mà hàm đó xoá **cả** `checkout.coupon`
lẫn `checkout.coupon_auto`. Ngay sau đó session trông y hệt lúc khách chưa
có mã nào, nên lần dựng trang kế tiếp `autoApplyBestCoupon()` áp lại đúng
cái mã vừa bỏ.

Nút "Bỏ mã" vì thế **không bao giờ** hoạt động — bấm bao nhiêu lần mã vẫn
nằm nguyên đó. Đo được: bấm 3 lần liên tiếp, cả 3 lần mã `NOEL2026` vẫn ở
lại.

### Vì sao phải thêm một khoá session nữa

Không suy ra được từ hai khoá cũ. "Chưa có mã" và "đã có mã rồi bỏ đi" cho
ra cùng một trạng thái session, nhưng là **hai ý định khác hẳn nhau**:
trường hợp đầu thì tự chọn giúp là có ích, trường hợp sau thì tự chọn giúp
là cãi lại khách.

`checkout.coupon_declined` ghi lại ý định đó. Nó mất khi khách tự áp một mã
khác, khi họ bấm "Chọn giúp tôi", hoặc khi đơn đã đặt xong — lời từ chối
chỉ có giá trị cho **đơn đang làm dở**, không phải cho cả phiên.

`clearCoupon()` **không** đụng tới cờ này: hàm đó còn được gọi lúc hệ thống
dọn dẹp (mã hết hạn, giỏ tụt xuống dưới mức tối thiểu). Gộp hai chuyện lại
thì một mã hết hạn cũng tắt luôn tính năng tự chọn mã.

### Đã tắt thì phải cho bật lại

Thêm `POST /thanh-toan/ma-giam-gia/tu-chon` và nút "Chọn giúp tôi". Cho
khách tắt một thứ mà không cho bật lại là nhốt họ trong lựa chọn của chính
mình cho tới hết phiên.

---

## QĐ-45 — Nút mã giảm giá thuộc biểu mẫu thông tin người nhận

### Lỗi

Ô mã giảm giá là **biểu mẫu riêng**. Khách điền xong tên, số điện thoại,
địa chỉ rồi bấm "Bỏ mã" thì trang tải lại **trắng trơn** — vì `back()`
không mang theo gì, còn `$values` chỉ có dữ liệu đã lưu vào session ở lần
bấm "Xem lại đơn hàng" trước đó. Đúng cảm giác đơn hàng vừa bị huỷ.

### Cách sửa: `formaction`, không phải biểu mẫu lồng nhau

Mọi ô và nút mã giảm giá nay mang `form="checkout-details-form"` và đổi
đích bằng `formaction`. Cả biểu mẫu được gửi đi, controller trả lại qua
`->withInput()`, `old()` dựng lại đúng những gì khách đang gõ.

Ba chi tiết bắt buộc:

- **`formnovalidate` trên cả ba nút.** Chúng không phải "gửi đơn", nên
  không được đòi điền đủ mới cho bấm.
- **`name="_method" value="DELETE"` đặt TRÊN NÚT**, không phải một ô ẩn.
  Ô ẩn nằm trong biểu mẫu thì nút "Xem lại đơn hàng" cũng gửi kèm, biến
  việc đặt hàng thành một request DELETE. Giá trị của nút chỉ được gửi khi
  chính nó được bấm.
- **`draft()` bỏ `_token` và `_method`** khỏi phần giữ lại — đó là thứ của
  HTTP, không phải của khách.

### Hệ quả: phím Enter

Ô nhập mã nay thuộc biểu mẫu chính, nên gõ mã rồi nhấn Enter là trình duyệt
bấm hộ nút mặc định — **"Xem lại đơn hàng"**. Bỏ qua ô đó thì khách sang
trang xác nhận và thấy mình không được giảm gì.

`storeDetails()` vì thế áp luôn mã đang có trong ô. Mã sai thì quay lại nói
rõ là sai, chứ không lặng lẽ đi tiếp.

---

## QĐ-46 — Danh sách chọn mã hiện cả mã chưa dùng được

Bản trước là một hàng chip nhỏ, đã **lọc bỏ** mã hết hạn / hết lượt / hết
suất. Lý do khi đó: "hiện một nút mà bấm chắc chắn ra lỗi thì thà đừng
hiện."

Sai ở chỗ khách **lưu mã xong tới đây không thấy nó đâu**, và kết luận là
hệ thống nuốt mất mã của mình. Im lặng không phải là gọn gàng.

Nay hiện đủ, kèm lý do, mã dùng được xếp lên trước. Nút bị `disabled` chứ
không bị ẩn, và dòng lý do là thông tin có ích: *"Mã này chỉ áp dụng cho
đơn từ 300.000đ"* cho khách biết mua thêm chút nữa là được giảm.

**Lý do lấy từ `CouponService::reasonUnusable()`**, không tự so sánh lại
trong controller. Nếu danh sách tự viết mấy phép kiểm tra riêng thì sớm
muộn hai bản sẽ lệch — và lệch kiểu này nghĩa là danh sách bảo "dùng được"
còn nút bấm trả về lỗi, hoặc tệ hơn, ngược lại. Đã tách `check()` ra khỏi
`resolve()` để hai nơi dùng chung đúng một bộ luật.

**Ô nhập tay và danh sách dùng hai tên khác nhau** (`coupon_code` và
`wallet_code`). Cả hai cùng nằm trong một biểu mẫu, nên nếu cùng tên thì
PHP lấy ô đứng sau — bấm một mã trong danh sách có thể hoá thành áp cái mã
đang gõ dở ở ô trên, tuỳ thứ tự thẻ trong trang.

**Ô nhập tay luôn hiện**, kể cả khi đang có mã. Bản trước giấu nó sau khi
áp mã, nên muốn đổi sang mã khác phải bỏ mã cũ rồi mới gõ được mã mới —
hai lần tải trang cho một việc.

---

## QĐ-47 — Danh sách chọn mã phải khớp với thứ hệ thống tự chọn

### Lỗi

Ví trống, trang ghi **"Ví voucher đang trống"**, nhưng bấm "Chọn giúp tôi"
vẫn có mã được áp.

Hai bộ mã khác nhau ở hai chỗ:

| | Xét những mã nào |
|---|---|
| Danh sách trên trang | chỉ mã **trong ví** |
| `autoApplyBestCoupon()` | mã trong ví **+ mã công khai đang chạy** |

Với khách thì đó là hệ thống tự bịa ra mã: một con số giảm giá xuất hiện
trong tổng tiền, mang tên một mã họ chưa từng nghe, và danh sách ngay bên
cạnh nói rằng họ không có mã nào.

### Sửa: cho danh sách hiện đúng bộ mã đó

`couponChoices()` gộp `forUser()` (ví) với `claimableFor()` (mã cửa hàng
đang mở). Mỗi dòng nói rõ nguồn: **"Trong ví của bạn"** hay **"Cửa hàng
đang mở"** — không thì khách thấy một mã lạ và không biết vì sao mình có.

Câu rỗng cũng phải sửa: **"Chưa có mã nào dùng được cho đơn này"**, chứ
không phải "ví đang trống". Ví trống mà cửa hàng vẫn đang mở mã thì khách
vẫn được giảm — nói về cái ví là sai trọng tâm.

Thứ tự sắp xếp lặp lại đúng thứ tự ưu tiên của `BestCouponFinder` (dùng
được trước, rồi ví trước mã công khai), nên đọc danh sách là biết hệ thống
sẽ chọn cái nào.

---

## QĐ-48 — Không tự áp mã của sự kiện khi khách chưa lưu về ví

Tìm ra khi truy lỗi QĐ-47. Giỏ 450.000đ, ví rỗng, khách **chưa từng mở
trang sự kiện** → hệ thống tự áp `NOELEVENT`.

`BestCouponFinder::candidates()` lọc theo `is_public`, mà mã sự kiện cũng
`is_public = true`. Trong khi đó `CouponWallet::claimableFor()` đã cố ý
**giấu** mã sự kiện khỏi trang voucher chung (QĐ trước: trang sự kiện phải
có thứ chỉ nó mới có — đấy là lý do khách chịu bấm vào banner).

Hai lớp cùng trả lời câu "khách được dùng mã nào", và chúng trả lời khác
nhau. Hậu quả kép:

1. **Trang sự kiện mất lý do tồn tại** — mã của nó được phát cho tất cả.
2. **Khách thấy một mã không tìm được ở đâu** trong giao diện: không có
   trên `/voucher`, không có trong ví, chỉ hiện trong tổng tiền.

Nay `candidates()` thêm `whereNull('promotion_id')` cho nhánh công khai.
Mã sự kiện **đã lưu vào ví** thì vẫn được xét bình thường — lúc đó nó là
tài sản của khách, và đo được: lưu `NOELEVENT` về ví xong, đơn 450.000đ tự
chọn nó (giảm 80.000) thay vì `NOEL2026` (15% = 67.500).

**Bài học:** mỗi lần thêm một lớp trả lời cùng một câu hỏi nghiệp vụ là
thêm một cơ hội để hai câu trả lời lệch nhau. Ở đây `claimableFor()` và
`candidates()` lẽ ra phải dùng chung một điều kiện ngay từ đầu.

---

## QĐ-49 — Con số trên nhãn không được đọc thành "mã của tôi"

Sau QĐ-47, danh sách gộp mã trong ví với mã cửa hàng đang mở. Đúng dữ liệu,
nhưng nhãn đóng lại ghi **"Chọn mã giảm giá · 2/2 dùng được"** trong khi ví
rỗng — và người đọc hiểu con số đó là "tôi đang có 2 mã".

Sửa đúng chỗ hiểu nhầm, không sửa dữ liệu:

1. **Câu mở đầu ngay khi bung ra:** *"Gồm mã trong ví của bạn và mã cửa
   hàng đang mở cho mọi khách."* Một dòng, luôn đúng, trả lời trước khi
   khách kịp thắc mắc.
2. **Chia theo nguồn, tiêu đề đặt trên mỗi nhóm** — thay cho nhãn nhỏ lặp
   lại ở từng dòng. Ví rỗng thì nhóm "Trong ví của bạn" **không xuất hiện**,
   nên không còn gì mâu thuẫn để khách phải đoán.
3. **Thứ tự đổi theo:** nhóm trước (ví → cửa hàng), trong mỗi nhóm mới xếp
   mã dùng được lên đầu. Vẫn là thứ tự ưu tiên của `BestCouponFinder` khi
   hoà điểm, nên đọc danh sách vẫn ra đúng thứ hệ thống sẽ chọn.

**Giới hạn chiều cao chuyển lên cả khối `<details>`**, không đặt trên từng
nhóm: kẹp trên từng nhóm thì mỗi nhóm cuộn riêng, còn khó dùng hơn không
kẹp. Nhãn bấm để mở dùng `position: sticky` để đứng yên khi cuộn phần bên
dưới. Đo với 22 mã: khối dừng ở 352px và cuộn được, nhãn không trôi.

**Ghi lại để nhớ:** dữ liệu đúng mà chữ trên màn hình gây hiểu sai thì vẫn
là lỗi. Lần này lỗi được phát hiện bởi người dùng chứ không phải bởi tôi,
vì tôi chỉ kiểm "mã nào được hiện" mà không đọc lại **câu chữ khách nhìn
thấy** khi ví rỗng.

---

## QĐ-50 — Bộ kiểm thử bắt đầu từ luồng thanh toán

Dự án chạy tới đây với **0 bài kiểm tra**. Việc đó được hoãn nhiều lần
theo đúng yêu cầu ("làm chức năng trước"), nhưng cái giá đã hiện ra: **ba
lỗi liên tiếp rơi vào cùng một khu vực** — nút "Bỏ mã", dữ liệu biểu mẫu
bị mất, danh sách mã lệch với thứ hệ thống tự chọn. Cả ba đều là loại lỗi
mà một bài kiểm tra bắt được ngay.

**Bắt đầu từ luồng thanh toán, không phải từ chỗ dễ nhất.** Thứ tự viết
kiểm thử đi theo *thiệt hại khi sai*, không theo thứ tự tính năng được
làm. Đây là nơi tiền đi qua.

### Ba nguyên tắc

1. **Đi qua HTTP, không gọi thẳng service.** Cả ba lỗi đều nằm ở chỗ ghép
   nối — biểu mẫu gửi đi đâu, session còn gì sau khi chuyển hướng. Gọi
   thẳng service thì cả ba vẫn xanh.

2. **Đọc session, không dò lớp CSS.** Bài kiểm tra hỏng mỗi lần đổi giao
   diện là bài kiểm tra sẽ bị bỏ qua. Ngoại lệ: bài đang kiểm chính *câu
   chữ khách nhìn thấy* thì phải dò chuỗi — QĐ-49 cho thấy chữ sai cũng
   là lỗi.

3. **Bài kiểm tra phải tự chứng minh mình có tác dụng.** Viết xong
   `CouponRemovalTest`, tôi cố ý làm lại lỗi cũ (`declineAutoCoupon()` →
   `clearCoupon()`) rồi chạy lại: **2 bài đỏ đúng chỗ**, sau đó mới khôi
   phục. Một bài chưa từng đỏ là một bài chưa biết mình canh cái gì.

### SQLite trong bộ nhớ, không phải MySQL

`phpunit.xml` (mặc định của Laravel) đã trỏ sang `sqlite/:memory:`. Giữ
nguyên vì hai lý do: chạy kiểm thử **không đụng tới cơ sở dữ liệu thật**
nên không có nguy cơ mất dữ liệu mẫu, và cả bộ mất **2 giây** — đủ nhanh
để chạy sau mỗi lần sửa, đó mới là điều kiện để nó thật sự được dùng.

Đánh đổi phải nói rõ: SQLite **không phải** MariaDB. Bộ này không bắt được
lỗi chỉ xuất hiện với đặc thù của MariaDB (kiểu dữ liệu, collation, khoá
ngoại). Toàn bộ migration hiện chạy được trên cả hai, và khi nào không còn
đúng thì phải chuyển sang một cơ sở dữ liệu MySQL riêng cho kiểm thử.

---

## QĐ-51 — Chỉ mã đã lưu vào ví mới được tự áp

**Thay thế phần "mã công khai" của QĐ-47 và QĐ-48.**

### Sai ở đâu

QĐ-47 giữ nguyên việc tự áp cả mã công khai chưa lưu, và chỉ sửa danh
sách cho khớp. Sai chỗ đó. Trang Voucher của chính cửa hàng in dòng này
ngay dưới tiêu đề:

> Lưu mã về ví, tới bước thanh toán chọn lại là xong — không phải nhớ mã.

Mã chưa lưu mà vẫn tự áp thì **nút "Lưu mã" và cả khái niệm ví không còn
nghĩa gì**. Khách thấy "Ví voucher đang trống" mà tổng tiền vẫn được
giảm, và không hiểu tiền ở đâu ra.

Tôi đã giải thích hai lần rằng đó là hành vi có chủ đích, thay vì nhận ra
mình đang chống lại một mô hình mà chính hệ thống đã hứa với khách. Đây
không phải chuyện diễn đạt — mô hình sai từ đầu.

### Luật mới

`BestCouponFinder::candidates()` chỉ lấy mã trong `coupon_user`. Khách
vãng lai không có ví, nên không có gì để tự chọn.

**Mã công khai không biến mất:**

| Cách dùng | Trước | Sau |
|---|---|---|
| Tự động áp | có | **không** — phải lưu trước |
| Bấm "Lưu mã" ở trang Voucher | có | có |
| Nhập tay ở bước thanh toán | có | có |

Chỉ **việc tự động** đòi khách phải nhận mã trước. Gõ mã là hành động rõ
ràng của khách và không được chặn — nếu không thì mã in trên tờ rơi cũng
vô dụng.

### Danh sách trở lại đúng một nguồn

Không còn hai nhóm, nên bỏ luôn tiêu đề nhóm và câu mở đầu của QĐ-49 —
chúng chỉ tồn tại để giải thích một danh sách trộn hai nguồn. Câu rỗng
quay về **"Ví voucher đang trống"**, đúng bằng câu trang Voucher dùng,
để hai nơi không kể hai câu chuyện khác nhau.

**Ràng buộc gốc của QĐ-47 vẫn giữ nguyên và nay được thoả một cách đơn
giản hơn:** danh sách trên trang khớp với thứ hệ thống tự chọn — vì cả
hai đọc đúng một nguồn là cái ví.

### Bài học

Người dùng báo cùng một chuyện **ba lần**. Hai lần đầu tôi sửa phần hiển
thị và giải thích tại sao dữ liệu đúng. Lần thứ ba mới nhìn lại mô hình.

Khi người dùng nhắc lại một điều đã được giải thích, khả năng cao là lời
giải thích sai chứ không phải người nghe chưa hiểu.

---

## QĐ-52 — Xác thực email bằng mã OTP, không phải liên kết

Bài thực hành của thầy dùng **liên kết có chữ ký**. Ở đây dùng **mã 6 chữ
số**, vì một lý do quan sát được: khách đọc email trên điện thoại rồi
quay lại thao tác trên máy tính. Bấm liên kết ở điện thoại thì phiên đăng
nhập lại nằm ở máy tính, và họ rơi vào trang đăng nhập giữa chừng. Gõ 6
chữ số thì không phụ thuộc thiết bị.

**Đường liên kết vẫn giữ nguyên** (`verification.verify` + middleware
`signed`). Nó gần như miễn phí vì `User implements MustVerifyEmail` đã có
sẵn, và giữ lại thì bài làm vẫn đúng nguyên yêu cầu — chỉ là mặc định
dùng OTP.

### Bốn lớp chặn, mỗi lớp cho một kiểu tấn công

| Lớp | Chặn gì | Con số |
|---|---|---|
| Hạn dùng | Email bị lộ về sau không dùng lại được | 15 phút |
| Số lần gõ sai | Dò mã | 5 lần |
| Khoảng chờ gửi lại | Dùng hệ thống làm máy gửi thư rác | 60 giây |
| Lưu băm | Ai đọc được CSDL cũng không xác thực hộ được | bcrypt |

Lớp "số lần gõ sai" bám theo **TÀI KHOẢN**, không theo IP. Đó mới là lớp
chính: `throttle:10,1` trong `routes/web.php` chỉ chặn một máy nện nghìn
lần mỗi phút, còn đổi IP là qua được — mà đổi IP thì rẻ.

**Đếm lần gõ TRƯỚC khi so sánh mã.** Tăng sau khi so sánh thì một request
bị ngắt giữa chừng là một lần đoán miễn phí.

**`digits:6` chứ không phải `integer`.** Mã `007355` là hợp lệ; ép sang số
nguyên là mất số 0 ở đầu và mã đúng bị coi là sai.

**Mỗi tài khoản chỉ MỘT mã sống** (`user_id` là khoá duy nhất, gửi lại là
`updateOrCreate`). Để nhiều mã cùng hiệu lực nghĩa là mã cũ bị lộ vẫn
dùng được, và số lần đoán kẻ tấn công có cũng nhân lên.

**Không xếp thư vào hàng đợi.** `EmailVerificationMail` mang mã GỐC; xếp
hàng đợi là mã gốc nằm trong cột `payload` của bảng `jobs` — đúng thứ mà
cả thiết kế này cố tránh khi chỉ lưu băm trong CSDL.

---

## QĐ-53 — Trang nào đòi email đã xác thực

**Khoá những trang GẮN VỚI DANH TÍNH:** hồ sơ, sổ địa chỉ, lịch sử đơn,
ví voucher, yêu thích, lịch chăm cây. Một địa chỉ email chưa ai chứng
minh là có thật thì không nên được dùng để nhận mã giảm giá hay xem lại
đơn đã mua.

**KHÔNG khoá giỏ hàng và thanh toán**, dù bài thực hành gợi ý. Cửa hàng
này **cho phép khách vãng lai đặt hàng**. Khoá giỏ với người đã đăng ký
nhưng chưa xác thực, trong khi người không có tài khoản mua thoải mái, là
phạt đúng nhóm khách thân thiết hơn — và làm mất một đơn hàng có thật để
đổi lấy một quy tắc không bảo vệ được gì.

### Tài khoản cũ phải được đánh dấu đã xác thực

Migration `create_email_verification_codes_table` chạy một câu `UPDATE`
đặt `email_verified_at = now()` cho mọi tài khoản đang `null`. Họ đăng ký
khi hệ thống chưa có bước này nên chưa từng có cơ hội xác thực; bật
middleware `verified` mà không làm việc đó là **khoá toàn bộ khách hiện
có ra khỏi tài khoản của họ**.

Chiều `down()` **không** đặt lại về `null`: không phân biệt được ai đã xác
thực thật với ai được đánh dấu bởi migration, nên xoá hết là làm mất dữ
liệu thật.

### Tên route không được đổi

`EnsureEmailIsVerified` chuyển hướng theo **TÊN** route, không theo đường
dẫn. Ba tên `verification.notice` / `verification.verify` /
`verification.send` phải giữ nguyên; đường dẫn thì tiếng Việt thoải mái.

---

## QĐ-54 — Thêm vào giỏ không tải lại trang

### Vấn đề

Khách lướt tới cuối trang chủ (cao **6026px**), bấm "Thêm vào giỏ" —
trang tải lại và ném họ về đầu. Muốn xem tiếp thì cuộn lại từ đầu. Thêm
ba món là ba lần cuộn lại.

### Nâng cấp, không phải thay thế

Biểu mẫu **vẫn là biểu mẫu thật**, gửi tới **cùng một route**.
`resources/js/add-to-cart.js` chỉ chặn sự kiện `submit` lại và làm cùng
việc đó bằng `fetch`. Không có JavaScript thì mọi thứ chạy y như trước.

Chỗ bám (`class="product-buy"`, `data-add-to-cart`) đã được đặt sẵn từ
lâu kèm chú thích "giữ lại để sau này nâng cấp" — nay dùng đúng vào việc
đó.

### Một endpoint, hai dạng trả lời

`CartController@store` nhận ra `expectsJson()` rồi đóng gói khác đi.
**KHÔNG tách thành `/api/gio-hang` riêng:** mọi phép kiểm tra (còn hàng,
có bán trực tiếp không, số lượng hợp lệ) chỉ được có MỘT bản. Hai
endpoint là hai bản luật, và bản ít người dùng hơn sẽ là bản bị quên khi
sửa — rồi thành đường vòng qua chính phép kiểm tra ta vừa viết.

Lỗi nghiệp vụ trả **422**, không phải 200 kèm `ok:false`: trả 200 thì mọi
công cụ theo dõi đều thấy request thành công và lỗi thật biến mất khỏi
biểu đồ.

### Bốn điều phải đúng

1. **Chỉ chặn nút "Thêm vào giỏ".** Nút "Mua ngay" nằm CHUNG một biểu mẫu
   (đổi đích bằng `formaction`) nhưng nó cố ý rời trang. Phân biệt bằng
   `event.submitter` — trình duyệt không hỗ trợ thì bỏ qua, để biểu mẫu
   chạy như cũ. Thà tải lại trang còn hơn gửi nhầm đích.

2. **Hỏng thì quay về cách cũ:** `submit(...).catch(() => form.submit())`.
   Mất mạng, máy chủ trả HTML thay vì JSON, phiên hết hạn — khách vẫn
   thêm được hàng. Im lặng nuốt lỗi ở đây mới là hỏng.

3. **Số món trong giỏ do MÁY CHỦ đếm** (`cartCount` trong JSON). Tự cộng
   ở trình duyệt là sai ngay khi khách mở hai tab, hoặc khi giỏ **gộp
   dòng trùng** thay vì thêm dòng mới.

4. **Thông báo phải nằm trong tầm mắt.** Thông báo của máy chủ ở đầu
   trang; với việc thêm-không-tải-lại thì khách đang ở giữa hoặc cuối
   trang — cả tính năng này sinh ra chính vì điều đó. Nên có thông báo
   nổi neo theo khung nhìn (`resources/css/components/toast.css`), dùng
   lại `dismissAfter()` của `flash.js` để cùng thời gian chờ và cùng cách
   tạm dừng khi rê chuột.

### Huy hiệu số món luôn có trong DOM

`resources/views/components/site/header.blade.php` giữ thẻ huy hiệu kể cả
khi giỏ trống, chỉ thêm `hidden`. Bỏ hẳn thẻ bằng `@if` thì JavaScript
phải tự dựng thẻ mới cho món đầu tiên — và tự nhớ đúng tên lớp, thứ sẽ
lệch ngay lần đổi giao diện sau.

`aria-label` của liên kết giỏ cũng đổi theo: người dùng trình đọc màn
hình không thấy huy hiệu, họ nghe cái nhãn đó.

---

## QĐ-55 — `assertSee` khớp chuỗi con, và điều đó làm hỏng một bài kiểm tra

Bài `trang_chu_co_du_thuoc_tinh_cho_javascript_bam_vao` canh **hợp đồng
giữa Blade và JavaScript**: đổi tên `data-add-to-cart` thì nút im lặng
quay về tải lại trang — không lỗi, không cảnh báo, tính năng biến mất mà
không ai biết.

Bản đầu viết `assertSee('data-add-to-cart')`. Thử làm hỏng thật (đổi
thành `data-add-to-cart-cu`) thì bài **vẫn xanh**, vì chuỗi cũ nằm trong
chuỗi mới.

Đã đổi sang `assertMatchesRegularExpression('/data-add-to-cart="{id}"/')`.
Thử lại: đỏ đúng lúc phải đỏ.

**Bài học:** kiểm thử phải tự chứng minh nó có tác dụng (QĐ-50 nguyên tắc
3), và phép so sánh cũng phải chặt như thứ nó đang canh. Một bài kiểm tra
xanh trong trường hợp hỏng còn tệ hơn không có bài nào — nó khiến người
ta yên tâm.

---

## QĐ-56 — Khoảng chờ gửi lại mã OTP phải đo từ lúc GỬI

### Lỗi đo được

1. Gửi mã lúc 10:00.
2. 10:05 khách gõ sai một lần → `attempts` tăng lên 1.
3. Bấm "Gửi lại mã" → **"Vui lòng đợi 59 giây nữa."**

Khoảng chờ đo bằng `updated_at`, mà `increment('attempts')` cũng chạm vào
cột đó. Mỗi lần gõ sai lại đẩy đồng hồ về 60 giây — **đúng người đang
cần mã mới nhất lại là người bị chặn**.

### Sửa

Thêm cột `sent_at`. `updated_at` trả lời "hàng này sửa lần cuối lúc nào"
— một câu hỏi của cơ sở dữ liệu. "Thư gửi lúc nào" là câu hỏi **nghiệp
vụ**, phải có cột riêng. Mượn cột này cho câu hỏi kia chính là nguyên
nhân sinh ra lỗi.

### Kèm theo: báo sai mã cho ra hồn

Lỗi cũ chỉ là một dòng `text-danger small` nằm **dưới** ô nhập. Nay là
khối `alert alert-danger` ngay **trên** ô nhập, dưới tiêu đề — thứ khách
nhìn đầu tiên sau khi trang tải lại. Và `withInput()` giữ lại mã vừa gõ:
sai một chữ số trong sáu là chuyện thường, xoá trắng ô thì họ phải nhìn
lại email và gõ lại cả sáu.

**KHÔNG cho lỗi này tự biến mất.** Thông báo tự tắt là dành cho việc ĐÃ
XONG; lỗi biểu mẫu phải nằm đó tới khi khách sửa xong.

---

## QĐ-57 — "Vé xem đơn" của khách vãng lai phải mất khi có người đăng nhập

### Lỗ hổng đo được — MÁY DÙNG CHUNG

1. Khách A đặt hàng **không đăng nhập**. Mã đơn được ghi vào session
   `checkout.placed` làm "vé" để xem lại đơn.
2. A rời máy. Khách vãng lai **không có nút đăng xuất** để bấm.
3. B ngồi xuống, đăng nhập bằng tài khoản của mình.
4. `login()` chỉ gọi `regenerate()` — **đổi ID phiên nhưng giữ nguyên dữ
   liệu**. Vé của A còn nguyên trong phiên của B.
5. B mở `/don-hang/{mã của A}` và đọc được **tên, số điện thoại, địa chỉ
   nhà** của A. Bấm huỷ đơn cũng được.

Đo qua HTTP thật trước khi sửa: B nhận **200** và thấy đủ tên khách.
Sau khi sửa: **403** cho cả xem lẫn huỷ.

### Sửa

`login()` và `register()` xoá `checkout.placed` ngay sau `regenerate()`.
Cái vé đó gắn với **một người vô danh**, không gắn với tài khoản nào;
vừa có người đăng nhập thì nó hết giá trị.

**KHÔNG tự gán đơn cũ cho tài khoản vừa đăng nhập.** Ở bước 3 hệ thống
không thể biết B có phải chính là A hay không, và đoán sai theo hướng đó
là trao **vĩnh viễn** dữ liệu của A cho B — tệ hơn hẳn lỗi đang sửa.
Khách vãng lai muốn xem lại đơn thì dùng trang Tra cứu đơn (mã đơn + số
điện thoại), đúng đường đã thiết kế sẵn cho họ.

`logout()` không cần sửa: nó đã gọi `invalidate()`, xoá sạch phiên.

---

## QĐ-58. Phép kiểm và phép ghi phải là MỘT câu lệnh

**Bối cảnh:** rà soát phát hiện năm chỗ cùng một khuôn: đọc trạng thái →
quyết định → ghi. Giữa bước đọc và bước ghi, một request khác chen vào và
làm quyết định kia thành sai.

| Chỗ | Cảnh xảy ra | Hậu quả |
|---|---|---|
| `OrderService::changeStatus()` | hai request cùng huỷ một đơn | kho hoàn **hai lần** |
| `CouponService::redeem()` | hai khách cùng lấy lượt cuối | `used_count` **vượt** `usage_limit` |
| `EmailVerifier::confirm()` | nhiều request cùng đoán mã | giới hạn 5 lần thành **vô hạn** |
| `OrderService::consumeStock()` | admin tắt hàng khi khách đang thanh toán | nhận đơn cho hàng **đã ngừng bán** |
| `EmailVerifier::send()` | bấm "Gửi lại mã" hai lần | khách nhận **hai thư**, chỉ thư sau dùng được |

**Quyết định:** ở mọi chỗ như vậy, **cơ sở dữ liệu là nơi phân xử**, không
phải bản sao trong bộ nhớ PHP. Ba cách dùng, theo thứ tự ưu tiên:

1. **Một câu UPDATE có điều kiện, rồi đọc số dòng bị ảnh hưởng.**
   `->where('used_count', '<', 'usage_limit')->update(...)`. Trả về 0 dòng
   nghĩa là người khác đã lấy mất — ném ngoại lệ.
2. **`lockForUpdate()` rồi ĐỌC LẠI và kiểm lại.** Khoá mà không đọc lại
   thì vô nghĩa: vẫn đang quyết định trên dữ liệu cũ.
3. **Khoá nguyên tử (`Cache::lock`)** khi việc cần bảo vệ không nằm gọn
   trong một bảng — như `send()`, gồm cả ghi bản ghi lẫn gửi thư.

**Điều dễ nhầm:** `increment()` KHÔNG phải phép bảo vệ. Nó chống mất bản
ghi đè (hai lần +1 ra +2), nhưng không chặn vượt giới hạn. Chú thích cũ
trong `redeem()` nhầm hai chuyện đó làm một — chú thích tự tin là chỗ dễ
để lỗi nằm lại lâu nhất.

## QĐ-59. Ghi lượt dùng mã nằm TRONG giao dịch tạo đơn

`redeem()` trước đây gọi từ `CheckoutController` **sau** khi transaction
tạo đơn đã commit. Hai hỏng hóc:

1. `redeem()` ngã thì đơn **đã ghi xong** và không cuộn lại được — khách
   nhận hàng đã giảm giá, bộ đếm mã đứng yên. Một mã "50 lượt" dùng được
   không giới hạn theo đúng đường này.
2. `place()` có chống đặt trùng: bấm hai lần thì lần sau **trả về đơn cũ**.
   Controller không phân biệt được nên vẫn `redeem()` lần nữa cho cùng
   một đơn.

**Quyết định:** chuyển vào `OrderService::createOrder()`. Cùng transaction
nên hỏng là cuộn lại cùng nhau, và nó chỉ chạy trên đường **thật sự tạo
đơn mới**. Controller chỉ còn dọn phiên.

## QĐ-60. Trạng thái thanh toán phải hợp với trạng thái đơn

Luật chuyển trạng thái thanh toán trước đây chỉ hỏi trạng thái **thanh
toán** hiện tại, không hỏi đơn đang ở đâu. Bổ sung hai chốt:

- **Hoàn tiền chỉ cho đơn ĐÃ HUỶ.** Đánh dấu "đã hoàn tiền" cho một đơn
  đang giao là ghi rằng cửa hàng đã trả tiền lại trong khi hàng vẫn trên
  đường tới khách. Hoàn tiền cho đơn chưa huỷ là nghiệp vụ **trả hàng**,
  cần quy trình riêng; chưa có quy trình thì chặn, chứ không ghi một
  trạng thái không ai biết nghĩa là gì.
- **Không gỡ đánh dấu thanh toán của đơn ĐÃ GIAO XONG.** Đường lui "đã
  trả → chưa trả" sinh ra để sửa cú bấm nhầm, mà bấm nhầm thì phát hiện
  ngay. Đơn đã giao xong quay về "chưa thanh toán" là trạng thái vô
  nghĩa: hàng ở nhà khách, tiền thì hệ thống bảo chưa nhận. Nếu khách
  thật sự chưa trả thì đó là **công nợ**, cần chỗ ghi riêng.

## QĐ-61. Xoá mềm thì không được xoá file

`ProductController::destroy()` xoá file ảnh trên đĩa **rồi** gọi
`$product->delete()` — mà Product dùng `SoftDeletes`, nên delete() chỉ ghi
một dấu thời gian. Một nửa hành động hoàn tác được, nửa kia thì không:
khôi phục sản phẩm ra trang hàng đủ tên, đủ giá, **không còn tấm ảnh nào**.

**Quyết định:** xoá mềm không đụng vào file. File dọn ở đúng lúc nó thật
sự thành rác — khi sản phẩm bị xoá **vĩnh viễn** — bằng `static::forceDeleting`
trong `Product::booted()`.

Đặt ở model chứ không ở controller: luật này đúng với **mọi** đường xoá
vĩnh viễn, kể cả đường viết sau này. Nghe `forceDeleting` (trước) chứ
không `forceDeleted` (sau), vì bản ghi `product_images` đi theo khoá ngoại
cascade — nghe sau thì danh sách ảnh đã rỗng và không còn biết xoá file nào.

## QĐ-62. Sắp xếp theo giá phải theo GIÁ KHÁCH THẤY

Danh sách sản phẩm in **giá sau khuyến mại** nhưng sắp theo `base_price`.
Chọn "Giá thấp đến cao" rồi thấy món 250.000₫ nằm dưới món 300.000₫ — sai
ngay trên màn hình, và sai đúng ở nhóm hàng cửa hàng muốn khách thấy trước.

**Quyết định:** `Product::scopeOrderByEffectivePrice()` — truy vấn con
tương quan tính giá hiệu lực trong SQL. Phải làm ở SQL vì danh sách có
**phân trang**: sắp bằng PHP chỉ đảo thứ tự trong trang hiện tại, món rẻ
nhất nằm ở trang 3 vẫn ở trang 3.

Biểu thức chép lại đúng luật của `PricingService`, gồm cả hai chốt chặn
(không âm, không cao hơn giá gốc). **Hai bản chép của một luật thì sớm
muộn cũng trôi khỏi nhau**, nên có một bài kiểm tra so trực tiếp thứ tự
của SQL với thứ tự tính bằng `PricingService` — chính bài đó đã bắt được
việc thiếu hai chốt chặn ngay khi viết.

Dựng bằng query builder chứ không viết chuỗi SQL thô: cách hỏi "mảng JSON
có chứa số này không" khác nhau giữa MariaDB (`JSON_CONTAINS`) và SQLite
mà bộ kiểm thử dùng.

## QĐ-63. Ba trường phân loại sản phẩm phải kể cùng một câu chuyện

`category_id`, `product_type`, `selling_form` đều hợp lệ khi xét riêng,
nhưng ghép lại có thể vô nghĩa: "cây cảnh" bán theo "bó hoa", hay bó hoa
nằm trong danh mục vật tư. Không tầng nào chặn được, và không có thông báo.

Hai hậu quả nhìn thấy được:
- **Sai danh mục** → hàng nằm nhầm gian. `mainCatalog()`/`supplyCatalog()`
  chia hàng theo `kind` của danh mục.
- **Sai hình thức bán** → mất bộ thông tin chăm sóc. `careProfile()` lấy
  theo `selling_form`.

**Quyết định:** luật đặt trong `ProductType::allowedSellingForms()` và
`fitsCategoryKind()`, dùng chung cho cả thêm mới lẫn sửa qua trait
`ValidatesProductClassification`. Đường "sửa sản phẩm" mới là đường dễ
tạo tổ hợp sai nhất — admin đổi một ô rồi lưu.

**Danh sách tổ hợp hợp lệ đối chiếu với dữ liệu đang chạy, không nghĩ ra
cho gọn:** cả 33 sản phẩm hiện có đều thoả luật mới, nên không chặn nhầm
hàng đang bán.

## QĐ-64. Chỉ mục duy nhất có cột NULL thì không chặn được gì

`cart_items` có `unique(cart_id, product_id, product_variant_id)` từ đầu.
Nhưng trong SQL **NULL không bằng bất cứ thứ gì, kể cả NULL khác** — nên
với hàng không có quy cách (phần lớn sản phẩm), hai dòng y hệt nhau vẫn
chèn được cả hai. Đã kiểm chứng trên cơ sở dữ liệu thật: đếm ra 2.

`CartService` tìm dòng cũ trước khi thêm nên đường thường ngày không sinh
trùng. Nhưng "tìm rồi thêm" là hai bước — bấm nhanh hai lần vào nút Thêm
vào giỏ (nay là nút AJAX nên còn dễ hơn) thì hai request cùng thấy "chưa
có" và cùng chèn.

**Quyết định:** cột sinh tự động `variant_key = COALESCE(product_variant_id, 0)`
và đặt chỉ mục lên đó. Cơ sở dữ liệu tự tính, mã nguồn không bao giờ ghi
vào — không có gì phải nhớ, không có chỗ nào quên.

## QĐ-65. Nhật ký hành vi sống sót qua việc xoá

`user_events` không nhất quán với chính nó: `user_id` là `nullOnDelete`
(sự kiện ở lại), còn `product_id`/`category_id` là `cascadeOnDelete` (sự
kiện biến mất). Cách hiểu đúng đã ghi ở đầu `AnalyticsService`: đây là
**nhật ký những việc đã xảy ra**.

Xoá một danh mục hôm nay làm bốc hơi mọi lượt xem của danh mục đó từ
những tháng trước. Báo cáo phễu của tháng Bảy đổi số vào tháng Chín mà
không ai đụng vào tháng Bảy. **Số liệu lịch sử tự sửa lại chính nó thì
không so sánh giữa các kỳ được nữa** — mà đó gần như là toàn bộ lý do
người ta mở trang báo cáo. Danh mục không dùng xoá mềm nên đường này có
thật.

**Quyết định:** cả hai chuyển sang `nullOnDelete`.

## QĐ-66. "Mua ngay" là một sự kiện riêng

`buyNow()` ghi nhật ký là `AddToCart`. Nhưng theo đúng thiết kế, "Mua
ngay" **không đụng vào giỏ hàng** — món đó giữ riêng trong phiên.

Ghi sai làm phễu `View → Cart → Purchase` nói dối: khách xem rồi bấm Mua
ngay rồi đặt hàng được đếm là **đã qua bước giỏ hàng**, dù chưa bao giờ
mở giỏ. Tỷ lệ "xem → giỏ" bị thổi lên, tỷ lệ "giỏ → mua" bị kéo xuống —
cả hai lệch về hướng làm người đọc kết luận sai về chỗ khách rơi rụng.

## QĐ-67. Thống kê bán chạy gom theo `product_id`, không theo tên

Tên trong `order_items` là **bản chụp** lúc đặt hàng — cố ý, để hoá đơn cũ
không đổi khi cửa hàng sửa tên. Nhưng gom nhóm theo nó thì đổi tên "Hoa
hồng đỏ" thành "Hoa hồng đỏ Ecuador" tách một sản phẩm thành hai dòng, cả
hai đều thấp hơn thực tế và có thể rơi khỏi top; còn hai sản phẩm từng
trùng tên thì bị gộp làm một.

`MAX(product_name)` chứ không phải `product_name` trần: chuẩn SQL cấm chọn
cột ngoài `GROUP BY`, và MySQL bật `ONLY_FULL_GROUP_BY` sẽ báo lỗi.

## QĐ-68. Tắt danh mục thì hàng bên trong cũng phải khuất

`scopeMainCatalog()`/`scopeSupplyCatalog()` không lọc `categories.is_active`.
Nút tắt danh mục chỉ giấu cái nhãn còn hàng vẫn bày nguyên trên kệ — một
lời hứa suông, và cửa hàng phát hiện bằng cách có người đặt đúng thứ mình
vừa ngừng bán. Đo được: tắt danh mục "Hoa" làm danh sách từ **33 xuống 25**
sản phẩm.

`scopeActive()` tách riêng khỏi `plants()`/`supplies()` vì là hai câu hỏi
khác nhau — trang quản trị **phải** thấy cả danh mục đã tắt.

---

## QĐ-69. Mã giảm giá tự rụng thì phải nói cho khách biết

`CheckoutSource::resolveCoupon()` kiểm lại mã ở **mỗi lần dựng giỏ** và gỡ
mã khi nó không còn dùng được — nhờ vậy con số trên màn hình không bao giờ
sai. Nhưng việc gỡ diễn ra **hoàn toàn im lặng**.

Khách bớt một món, tổng tiền tăng lên, và không có gì trên trang giải
thích. **Người ta sẽ tự tìm lời giải thích, và lời họ tự nghĩ ra thường là
"trang web tính sai tiền".**

**Quyết định:** ghi một lời nhắn kèm **lý do lấy thẳng từ `CouponService`**
— "mã này chỉ áp dụng cho đơn từ 1.000.000₫" chứ không phải "mã không dùng
được". Biết lý do thì khách còn một lựa chọn: mua thêm.

**KHÔNG dùng `flash()`.** Dữ liệu flash sống qua đúng một request *nữa* sau
request đặt nó. Mà mã có thể rụng ngay giữa lúc đang dựng trang — khi đó
lời nhắn hiện ở trang này **rồi hiện lại** ở trang sau, làm khách tưởng mã
vừa rụng thêm lần nữa. Khoá session riêng + đọc bằng `pull()` (đọc xong xoá
luôn) thì hiện đúng một lần, bất kể nó được đặt ở request nào.

Ghi nhận trong lúc làm: **trang giỏ hàng không kiểm lại mã** — `basket()`
chỉ được gọi ở các trang thanh toán. Không phải lỗi: `cartBasket()` không
gắn mã nên trang giỏ chưa bao giờ hiện giảm giá từ mã. Lời nhắn vì thế xuất
hiện ở bước thanh toán, đúng chỗ con số thật sự thay đổi.

## QĐ-70. `activity_logs` — nhật ký thao tác quản trị

**Nghiệp vụ:** cửa hàng có nhiều hơn một người vào khu quản trị. Khi một
đơn bị huỷ nhầm, một sản phẩm đổi giá, hay một đánh giá xấu biến mất khỏi
trang, câu hỏi đầu tiên luôn là "ai làm?" — và không có chỗ nào trả lời
được. Bản ghi chỉ giữ trạng thái **cuối cùng**: nhìn một sản phẩm giá 300k
không biết hôm qua nó là 500k và ai hạ xuống.

**Khác `user_events`:** bảng kia ghi hành vi **khách** trên trang bán hàng
để phân tích. Bảng này ghi thao tác **ghi dữ liệu** ở khu quản trị để truy
trách nhiệm. Hai mục đích, hai vòng đời, hai bảng.

**Bốn quyết định thiết kế:**

1. **Ghi tường minh tại chỗ gọi, không dùng model observer.** Observer bắt
   được mọi `save()` nên nghe thì tiện, nhưng nó chỉ biết "bản ghi X vừa
   đổi", không biết **việc** gì vừa xảy ra: cùng một `save()` lên `Order`
   có thể là xác nhận, là huỷ, hay là sửa ghi chú. Observer còn ghi cả
   những lần ghi do hệ thống tự làm, làm loãng nhật ký tới mức không ai đọc.

2. **Chụp `actor_name` ngay lúc ghi.** `user_id` thành NULL khi tài khoản
   bị xoá, và khi đó dòng nhật ký mất hết ý nghĩa. Cùng lý do với việc
   `order_items` chụp tên sản phẩm.

3. **`description` viết sẵn, không dựng lại lúc hiển thị.** Cách diễn đạt
   sẽ đổi theo thời gian, và khi đó nhật ký cũ bị đọc bằng luật mới. Câu
   chữ phải đóng băng cùng sự việc.

4. **Không khoá ngoại cho `subject`.** Đối tượng có thể bị xoá hẳn, mà dòng
   nhật ký *về việc xoá nó* thì phải ở lại. Đây đúng là chỗ khoá ngoại làm
   hỏng việc.

**Không có đường xoá, không có `updated_at`.** Một nhật ký mà người bị ghi
xoá được thì không dùng để truy trách nhiệm — mà đó là toàn bộ lý do nó tồn
tại. Có một bài kiểm tra canh riêng việc không ai vô tình thêm route
`destroy` vào sau này.

**Ghi nhật ký không bao giờ được làm hỏng thao tác chính.** `ActivityLogger`
tự nuốt lỗi và ghi ra log hệ thống: admin vừa huỷ một đơn thành công, không
được để họ thấy trang lỗi rồi bấm huỷ lần nữa.

## QĐ-71. `order_status_events` — dòng thời gian đơn hàng

**Nghiệp vụ:** `orders` chỉ giữ trạng thái hiện tại cộng vài mốc rời rạc
(`confirmed_at`, `completed_at`, `cancelled_at`). Không trả lời được những
câu hỏi hằng ngày: "đơn nằm ở khâu chuẩn bị bao lâu?" (không có
`preparing_at`), "ai chuyển nó sang Đang giao?" (không ghi người làm).

Và với **khách**: trang đơn hiện đúng một trạng thái. Không có lịch sử thì
họ gọi điện, và cửa hàng trả lời bằng cách mở đúng cái màn hình cũng chẳng
có thông tin gì hơn.

**Khác `activity_logs`:** nhật ký kia là nội bộ, khách không bao giờ thấy.
Bảng này là **lịch sử của đơn**, hiện cho chính khách xem. Gộp làm một thì
hoặc khách đọc được ghi chú nội bộ, hoặc nhật ký nội bộ phải tự kiểm duyệt.

**Ghi mốc TRONG cùng transaction với việc đổi trạng thái.** Đặt bên ngoài
thì có lúc trạng thái đổi xong mà mốc không ghi được, và dòng thời gian
nhảy cóc — khách thấy "đã xác nhận" rồi "đã giao", không có bước chuẩn bị
nào ở giữa. **Lịch sử thiếu mảnh tệ hơn không có lịch sử, vì nó trông vẫn
như đầy đủ.**

**Dựng lại lịch sử cho đơn cũ ngay trong migration**, từ những mốc đã có
sẵn — 39 đơn cũ nhận 117 mốc. Không làm thì mọi đơn trước hôm nay hiện một
dòng thời gian trống rỗng và khách hiểu là đơn chưa được xử lý gì. **Một
tính năng mới không được làm dữ liệu cũ trông như bị hỏng.**

**Hiện mốc đã xảy ra, không vẽ sẵn các bước chưa tới.** Nhiều nơi vẽ đủ năm
bước rồi tô mờ bước chưa đến; ở đây không, vì đơn **có thể bị huỷ ở bất cứ
bước nào** — vẽ sẵn "Đang giao" cho một đơn vừa huỷ là hứa một việc sẽ
không xảy ra.

**Một component cho cả hai trang**, khác nhau đúng một tham số `showActor`.
Với khách, người bấm nút là "cửa hàng", không phải một cái tên: hiện tên
nhân viên trên trang công khai là lộ thông tin nội bộ mà không đem lại gì
cho người đọc. Viết hai bản thì khách và nhân viên sẽ nhìn thấy hai lịch sử
khác nhau cho cùng một đơn.

## QĐ-72. Sắp xếp theo cột — danh sách trắng là bắt buộc

**Nghiệp vụ:** mọi trang danh sách có một thứ tự cố định, nhưng công việc
thật thì không: "sản phẩm nào sắp hết hàng?", "mã nào được dùng nhiều
nhất?", "đơn nào giá trị lớn?".

**`orderBy($request->query('sap'))` là một lỗ hổng**, không chỉ là cẩu thả:
tên cột đi thẳng vào câu SQL. Nhẹ thì lỗi 500 khi gõ bừa; nặng hơn là sắp
theo cột `password` để dò ký tự đầu của mã băm — chậm nhưng làm được. Mỗi
trang tự khai ánh xạ `khoá trên URL => cột thật`; giá trị lạ thì **bỏ qua
và về thứ tự mặc định**, không báo lỗi.

**Luôn chốt thêm khoá chính.** Sắp theo cột nhiều giá trị trùng nhau —
trạng thái, tồn kho — thì thứ tự giữa các hàng bằng nhau là **không xác
định**, và với phân trang, một bản ghi có thể xuất hiện ở cả trang 1 lẫn
trang 2, hoặc không ở trang nào.

**Chỉ mở sắp xếp cho cột thật sự dùng tới.** "Danh mục" và "Hình thức" đã
có bộ lọc riêng — sắp theo chúng chỉ gom hàng cùng loại lại gần nhau, đúng
bằng việc lọc nhưng kém rõ hơn. Cho sắp mọi cột là làm loãng hàng tiêu đề
tới mức không cột nào nổi lên.

**Thêm cột "Tồn kho" vào bảng sản phẩm** cùng lúc: sắp theo một cột không
hiện trên bảng thì thứ tự trông như ngẫu nhiên.

## QĐ-73. Thao tác hàng loạt

**Nghiệp vụ:** hết mùa Tết thì ba mươi sản phẩm phải chuyển sang tạm ẩn.
Làm từng cái là ba mươi lần bấm Sửa, cuộn, Lưu, chờ tải lại — và tới cái
thứ mười lăm thì người làm bắt đầu bỏ sót. **Đó không phải sự bất tiện, đó
là nguồn sinh lỗi dữ liệu.**

**Không bọc bảng trong `<form>`.** Mỗi dòng đã có sẵn một `<form>` cho nút
Xoá, và HTML **cấm form lồng form** — trình duyệt tự đóng thẻ ngoài ở chỗ
gặp thẻ trong, làm hỏng cả hai. Không có lỗi nào hiện ra; chỉ là các nút
thôi hoạt động. Dùng thuộc tính `form="..."`: ô tích nằm ở bất kỳ đâu vẫn
thuộc về biểu mẫu có id tương ứng.

Kéo theo một chi tiết trong JS: phải dùng `form.elements`, **không phải**
`form.querySelectorAll` — ô tích nằm ngoài cây con của form.

**Ba điều bắt buộc:** việc phải nằm trong danh sách trắng (đây nguy hiểm
hơn sắp xếp: sắp sai chỉ đảo thứ tự, việc hàng loạt thì **ghi** dữ liệu);
mọi id phải qua `exists` (một id sai thì **cả lô** không được ghi, chứ
không lặng lẽ bỏ qua); và thông báo phải **nói rõ số lượng** — "đã cập
nhật" trống không thì người dùng phải tự đếm lại.

**Đánh giá không có "xoá hàng loạt"**, cũng như không có xoá từng cái: nội
dung là của khách. Cửa hàng được ẩn khỏi trang bán hàng, không được xoá lời
người ta đã viết. Ẩn thì lùi lại được.

## QĐ-74. Khoá tài khoản, không xoá tài khoản

**Nghiệp vụ:** cửa hàng chỉ có đúng một cách xử lý một tài khoản gây rối —
xoá nó. Nhưng xoá kéo theo: đơn hàng mất người đứng tên, đánh giá thành
"(tài khoản đã xoá)", và xoá nhầm thì không có đường lùi. **Gần như mọi lần
người ta muốn xoá một tài khoản, thứ họ thật sự cần là khoá nó.**

**Dùng mốc thời gian `locked_at`, không dùng cờ đúng/sai.** `is_locked`
chỉ nói "đang khoá"; `locked_at` nói thêm "từ bao giờ" — thứ luôn được hỏi
tới khi có tranh cãi, và không tốn thêm gì để lưu.

**Chặn ở HAI chỗ, và chỗ thứ hai mới là chỗ quan trọng.** Phép kiểm ở màn
hình đăng nhập chỉ chạy một lần; sau đó người dùng giữ một **phiên** sống
nhiều ngày. Nghĩa là: quản trị khoá một tài khoản đang gửi đánh giá rác, và
tài khoản đó **vẫn tiếp tục gửi** vì không cần đăng nhập lại. Middleware
`EnsureUserIsNotLocked` kiểm ở mọi request nên khoá có hiệu lực ngay ở lần
bấm tiếp theo — và **huỷ cả phiên**, không chỉ đăng xuất, vì phiên còn giữ
giỏ hàng và vé xem đơn.

**Kiểm SAU `Auth::attempt()`, không phải trước.** Kiểm trước thì phải tra
email khi *chưa* biết người gõ có đúng mật khẩu hay không, và thông báo
"tài khoản đã bị khoá" trở thành cách để người lạ dò xem email nào tồn tại.
Kiểm sau thì chỉ đúng chủ tài khoản đọc được lý do.

**Nói lý do cho chính người bị khoá.** Chặn ai đó mà không nói vì sao thì
họ chỉ nghĩ là trang web hỏng, và việc tiếp theo họ làm là gọi điện hoặc
lập tài khoản mới — cả hai đều tốn công cửa hàng hơn một dòng giải thích.
Mở khoá thì **xoá luôn lý do**, nếu không lần khoá sau người dùng sẽ đọc
được lời giải thích của một việc khác.

## QĐ-75. Ba chốt chặn khi đổi vai trò

Đây là màn hình có hậu quả lớn nhất trong khu quản trị: một cú bấm sai có
thể khoá chính cửa hàng ở ngoài hệ thống của mình, và **không màn hình nào
chữa được** — chỉ còn cách sửa thẳng cơ sở dữ liệu.

1. **Không tự đổi vai trò / tự khoá chính mình.** Admin duy nhất tự hạ mình
   là mất quyền vào khu quản trị. Chặn cả chiều tự *nâng* quyền: một admin
   thì không cần, còn nếu sau này có vai trò thấp hơn thì đó chính là lỗ
   hổng leo thang quyền.

2. **Luôn còn ít nhất một quản trị viên dùng được.** Phép đếm phải hỏi
   *"còn ai nếu bỏ người này ra"*, và phải **bỏ qua admin đang bị khoá** —
   họ không đăng nhập được nên không phải lối vào dự phòng.

   Bản đầu tôi viết là `tổng số admin dùng được <= 1`, và nó **chặn nhầm**:
   khi có một admin đang bị khoá, hạ quyền chính người đó chẳng làm ai mất
   quyền truy cập, nhưng phép đếm ấy vẫn từ chối. Đã sửa thành
   `whereKeyNot($user)`, và có hai bài kiểm tra canh cả hai chiều.

   Ở cấu hình quyền hiện tại chốt này gần như không chạm tới — người thao
   tác bắt buộc là admin dùng được và không tự đổi được vai trò của mình.
   Giữ lại vì nó sẽ có việc ngay khi xuất hiện vai trò thứ ba.

3. **Ghi nhật ký.** Không phải phép chặn, nhưng cùng mục đích: "ai phong
   admin cho tài khoản kia" phải trả lời được sau nhiều tháng.

**Không hiện nút cho dòng của chính mình.** Máy chủ đã chặn, nhưng hiện một
cái nút chắc chắn báo lỗi là mời người ta bấm vào chỗ không dùng được. Chặn
ở máy chủ là để an toàn; không hiện nút là để không lừa người dùng.

## QĐ-76. Nhật ký phải tự nói ra giới hạn của chính nó

`ActivityLogger` chỉ chạy khi thao tác đi qua controller. Sửa thẳng cơ sở
dữ liệu — phpMyAdmin, `tinker`, `db:seed` — không để lại dòng nào.

**Không vá được ở tầng ứng dụng.** Model observer cũng không cứu:
`User::whereKey($id)->update([...])` là câu lệnh của query builder và
**không sinh sự kiện model**. Bắt được cả những đường đó cần công cụ ở tầng
cơ sở dữ liệu (binlog, trigger), ngoài phạm vi đồ án.

**Quyết định: viết giới hạn đó ra ngay trên trang nhật ký.**

Người đọc mặc định hiểu nhật ký là bản ghi **đầy đủ**, rồi kết luận rằng
thứ không có ở đây thì đã không xảy ra. Một nhật ký nói thiếu mà người đọc
tưởng là đủ **còn dẫn sai hơn không có nhật ký** — vì nó tạo ra sự tin
tưởng mà nó không xứng đáng.

**Chuyện đã thật sự xảy ra:** trong lúc dựng tính năng này, tôi khoá và hạ
quyền một tài khoản qua HTTP (được ghi), rồi trả lại nguyên trạng bằng lệnh
ghi thẳng (không được ghi). Đọc nhật ký thì tưởng tài khoản vẫn đang bị
khoá, trong khi nó vẫn đăng nhập bình thường. Chính người dùng phát hiện ra
sự lệch đó — nhật ký nói một đằng, đăng nhập được một nẻo.

Bài học rộng hơn: **một công cụ đối chiếu phải nói rõ nó đối chiếu được cái
gì.** Chỗ nguy hiểm nhất của nó không phải chỗ nó sai, mà chỗ nó im lặng.

---

## QĐ-77. Seeder chỉ TẠO, không ghi đè

`AdminUserSeeder` dùng `updateOrCreate`, nên mỗi lần chạy `php artisan
db:seed` là mật khẩu quản trị bị đặt lại về giá trị mặc định nằm trong mã
nguồn. Người dùng đổi mật khẩu thành chuỗi chỉ mình họ biết, vài hôm sau
chạy seeder để nạp thêm dữ liệu mẫu, và mật khẩu **âm thầm quay về
`Admin@12345`** — một chuỗi ai đọc repo cũng biết. Không thông báo, không
log. Đo được: đổi mật khẩu → chạy seeder → `Hash::check('Admin@12345')`
trả về true.

**Quyết định:** `firstOrCreate`. Vẫn idempotent (chạy lại không tạo trùng),
nhưng không đụng tới bản ghi đã có.

**Vai trò thì VẪN đặt lại mỗi lần.** Hai thứ khác nhau về hậu quả: ghi đè
mật khẩu làm mất thứ người dùng tự đặt và hạ bảo mật xuống một chuỗi công
khai; đặt lại vai trò thì chữa đúng cái tình huống seeder sinh ra để chữa —
chạy `db:seed` sau khi lỡ tay hạ quyền và không còn ai vào được khu quản trị.

Seeder cũng **nói ra** nó vừa làm gì. Im lặng thì người chạy phải đoán, và
đoán sai theo cả hai hướng đều tốn thời gian.

## QĐ-78. Đường thoát khi đang đăng nhập mà quên mật khẩu cũ

Biểu mẫu đổi mật khẩu bắt nhập mật khẩu hiện tại — đúng, vì thiếu phép
kiểm đó thì ai mượn được máy đang mở sẵn cũng chiếm được tài khoản.

Nhưng người đăng nhập bằng "ghi nhớ đăng nhập" từ nhiều tháng trước hoàn
toàn có thể **không còn nhớ mật khẩu cũ**, và khi ấy họ mắc kẹt: đang đăng
nhập mà không đổi được mật khẩu. Đường thoát duy nhất là đăng xuất rồi bấm
"Quên mật khẩu" — trang đó nằm sau middleware `guest` nên phải đăng xuất
**thật** mới vào được, và không ai đoán ra bước đó.

**Quyết định:** một nút ngay dưới biểu mẫu, gửi đúng liên kết đặt lại ấy về
email của chính họ.

- **Địa chỉ lấy từ tài khoản đang đăng nhập, không từ biểu mẫu.** Nhận
  email từ request thì màn hình này thành công cụ gửi thư tới địa chỉ bất
  kỳ, ký tên cửa hàng.
- **Dùng lại cơ chế của trang "Quên mật khẩu", không dựng OTP riêng.** Hai
  đường đặt lại mật khẩu là hai bộ luật phải giữ đồng bộ về hạn dùng, số
  lần thử và cách huỷ token — bộ thứ hai sẽ là bộ bị quên.
- **Ở đây nói thẳng địa chỉ email**, khác với trang "Quên mật khẩu" phải
  trả lời trung tính để không thành công cụ dò tài khoản. Người dùng đã
  đăng nhập và đang xem chính hồ sơ của mình; giấu địa chỉ lúc này chỉ
  khiến họ không biết mở hộp thư nào.

## QĐ-79. Tên trình duyệt trong "Thiết bị đang đăng nhập" chỉ là phỏng đoán

Người dùng báo: đang dùng **Cốc Cốc** nhưng danh sách hiện "Chrome trên
Windows". Kiểm chuỗi User-Agent thật trong bảng `sessions`:

```
Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36
(KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36
```

**Không có mẩu nào phân biệt được với Chrome thật.** Cốc Cốc bản mới cố ý
bỏ token `coc_coc_browser` để khỏi bị các trang web chặn nhầm. Thư viện
nhận dạng đắt tiền nhất cũng chỉ đọc đúng chuỗi đó nên cũng trả lời
"Chrome". Đây là giới hạn thật, không phải thiếu sót sửa được.

**Hai việc đã làm:**

1. Bổ sung tên riêng cho các trình duyệt **có** khai (Cốc Cốc bản cũ,
   Brave, Vivaldi, Yandex, Samsung, UC) — đặt **trước** `Chrome/` vì chuỗi
   của chúng đều chứa `Chrome/`.
2. Nói rõ trên màn hình rằng đây là phỏng đoán, và chỉ về hai thứ đáng tin
   hơn: **địa chỉ IP** và **lần hoạt động gần nhất**.

Việc thứ hai quan trọng hơn việc thứ nhất. Người dùng thấy "Chrome" trong
khi đang dùng Cốc Cốc sẽ rút ra kết luận hợp lý nhất: **có người lạ đang
đăng nhập** — rồi đá nhầm phiên của chính mình hoặc hoảng lên đổi mật khẩu
vô cớ. **Một cảnh báo sai gây hại nhiều hơn hẳn việc không có thông tin đó.**

## QĐ-80. Nền sáng / tối là trục KHÁC với theme theo mùa

```
data-theme  = Tết / Noel / Valentine  → CỬA HÀNG chọn, cho mọi khách
data-scheme = sáng / tối              → TỪNG KHÁCH chọn, cho riêng họ
```

Hai trục độc lập: theme Tết vẫn phải đọc được ở chế độ tối. Gộp làm một thì
hoặc cửa hàng ép mọi người cùng một độ sáng, hoặc mỗi người tự tắt được
không khí lễ hội mà cửa hàng đang muốn dựng.

**ĐẢO NGƯỢC THANG MÀU, KHÔNG SỬA TỪNG COMPONENT.** Thang `--brand-50 …
--brand-700` đi từ nhạt tới đậm, và mọi component đã dùng nó theo đúng
nghĩa đó: `--brand-50` cho nền phủ nhẹ khi rê chuột, `--brand-700` cho chữ
đậm trên nền ấy. Chế độ tối chỉ đảo thứ tự sáng-tối mà giữ nguyên sắc — và
không component nào phải sửa, kể cả component viết sau này.

Cách còn lại — thêm `[data-scheme="toi"]` vào từng tệp component — là hàng
chục chỗ phải nhớ, và chỗ bị quên sẽ là một mảng trắng loá giữa trang tối.

**Bốn chi tiết dễ bỏ sót**, đều đã xử lý:

- **`--brand-600` có hai vai trò xung khắc** ở chế độ tối: nền nút khi rê
  chuột cần đủ *tối* cho chữ trắng, chữ liên kết cần đủ *sáng* để đọc trên
  nền đen. Một giá trị không làm được cả hai → tách `--bs-link-color` ra.
- **Viền phải đổi sang màu sáng mờ.** Viền tối trên nền tối biến mất hoàn
  toàn và mọi thẻ card dính liền thành một mảng.
- **Bóng đổ phải đậm hơn hẳn.** Bóng của bản ngày hoàn toàn vô hình trên
  nền tối.
- **`--semantic-light`** là màu nền nhạt trong bảng Bootstrap; để nguyên
  thì mọi huy hiệu `text-bg-light` loé trắng giữa trang.

**Không làm mờ ảnh sản phẩm.** Mẹo hay gặp là hạ độ sáng ảnh ở chế độ tối;
ở cửa hàng bán hoa thì không được — màu hoa chính là thứ khách dựa vào để
chọn, và một bó hồng giảm sáng 20% là một bó hồng khác màu.

**Lưu bằng cookie, không lưu vào tài khoản:** khách vãng lai cũng phải dùng
được; đây là tuỳ chọn **theo thiết bị** (nền tối trên điện thoại ban đêm,
nền sáng trên máy tính ban ngày); và máy chủ đọc được cookie ngay khi dựng
HTML nên thẻ `<html>` đúng từ khung hình đầu tiên.

**"Theo hệ thống" cần JavaScript, và nói ra điều đó.** Máy chủ không biết
hệ điều hành đang để sáng hay tối, nên nó ghi `auto` và một script inline
trong `<head>` quy về giá trị cụ thể trước khi vẽ. Cách còn lại là chép
bảng màu tối thành hai bản (một cho `[data-scheme="toi"]`, một trong
`@media (prefers-color-scheme: dark)`) — và bản bị quên khi sửa sẽ tạo ra
hai chế độ tối khác nhau. Lựa chọn tay thì máy chủ xử lý hoàn toàn.

**Đo bằng máy, không bằng mắt.** Sau khi bật chế độ tối, quét mọi phần tử
có nền sáng (độ sáng > 0.75) trên từng trang. Kết quả: đúng **một** chỗ —
`.page-link` bị vô hiệu hoá, vì Bootstrap gán `--bs-pagination-disabled-bg:
#e9ecef` là mã màu cứng không đi qua bảng màu. Đã sửa và quét lại: 0.

## QĐ-81. Xoá tài khoản — ba bước, cố ý tách rời

```
1. bấm nút          → gửi thư, KHÔNG xoá gì
2. bấm liên kết     → mở trang xác nhận, KHÔNG xoá gì
3. gõ đúng một dòng → mới thật sự xoá
```

Gộp bất kỳ hai bước nào cũng mất một lớp: gộp 1-2 thì bấm một nút là mất
tài khoản; gộp 2-3 thì phần xem trước liên kết của Gmail, phần quét virus
của doanh nghiệp, hay một cú bấm nhầm cũng xoá được.

**Xác minh bằng liên kết ký số, không bằng OTP riêng.** Mã OTP cần một bảng
riêng cùng toàn bộ luật đi kèm — hạn dùng, đếm lần gõ sai, thời gian chờ
gửi lại, băm mã. Hệ thống **đã có** đúng bộ luật đó trong `EmailVerifier`;
viết bản thứ hai là hai bộ luật bảo mật phải giữ đồng bộ.
`URL::temporarySignedRoute` cho đủ ba thứ cần thiết mà không thêm bảng nào
và không thêm một dòng mã bảo mật tự viết nào.

**Chữ ký không thay cho phép kiểm quyền.** Nó chứng minh liên kết do máy
chủ phát ra, **không** chứng minh người đang cầm nó là chủ tài khoản: liên
kết bị chuyển tiếp, dán vào nhóm chat, hay lọt vào lịch sử trình duyệt máy
chung đều vẫn còn chữ ký hợp lệ. Vẫn phải hỏi người đang đăng nhập có đúng
là chủ liên kết không.

**Bắt gõ một dòng, không chỉ bấm "Đồng ý".** Hộp thoại xác nhận thì người
ta bấm theo phản xạ — đó là cú bấm thứ hai trong cùng một nhịp tay. Phải gõ
thì buộc dừng lại và làm một việc khác hẳn. Với thao tác không có đường lùi
thì cái khựng lại đó chính là thứ cần.

**Hai lời từ chối, cả hai đều có lý do nghiệp vụ:**

- **Quản trị viên dùng được cuối cùng** không tự xoá được — cùng luật với
  trang quản lý người dùng, chỉ khác là người thao tác chính là người bị xoá.
- **Còn đơn đang dở** thì chưa cho xoá. Đơn đã giao xong hay đã huỷ thì
  xong chuyện, nhưng đơn đang chuẩn bị hoặc đang giao là việc chưa xong
  giữa hai bên. Chặn **tạm thời**, không chặn vĩnh viễn.

**Khoá ngoại lo phần dọn dẹp**, không tự đi xoá từng bảng trong PHP. Mỗi
bảng đã khai ý định của nó trong migration; chép lại ý định đó vào PHP là
tạo bản sao thứ hai sẽ lệch — thêm một bảng mới mà quên sửa hàm xoá thì dữ
liệu cá nhân ở lại mà không ai biết.

**Màn hình xác nhận nói ĐÚNG CON SỐ.** "Bạn sẽ mất dữ liệu cá nhân" không
giúp ai quyết định gì; "xoá 4 đánh giá, 2 địa chỉ, giữ lại 15 đơn hàng" thì
có. Và nói trước rằng **đơn hàng được giữ lại** — người bấm xoá thường nghĩ
mọi dấu vết biến mất, biết điều đó sau khi xoá là quá muộn để đổi ý.

Trang Chính sách bảo mật đã được sửa theo: mục "Xoá tài khoản" trước đây
bảo khách gửi thư tay cho cửa hàng. **Một trang chính sách mô tả quy trình
đã không còn đúng là một lời hứa sai, và người đọc không có cách nào biết.**

## QĐ-82. Mọi con số trên trang Hồ sơ phải có đường đi tới

Trang Hồ sơ đếm bốn thứ: đơn hàng, sản phẩm yêu thích, địa chỉ, **đánh giá
đã viết**. Ba cái đầu bấm vào được; cái thứ tư là **ngõ cụt**.

Trang Chính sách bảo mật hứa người dùng "gỡ đánh giá của chính mình" được,
và điều đó đúng — nhưng nút gỡ chỉ nằm trên **trang sản phẩm**. Ai viết bốn
đánh giá cho bốn sản phẩm khác nhau phải nhớ ra đủ bốn rồi mở từng trang;
với đánh giá viết từ nửa năm trước thì đó là điều không làm nổi.

**Quyết định:** thêm trang "Đánh giá của tôi", và bổ sung đường tới **Ví
voucher** và **Lịch chăm cây** — hai trang đã tồn tại nhưng chỉ tới được
qua menu, tức là phải biết trước là chúng tồn tại.

**Không viết lại hàm xoá.** Trang mới chỉ liệt kê và trỏ về đúng route
`shop.reviews.destroy` sẵn có — hai đường xoá là hai bộ phép kiểm quyền
phải giữ đồng bộ.

Trang cũng nói thẳng khi một bài **đang bị cửa hàng ẩn**. Không nói thì
người viết vào trang sản phẩm không thấy bài của mình và tưởng hệ thống mất
dữ liệu.

## QĐ-83. Trang Hồ sơ chia thành ba mục, mỗi mục một địa chỉ

**Đo trước khi sửa** ở khổ 1440×900: trang cao **3748px — hơn bốn màn hình
cuộn** cho một trang cài đặt. Bảy khối xếp chồng nhau, và muốn đổi tuỳ chọn
nhận thư thì phải cuộn qua toàn bộ biểu mẫu đổi mật khẩu cùng danh sách
thiết bị, mỗi lần.

Dài không phải vấn đề duy nhất. **Bảy khối ngang hàng thì không khối nào
nói được nó quan trọng tới đâu**: ô đổi tên và nút xoá vĩnh viễn tài khoản
trông giống hệt nhau.

**Chia theo việc người dùng tới để làm:**

| Mục | Gồm | Cao sau khi chia |
|---|---|---|
| Thông tin (mặc định) | thông tin cá nhân | 1330px |
| Bảo mật | đổi mật khẩu → thiết bị đang đăng nhập → xoá tài khoản | 1950px |
| Tuỳ chọn | nền sáng/tối, thư thông báo | 1331px |

**4,2 màn hình → 1,5 màn hình** ở mục mặc định.

Ba khối trong mục Bảo mật theo đúng thứ tự một người xử lý khi nghi tài
khoản bị chiếm: đổi mật khẩu → xem ai đang đăng nhập → nếu tệ quá thì xoá
hẳn. Khối xoá đứng cuối, sau một khoảng cách rõ ràng.

**MỤC LÀ LIÊN KẾT, KHÔNG PHẢI TAB JAVASCRIPT.** Mỗi mục là một địa chỉ
riêng (`?muc=bao-mat`), nên: chạy được khi không có JavaScript; gửi được
đường dẫn cho người khác và lưu được dấu trang; bấm Back ra đúng mục vừa
xem; và **biểu mẫu lỗi thì `back()` đưa về đúng mục đó** vì địa chỉ trước
đó đã mang sẵn tham số. Tab JavaScript mất cả bốn điều trên, đổi lại chỉ
tránh được một lần tải trang — và sau khi tải lại, tab active quay về mặc
định, ném người dùng ra khỏi chỗ họ vừa đứng cùng với thông báo lỗi.

Khối tóm tắt và liên kết nhanh **hiện ở mọi mục**: đó là thứ người ta thật
sự tới trang này để dùng. Nhét nó vào một mục riêng là bắt bấm thêm một lần
cho việc phổ biến nhất.

Danh sách mục khai trong controller, vừa làm **danh sách trắng** cho tham
số `?muc=` (giá trị lạ quy về mục đầu tiên, không đổ trang lỗi) vừa là
nguồn duy nhất dựng thanh điều hướng.

### Lỗi cấu trúc phát hiện trong lúc đo

Bốn khối đang **lồng bên trong** khối "Đổi mật khẩu" — do một lần chèn
trước đó thiếu thẻ đóng. HTML vẫn cân bằng tổng số thẻ nên trình duyệt
không kêu gì và trang nhìn qua vẫn như thường; chỉ có viền và khoảng đệm
chồng lên nhau. Tách thành bảy partial làm lỗi kiểu đó không giấu được
nữa: mỗi tệp tự đóng đủ thẻ của mình.

**Bài kiểm tra đầu tiên tôi viết cho việc này thì sai.** Nó đếm số thẻ
`<div>` mở và đóng rồi so bằng nhau — và phép đếm đó **xanh trên chính bản
hỏng** (12 mở, 12 đóng), vì thẻ đóng thiếu ở giữa chỉ trôi xuống cuối chứ
không mất đi. Đã đổi sang hỏi đúng câu hỏi bằng XPath: *có `surface-card`
nào nằm trong `surface-card` khác không*. Kiểm chứng bằng cách tái dựng lại
đúng lỗi cũ — bài mới ngã, bài cũ thì không.

**Một bài kiểm tra không bắt được đúng lỗi nó nói mình canh còn tệ hơn
không có bài nào, vì nó tạo ra niềm tin sai.**

## QĐ-84. Thư "Xác nhận đơn hàng" chỉ đi khi đơn ĐÃ được xác nhận

Thư này trước đây gửi **ngay lúc khách bấm đặt hàng**, khi đơn còn ở
trạng thái `pending` — "Chờ xác nhận". Chưa ai ở cửa hàng nhìn thấy đơn,
chưa ai kiểm hàng còn hay hết, chưa ai xem địa chỉ có ship tới được không.

**"Chờ xác nhận" và "Đã xác nhận" là hai trạng thái khác nhau, và chỉ
trạng thái thứ hai mới đáng một lá thư.** Gửi sớm là nói với khách rằng
cửa hàng đã nhận lời — rồi nếu phải từ chối, khách đã cầm sẵn trong tay
một văn bản tên là "Xác nhận đơn hàng".

**Sáu trạng thái, ba cách xử lý:**

| Trạng thái | Thư |
|---|---|
| Chờ xác nhận | không gửi |
| **Đã xác nhận** | `OrderConfirmationMail` — biên nhận đầy đủ |
| Đang chuẩn bị | không gửi |
| Đang giao / Đã giao / Đã huỷ | `OrderStatusMail` — cập nhật ngắn |

"Đang chuẩn bị" im lặng là có chủ ý: đó là bước nội bộ (gói hàng, cắt
hoa), với khách không có gì mới so với "đã xác nhận". **Gửi thư cho mọi
bước nhỏ là cách nhanh nhất khiến người ta lọc thẳng thư của cửa hàng vào
thùng rác — và khi đó thư thật sự quan trọng cũng chung số phận.**

Việc chọn lá thư nào đặt trong `OrderMailer`, không đặt ở nơi gọi:
`OrderService` chỉ nói "đơn vừa đổi trạng thái", còn trạng thái nào xứng
lá thư nào là luật của tầng gửi thư.

Nhờ đổi này, `OrderConfirmationMail` được dùng đúng chỗ thay vì thành mã
chết. Hai câu trong thư đã sửa theo: tiêu đề "Cảm ơn bạn đã đặt hàng" →
"Cửa hàng đã xác nhận đơn của bạn", và "sẽ liên hệ để xác nhận trước khi
giao" → "sẽ liên hệ khi giao hàng" (câu cũ hẹn một việc vừa xong rồi).

Màn hình sau khi đặt cũng đổi từ "Xác nhận đơn **đã được gửi** tới ..."
thành "**Khi cửa hàng xác nhận đơn**, thư báo sẽ được gửi tới ...". Không
đổi thì khách mở hộp thư tìm một lá thư chưa tồn tại.

## QĐ-85. Sản phẩm có quy cách thì BẮT BUỘC chọn trước khi mua

Thẻ sản phẩm ở trang danh sách gửi thẳng biểu mẫu "Thêm vào giỏ" với đúng
`product_id` và không có `variant_id`. Không tầng nào chặn.

**Đo trên dữ liệu thật:** "Lưỡi hổ mini để bàn" có hai quy cách — Chậu sứ
trắng 180.000₫, Chậu gốm nâu 195.000₫. Thêm vào giỏ không kèm quy cách
cho ra một dòng `product_variant_id = NULL`, đơn giá 180.000₫.

Hai hỏng hóc:

1. **Cửa hàng không biết giao chậu nào.** Đơn ghi tên sản phẩm mà không
   nói quy cách. Người gói hàng phải gọi lại hỏi khách, hoặc đoán.
2. **Luôn tính giá rẻ nhất.** `base_price` bằng giá quy cách rẻ nhất, nên
   ai bỏ qua bước chọn cũng mua được quy cách đắt với giá quy cách rẻ.

**Chặn ở hai cổng, không phải một:**

- `CartService::assertPurchasable()` — cửa vào giỏ, dùng chung cho cả
  "Thêm vào giỏ" lẫn "Mua ngay" và mọi đường thêm hàng sau này.
- `OrderService::assertStillSellable()` — cổng cuối trước khi thành đơn
  thật. Giỏ có thể mang sẵn dòng thêm từ TRƯỚC khi có phép kiểm, và cửa
  hàng có thể mới thêm quy cách cho một sản phẩm trước đây bán trơn. Cổng
  cuối phải tự kiểm chứ không dựa vào việc cửa trước đã kiểm.

**Giao diện phải theo, nếu không phép kiểm chỉ là một lời từ chối.** Thẻ
sản phẩm của hàng có quy cách nay đưa khách qua bước chọn:

- **Không có JavaScript:** hai nút là liên kết thật dẫn tới trang sản phẩm
  ngay tại bảng chọn quy cách (`#chon-quy-cach`).
- **Có JavaScript:** mở hộp chọn ngay tại chỗ, không rời trang.

**Chữ trên nút giữ nguyên "Thêm vào giỏ" và "Mua ngay".** Đổi thành "Xem
chi tiết" là nói sai việc: khách vẫn đang mua hàng, chỉ là còn một lựa
chọn phải nêu.

Hộp thoại dùng lại `<form class="product-buy">` với đúng các thuộc tính
`data-add-to-cart` / `data-buy-now` mà `add-to-cart.js` đang nghe — nhờ
vậy phần thêm-không-tải-lại-trang, cập nhật số trong giỏ và thông báo đều
chạy sẵn, không có bản sao thứ hai để sau này lệch đi.

## QĐ-86. Tô sáng quy cách phải đặt lên khung, không lên ô radio

`initVariantPicker()` gắn class `is-selected` lên chính các thẻ
`<input type="radio">` — mà chúng mang class `visually-hidden`. Quy tắc
`.variant-option.is-selected` do đó **không bao giờ khớp**.

Khách bấm sang quy cách khác thì **giá đổi nhưng khung tô sáng đứng yên**
ở quy cách đầu tiên. Hai thông tin mâu thuẫn trên cùng màn hình, và không
có cách nào biết cái nào đúng.

**Gộp phần tô sáng về một nơi** — một trình lắng nghe uỷ quyền trên
`document`, phục vụ cả bảng cố định ở trang chi tiết lẫn bảng dựng động
trong hộp thoại. Bảng thứ hai chưa tồn tại lúc trang tải xong, nên cách
gắn-từng-bảng sẽ bỏ sót nó.

Thêm `.variant-option:has(input:checked)` trong CSS làm cùng việc đó mà
không cần JavaScript; giữ cả hai là có chủ ý, phần JavaScript lo cho
trình duyệt cũ chưa hỗ trợ `:has()` — ô radio thật thì đã bị ẩn đi.

Bỏ `aria-pressed`: nó dành cho **nút bật/tắt**. Đây là nhóm radio, trình
đọc màn hình đã tự đọc đúng trạng thái từ `checked`; thêm vào là gán cho
phần tử một vai trò nó không có.

### Ghi chú về cách đo

Đo màu bằng `getComputedStyle` ngay sau cú bấm cho kết quả **ngược với sự
thật**: class đã dời nhưng màu vẫn là màu cũ. Nguyên nhân là
`transition: background-color 140ms` — trong khung xem không được vẽ, các
transition bị đóng băng và giá trị tính toán treo lại ở đầu đoạn chuyển.
Tắt transition rồi đo lại mới ra đúng. **Một phép đo cho kết quả lạ thì
phải nghi chính phép đo trước, chứ không kết luận ngay là mã sai.**

## QĐ-87. "Còn hàng" phải xét cả quy cách

`Product::inStock()` chỉ nhìn cột tồn kho của **chính sản phẩm**. Với
hàng có quy cách thì đó không phải thứ khách mua — họ mua một quy cách cụ
thể, và mỗi quy cách có kho riêng.

Mọi quy cách bán hết mà cột kia còn số dương thì trang hiện huy hiệu "Còn
hàng", nút "Thêm vào giỏ" vẫn sáng, còn bảng chọn quy cách thì mọi ô đều
bị vô hiệu hoá. **Khách bấm mãi không được và không có gì giải thích.**

Thêm `Product::isPurchasable()` thay vì sửa thẳng `inStock()`: hàm cũ còn
được dùng ở chỗ chỉ quan tâm tới kho của chính sản phẩm, và đổi ý nghĩa
của nó là đổi hành vi ở những nơi chưa xem xét tới.

Hộp chọn quy cách cũng chọn sẵn **quy cách còn hàng đầu tiên**, không
phải quy cách đầu tiên: chọn cứng cái đầu mà nó đã hết thì ô radio bị vô
hiệu hoá, trình duyệt không gửi `variant_id` nào, và máy chủ từ chối.

## QĐ-88. Cước giao hàng do MÁY CHỦ hỏi GHN, không do trình duyệt tính

Tài liệu `lab05 - shipping.pdf` cho JavaScript tính cước rồi ghi tổng
tiền vào một ô ẩn (`total_price_input`), và máy chủ lấy con số đó làm
tiền phải trả.

**Cách đó nghĩa là trình duyệt quyết định giá.** Sửa một dòng trong
DevTools là được giao miễn phí đi Cà Mau, và không có bản ghi nào cho
thấy chuyện đã xảy ra.

**Quyết định:** trình duyệt gửi lên **mã địa chỉ**, máy chủ tự hỏi GHN ra
tiền — cùng nguyên tắc đã áp cho mã giảm giá (QĐ-51). Con số hiện trên
màn hình chỉ để xem trước; `CheckoutBasket::baseShippingFee()` gọi
`ShippingQuote` và tính lại từ đầu ở thời điểm ghi đơn.

Biểu mẫu thanh toán **không có ô tiền nào**. Bài kiểm tra gửi kèm
`shipping_fee=0`, `total_price=1000`, `grand_total=1000` và khẳng định
đơn vẫn ghi 42.900₫.

**Có đường lùi khi GHN im lặng.** GHN sập, hết hạn mức, địa chỉ chưa hỗ
trợ — cả ba đều có thật. Khi đó lùi về bảng phí theo tỉnh
(`ShippingRates`), **không** chặn khách đặt hàng và **không** cho giao
miễn phí. Bảng phẳng kém chính xác hơn nhưng luôn có một con số.

## QĐ-89. Không tạo lại bảng `orders`, chỉ thêm cột GHN

Tài liệu viết migration `create` cho `orders` và `order_items` vì nó giả
định dự án trống. Dự án này đã có cả hai với 39 đơn thật và đầy đủ nghiệp
vụ (mã đơn, máy trạng thái, mã giảm giá, chụp giá, dòng thời gian). Chạy
lại `create` là mất sạch.

Thêm năm cột: `ghn_order_code`, `ghn_total_fee`, `to_district_id`,
`to_ward_code`, `shipping_status`.

**Giữ CẢ tên chữ lẫn mã số.** `shipping_province/_district/_ward` là thứ
con người đọc; `to_district_id/to_ward_code` là thứ GHN hiểu. Đơn hàng là
chứng từ phải đọc được sau nhiều năm, kể cả khi GHN đổi mã hoặc cửa hàng
đổi đơn vị vận chuyển — bỏ tên chữ thì nhân viên mở đơn cũ chỉ thấy
`to_district_id = 1482` và không biết đó là đâu.

**`ghn_total_fee` tách khỏi `shipping_fee`.** Cột kia là tiền cửa hàng
**thu** của khách; cột này là tiền cửa hàng **trả** cho GHN. Hai con số
lệch nhau mỗi khi miễn phí giao cho đơn lớn, và chỉ giữ cả hai mới biết
tháng này bù lỗ bao nhiêu tiền ship.

**`shipping_status` tách khỏi `status`.** `status` là việc của cửa hàng
(chờ xác nhận → đã xác nhận → đang chuẩn bị), do người bấm.
`shipping_status` là việc của GHN (`ready_to_pick`, `delivering`…), do
GHN báo về. Gộp làm một thì máy trạng thái của đơn phải hiểu cả những giá
trị không ai trong cửa hàng đặt ra được.

## QĐ-90. Ba bẫy trong dữ liệu GHN, phát hiện khi gọi thật

**1. Có hai bản ghi "Hà Nội".** `2002 = "Hà Nội 02"` (0 quận/huyện, bản
ghi rác) và `201 = "Hà Nội"` (30 quận/huyện, bản thật). Cả hai đều
`Status = 1` — không có cờ nào phân biệt. Chọn nhầm bản đầu thì mọi lời
gọi tiếp theo trả về `data: null` kèm `code: 200`: "thành công" nhưng
rỗng, không gợi ý gì rằng mình đã chọn sai.

Lệnh `php artisan ghn:tra-dia-chi` in kèm **số quận/huyện của mỗi tỉnh**
để chuyện đó lộ ra ngay.

**2. Lời gọi lấy toàn bộ quận/huyện không bao giờ hoàn tất.** Bản đầu lọc
bản ghi rác bằng cách gọi `/master-data/district` không kèm tham số (727
bản ghi) rồi suy ra tập tỉnh hợp lệ. Nghe hợp lý — **đo ra thì 40 giây
vẫn đang nhận dữ liệu rồi hết giờ**. Nó nằm ngay trên đường mở trang
thanh toán. Đã đổi sang danh sách chặn cố định trong config, và giao diện
nói thẳng khi một tỉnh không có quận/huyện nào.

**3. Danh mục tỉnh của GHN và của hệ thống lệch nhau.**

```
App\Services\Shop\Provinces  →  34 tỉnh, tên SAU sáp nhập 2025
Giao Hàng Nhanh (dev)        →  63 tỉnh, tên TRƯỚC sáp nhập
                                 chỉ 28/63 trùng nhau
```

Giữ nguyên `Rule::in(Provinces::all())` thì khách chọn từ ô GHN xong bị
báo "tỉnh/thành không hợp lệ" — cho **hơn nửa số tỉnh trong nước**.

**Quyết định:** nguồn nào chứng minh được địa chỉ giao được thì nguồn đó
nói. Có mã GHN → chấp nhận tên GHN trả về (chính GHN vừa xác nhận đó là
nơi họ giao tới). Không có mã GHN → giữ `Rule::in(...)`, vì lúc đó không
còn gì kiểm giúp. **Không dựng bảng ánh xạ 63→34**: bảng đó phải sửa mỗi
lần một trong hai bên đổi danh mục, và bên bị quên sẽ lặng lẽ chặn mất
một tỉnh.

## QĐ-91. Tạm nghỉ gọi lại khi GHN không phản hồi

Khi GHN không nhận kết nối, mỗi lần mở trang thanh toán là một lần chờ
hết 15 giây rồi mới hỏng — cho một dịch vụ chỉ phụ trách phần phí giao.

Nhớ "vừa hỏng" trong **60 giây** rồi thôi. Đo được: lần đầu 10,1s, lần
sau **0,0s**.

Đủ ngắn để GHN sống lại là dùng được ngay, đủ dài để một trục trặc không
thành hàng trăm lần chờ. **Không đệm chính câu trả lời lỗi 24 giờ như câu
trả lời đúng** — làm vậy là biến một trục trặc mười giây thành một trục
trặc một ngày.

## QĐ-92. Hai chỗ hiện tiền phải nói cùng một con số

Bảng tóm tắt bên phải dựng ở **máy chủ** từ mã địa giới trong phiên, mà
lúc khách còn đang chọn thì phiên chưa có gì — nên nó hiện mức tạm theo
tỉnh (50.000₫) trong khi hộp ngay dưới ô địa chỉ đã có cước GHN thật
(42.900₫).

**Hai con số tiền khác nhau trên cùng một màn hình là lỗi nặng hơn cả
việc hiện sai một con số:** khách không biết tin cái nào, và mất tin vào
cả trang. JavaScript nay đồng bộ cả hai (chỉ để hiển thị; máy chủ vẫn
tính lại khi ghi đơn).

Cùng loại lỗi ở nhãn giải thích: nó luôn lấy từ bảng vùng kể cả khi phí
đến từ GHN, nên đơn về Bắc Từ Liêm — cách cửa hàng 2km — hiện *"Phí giao
hàng (Tỉnh xa) 42.900₫"*. Sai gấp đôi: địa chỉ không xa, và bảng vùng
không phải nơi ra con số đó. **Một lời giải thích sai còn tệ hơn không
giải thích.**

## QĐ-93. Vận đơn tạo bằng tay ở trang quản trị, không tự động

Tạo vận đơn là **cam kết với GHN**: họ cử người tới lấy hàng và tính tiền
cửa hàng. Làm tự động lúc khách bấm đặt nghĩa là mọi đơn đặt nhầm, đơn
hết hàng, đơn khách huỷ sau ba phút đều thành một chuyến xe có thật.

Cửa hàng xác nhận đơn và gói xong rồi mới bàn giao. `GHNOrderService`
chặn tạo lần hai khi đơn đã có mã vận đơn — bấm hai lần vì trang chậm là
đủ để GHN tính tiền hai chuyến.

`cod_amount` đọc từ `payment_status` của chính đơn, **không nhận cờ từ
nơi gọi**: truyền sai theo hướng "đã trả rồi" nghĩa là shipper không thu
tiền, hàng đi mà cửa hàng không nhận được đồng nào.

Huỷ vận đơn **không xoá** `ghn_order_code` — mã đó là bằng chứng cửa hàng
đã từng bàn giao và đã huỷ, cần để đối soát với hoá đơn GHN.

## QĐ-94. Đo trước, tối ưu sau — và số đo bác bỏ hầu hết giả định

Bài tư vấn về tốc độ tải trang mở đầu bằng câu đúng nhất trong cả bài:
*"Trước tiên phải biết cái gì đang chậm."* Nên việc đầu tiên là dựng lệnh
`php artisan do:hieu-nang` và đo, chứ không làm theo danh sách 13 kỹ thuật.

**Số đo bác bỏ tiền đề của bài viết:**

| Bài viết giả định | Đo được ở dự án này |
|---|---|
| Trang mất 1–3 giây | Dựng trang **12–137 ms** |
| Truy vấn nặng ~1,2 giây | Truy vấn chậm nhất **12,5 ms** |
| Recommendation là tính toán nặng | Không có truy vấn nào quá 13 ms |

**Nguyên nhân thật, bài viết không nhắc tới: OPcache đang TẮT.** PHP biên
dịch lại ~7000 tệp ở mỗi request. Đo qua HTTP thật:

```
                     TTFB trước    TTFB sau
Trang chủ              781 ms   →   135 ms
Chi tiết sản phẩm      776 ms   →   114 ms
Tải xong toàn trang   2947 ms   →   260 ms
```

**Một dòng cấu hình, giảm 82–91%** — nhiều hơn mọi kỹ thuật trong bài
cộng lại.

### Vì sao chia nhỏ thành nhiều AJAX sẽ làm CHẬM HƠN

Đây là điểm quan trọng nhất của việc đo trước. Với TTFB 781 ms, tách một
trang 77 ms thành 5 lời gọi AJAX là **thêm 4 lần trả giá 781 ms**. Bài
viết có cảnh báo ở mục 5 ("đừng gửi 4 request nặng cùng lúc") nhưng không
biết chi phí mỗi request lớn tới đâu.

Sau khi bật OPcache, TTFB còn ~100 ms và cả trang dựng xong trong 132 ms —
**không còn gì để chia nhỏ**. Thêm skeleton loading vào một trang đã hiện
sau 132 ms chỉ là thêm một lần nhấp nháy.

### Đánh giá 13 mục của bài viết

| Mục | Phù hợp? | Vì sao |
|---|---|---|
| 1–3. Progressive/Skeleton/Lazy theo viewport | **Không** | Trang đã dựng xong trong 132 ms |
| 4–5. Tách request nặng, chia priority | **Không** | Không có request nặng nào |
| 6–7. Cache recommendation, dữ liệu ít đổi | **Không** | Truy vấn chậm nhất 12,5 ms |
| 8. Tối ưu database | **Không** | Không có N+1, không truy vấn chậm |
| **9. Ảnh** | **CÓ** | 52 JPG, 5,59 MB, ảnh 900px vẽ ở 117px |
| 10. Tách JS theo trang | Ít giá trị | Bundle 106 KB, phần lớn là Bootstrap |
| **11. HTTP caching** | **CÓ** | Không có header cache nào |
| 12. Stale-while-revalidate | **Không** | Không có tính toán nặng để làm mới nền |
| **13. Đừng làm phức tạp lên** | **ĐÚNG NHẤT** | Xem bảng trên |

Mục 13 — *"đừng biến project thành 20 AJAX requests chỉ để trông giống
Facebook"* — hoá ra là lời khuyên giá trị nhất, vì số đo cho thấy 11/13
mục còn lại đang chữa một căn bệnh dự án không mắc.

## QĐ-95. Ảnh: sinh sẵn bản WebP nhiều cỡ, không đổi cỡ lúc chạy

Đo được: 52 ảnh JPG, tổng 5,59 MB, bề ngang trung bình 882px — nhưng thẻ
sản phẩm vẽ chúng ở 117–300px. Một ảnh 900×1125 hiển thị ở 117×146 là tải
về **gấp ~58 lần** số điểm ảnh cần dùng.

Trên máy tính nối LAN thì không ai thấy; trên điện thoại 4G thì đó là
toàn bộ thời gian chờ.

**Kết quả:** trang danh sách sản phẩm **1152 KB → 339 KB (nhẹ hơn 71%)**.

**Ba quyết định trong cách làm:**

**Sinh sẵn bằng lệnh, không đổi cỡ lúc có request.** Đổi cỡ khi chạy cần
một lớp đệm riêng và biến mỗi ảnh chưa đệm thành một lần chờ. `anh:toi-uu`
chạy một lần, chạy lại được, và bỏ qua ảnh chưa đổi.

**Đọc kích thước từ manifest, KHÔNG từ tệp ảnh.** Cách hiển nhiên là gọi
`getimagesize()` lúc dựng trang để biết `width`/`height`. Nhưng trang danh
sách có 12 thẻ, nên đó là **12 lần mở tệp cho mỗi lượt xem** — thay một
vấn đề hiệu năng bằng một vấn đề khác. Có bài kiểm tra riêng canh điều
này: manifest có kích thước còn tệp ảnh thì không tồn tại.

**`<picture>` chứ không phải `<img srcset>`.** Trình duyệt cũ không đọc
được WebP; đặt WebP thẳng vào `src` là ảnh vỡ không có gì thay thế. Với
`<picture>`, trình duyệt tự bỏ qua `<source>` nó không hiểu và rơi xuống
ảnh JPG gốc — không cần một dòng JavaScript nào.

**`width`/`height` là bắt buộc, kể cả khi CSS đã định cỡ.** Thiếu chúng,
trình duyệt dựng trang với chiều cao ảnh bằng 0 rồi đẩy mọi thứ xuống khi
ảnh về: người đang đọc bị nhảy chữ, người đang bấm thì bấm nhầm nút vừa
dịch chỗ.

### Cái bẫy trong thư viện ảnh sản phẩm

Ảnh chính ở trang chi tiết đổi được khi bấm ảnh nhỏ, và JavaScript cũ chỉ
đặt `img.src`. Khi bọc trong `<picture>`, **trình duyệt ưu tiên `<source>`
hơn `src`** — nên đổi `src` mà không đổi `<source srcset>` thì ảnh đứng
yên, và không có lỗi nào để lần ra.

Đã kiểm chứng bằng cách gỡ đúng dòng sửa `<source>`: bấm ảnh nhỏ, ảnh lớn
**không đổi** (`DOI_DUOC: false`).

## QĐ-96. Header cache đặt ở .htaccess, không đặt ở middleware

Đo được: **không có header cache nào**. Mỗi lần khách mở một trang khác,
trình duyệt tải lại toàn bộ CSS (336 KB), JS (106 KB) và mọi ảnh.

Không đặt được ở middleware của Laravel: những tệp này do Apache phục vụ
**thẳng**, request không đi qua PHP nên middleware không bao giờ chạm tới.

**Ba mức khác nhau, vì ba loại tệp có vòng đời khác nhau:**

- `/build/*.css|js` → **1 năm, `immutable`**. Vite đặt tên có mã băm
  (`app-CpFXTyiy.css`): đổi nội dung là đổi tên tệp, nên bản trong đệm
  không bao giờ cũ.
- Ảnh `/storage` → **1 tuần, không `immutable`**. Tên ảnh KHÔNG có mã
  băm: cửa hàng thay ảnh sản phẩm thì tên giữ nguyên. `immutable` ở đây
  nghĩa là ảnh mới không bao giờ lên.
- HTML → **không đệm** (mặc định của Laravel, đúng).

## QĐ-97. Công cụ chẩn đoán nói sai còn hại hơn không có

`do:hieu-nang` in ra tình trạng OPcache. Bản đầu đọc
`opcache_get_status()` — và **luôn báo "CHƯA BẬT"**, kể cả khi máy chủ web
đang bật.

Lý do: lệnh chạy ở dòng lệnh, mà `opcache.enable_cli` mặc định tắt — và
đó là đúng, vì mỗi lần gọi artisan là một tiến trình mới, đệm chưa kịp
dùng đã bị huỷ.

Phải đọc `ini_get('opcache.enable')` — **thiết lập**, thứ áp cho cả máy
chủ web — chứ không đọc **trạng thái tiến trình hiện tại**.

Một công cụ chẩn đoán báo động giả sẽ khiến người đọc đi sửa thứ không
hỏng, và mất lòng tin vào cả những cảnh báo đúng của nó.

## QĐ-98. Máy chủ không chậm — 800 ms nằm ngoài mã nguồn

Bài nhận xét về tốc độ giả định trang mất **1–3 giây** vì máy chủ làm
quá nhiều việc trong một request, rồi đề xuất 13 kỹ thuật, trong đó nặng
nhất là chia trang thành nhiều lời gọi AJAX.

**Đo trước đã** (lệnh `php artisan do:hieu-nang`):

| | Trước |
|---|---|
| Máy chủ dựng trang | 12–137 ms |
| Truy vấn chậm nhất toàn hệ thống | **12,5 ms** |
| TTFB đo bằng curl | **~960 ms** |
| Tải xong hoàn toàn | **2947 ms** |

Ba con số đầu và con số thứ tư nói hai câu chuyện khác nhau. Máy chủ
**không** chậm — nó dựng xong trang trong 137 ms rồi ngồi chờ. Phần
800 ms còn lại nằm ngoài mã nguồn.

**Nguyên nhân thật:** PHP chưa bật OPcache, nên **biên dịch lại ~7000
tệp ở mỗi request**. Bật lên:

```
TTFB: ~960 ms  →  ~180 ms   (giảm 81%, không sửa một dòng mã)
```

Nếu làm theo thứ tự bài viết đề xuất — chia AJAX, thêm skeleton, cache
recommendation — thì công sức đổ vào việc giấu một độ trễ mà nguyên nhân
là một dòng `;` trong `php.ini`. Và sau khi làm xong, TTFB vẫn 960 ms,
vì AJAX cũng phải đi qua đúng cái PHP chưa có OPcache đó.

**Nguyên tắc rút ra:** một lời khuyên tối ưu chỉ đúng khi giả định của
nó đúng. Kiểm giả định rẻ hơn nhiều so với làm theo rồi phát hiện sai.

## QĐ-99. Nén nội dung trước khi cắt nhỏ nó

Sau khi sửa OPcache, mục nặng nhất còn lại là **CSS 336 KB** — nặng hơn
JavaScript và phông chữ cộng lại. Gói Bootstrap chiếm phần lớn.

Hai hướng xử lý:

1. **Cắt bớt CSS không dùng** (PurgeCSS). Rủi ro thật: nhiều lớp chỉ
   xuất hiện khi JavaScript thêm vào lúc chạy (modal, dropdown, toast),
   nên công cụ quét mã tĩnh không thấy và cắt nhầm — hỏng âm thầm, chỉ
   lộ ra khi khách bấm.
2. **Bật nén.** Không rủi ro gì.

Đo được:

```
CSS   336 KB  →   49 KB
JS    106 KB  →   31 KB
HTML  111 KB  →   17 KB
────────────────────────
TỔNG  544 KB  →  100 KB   (giảm 82%)
```

Nén lấy được ~85% phần lợi mà không đụng vào mã. Cắt CSS chỉ nên tính
tới sau, nếu 49 KB vẫn còn là vấn đề.

**Bẫy đã mắc phải khi cấu hình:** `AddOutputFilterByType` do **mod_filter**
cung cấp, không phải mod_deflate — bọc trong `<IfModule mod_deflate.c>`
là bảo vệ nhầm mô-đun, và Apache trả lỗi 500 cho toàn bộ site.

**Bẫy thứ hai, âm thầm hơn:** Apache 2.4 gửi tệp `.js` với kiểu
`text/javascript`, không phải `application/javascript`. Liệt kê thiếu nó
thì CSS và HTML được nén còn **JavaScript thì không** — nhìn bảng tổng
tưởng đã xong, phải đo từng loại tệp mới thấy 105 KB vẫn nguyên.

## QĐ-100. Phần tối ưu ảnh tự nó tạo ra một N+1

Sau khi thêm `ResponsiveImage` (sinh WebP nhiều kích cỡ), số truy vấn
mỗi trang **tăng vọt**:

```
/san-pham :  12  →  36 truy vấn
Trang chủ :  26  →  64 truy vấn
```

Đếm theo hình dạng câu truy vấn: **25 câu `select * from cache`** trong
một lần mở trang, chiếm 25 trong tổng 36.

Nguyên nhân: dự án đặt `CACHE_STORE=database`, nên mỗi
`Cache::rememberForever` là một câu SQL thật. `ResponsiveImage` đọc
manifest **một lần cho mỗi ảnh**, và một trang danh sách có 12 thẻ.

Trớ trêu: cái đệm sinh ra để **tránh** đọc tệp, lại đổi một lần đọc tệp
lấy 25 lần đi vòng qua cơ sở dữ liệu.

**Hai việc phải làm cùng nhau:**

1. Nhớ manifest trong thuộc tính của đối tượng (đọc một lần mỗi request).
2. Đăng ký `ResponsiveImage` là **singleton**. Thiếu bước này thì bước 1
   vô dụng: `app(ResponsiveImage::class)` tạo đối tượng MỚI mỗi lần gọi,
   và component gọi nó một lần cho mỗi ảnh.

Kết quả: `/san-pham` **36 → 13 truy vấn**.

## QĐ-101. Kỹ thuật nào của bài viết ÁP DỤNG, kỹ thuật nào KHÔNG

| Đề xuất | Quyết định | Vì sao |
|---|---|---|
| Lazy load ảnh theo viewport | **đã có** | 12/12 ảnh `loading="lazy"` |
| Ảnh WebP + srcset | **đã làm** | 12 ảnh: 1003 KB → 286 KB (−71%) |
| HTTP caching | **đã làm** | tệp băm: `max-age=1 năm, immutable` |
| Nén gzip | **đã làm** | 544 KB → 100 KB |
| Cache dữ liệu ít đổi | **đã có** | danh mục GHN đệm 24 giờ |
| Tối ưu truy vấn | **đã làm** | sửa N+1 ở ResponsiveImage |
| Progressive/AJAX từng phần | **KHÔNG** | máy chủ dựng trang 12–137 ms |
| Skeleton loading | **KHÔNG** | không có gì để chờ |
| Precompute recommendation | **KHÔNG** | truy vấn chậm nhất 12,5 ms |
| Chia nhỏ JavaScript | **KHÔNG** | 31 KB sau nén, chia ra tốn thêm request |

**Bảy trong mười ba kỹ thuật không áp dụng**, vì chúng giải quyết một
vấn đề dự án này không có: chúng giấu độ trễ của máy chủ, mà máy chủ ở
đây đã nhanh.

Chính bài viết cũng cảnh báo điều đó ở mục 13: *"Đừng biến project thành
20 AJAX requests chỉ để trông giống Facebook."* Với dữ liệu đo được,
cảnh báo đó áp cho hầu hết phần còn lại của chính bài viết.

**Kết quả cuối** (trang danh sách sản phẩm, Apache):

```
TTFB          966 ms  →  154 ms
Tải xong     2947 ms  →  364 ms
Lần đầu       686 KB  →  ~240 KB
Lần sau kế             →   20 KB
```

---

## QĐ-102. Phí giao là 0₫ khi chưa biết giao tới đâu

Trước đây trang giỏ hàng hiện **50.000₫** kèm chữ "tạm tính" ngay khi
khách vừa bỏ món đầu tiên vào giỏ. Con số đó là mức của vùng mặc định
("Tỉnh xa") — tức là **mức CAO NHẤT trong bảng cước**, dựng ra cho một
địa chỉ chưa ai nhập.

Khách ở nội thành Hà Nội nhìn thấy 50.000₫ trong khi cước thật của họ là
42.900₫. Chữ "tạm tính" không cứu được: người ta đọc **con số** trước, và
phần lớn không đọc chú thích bên cạnh.

**Quyết định:** chưa có địa chỉ thì phí giao là `0.00`, và nhãn nói rõ
*"nhập địa chỉ để tính phí giao"*. Không đoán, không dựng mức xấu nhất.

`CheckoutBasket::baseShippingFee()` và `shippingDiscount()` đều trả
`0.00` khi `hasDestination()` sai — cả hai, vì trả 0 ở một chỗ mà không
trả ở chỗ kia thì các dòng không cộng lại đúng bằng dòng tổng.

**Bẫy đã sập một lần:** endpoint hỏi cước của GHN có một nhánh dự phòng
đọc `$basket->shippingFee()` khi GHN không trả lời. Sau thay đổi này,
nhánh đó trả 0₫ — tức là báo "miễn phí giao" mỗi khi GHN hỏng. Đã sửa
thành gọi thẳng `ShippingRates::feeFor(null)`: chỗ dự phòng cần **một
mức cước**, không cần "cước của giỏ hiện tại".

---

## QĐ-103. "Theo hệ thống" bỏ khỏi nút chọn, nhưng vẫn là mặc định

Ô chọn nền sáng/tối từng có ba lựa chọn. Trên màn hình, "Theo hệ thống"
không nói được nó làm gì: bấm vào và kết quả trông y hệt "Nền sáng"
(hoặc y hệt "Nền tối"). Một lựa chọn thứ ba gây phân vân mà không thêm
khả năng nào.

**Quyết định:** bỏ khỏi danh sách CHỌN, **giữ lại làm giá trị mặc định**.

Hai câu hỏi khác nhau nên tách thành hai hàm:

| Hàm | Trả lời câu hỏi |
|---|---|
| `DisplayScheme::choices()` | *hiện nút nào cho người dùng?* → sáng, tối |
| `DisplayScheme::all()` | *chấp nhận giá trị nào?* → auto, sáng, tối |

Gộp làm một là **đẩy người cũ sang nền sáng**: cookie `auto` của người đã
vào trang từ trước sẽ bị coi là rác, dù máy họ đang để tối. `current()`
và `cookie()` dùng `all()`; controller dùng `choices()` — auto là trạng
thái máy chủ đặt, không phải thứ ai đó gửi lên.

---

## QĐ-104. Nền tối: dư tương phản không phải là tốt hơn

Bản đầu đặt nền `#121511` — gần như đen. Đo lại:

| | Trước | Sau |
|---|---|---|
| Màu nền | `#121511` | `#1f241d` |
| Độ sáng nền | 0,71% | 1,64% |
| Tương phản với chữ | 15,1:1 | 13,0:1 |
| Chuẩn AAA cần | 7:1 | 7:1 |

15,1:1 là **hơn gấp đôi mức AAA yêu cầu**, và phần dư đó không đổi lấy
điều gì ngoài cảm giác gắt: chữ sáng "loang" trên nền gần đen, mắt phải
liên tục điều tiết giữa hai cực. Sau khi nâng, vẫn còn 13,0:1 — dư rộng
so với chuẩn — mà nền có sắc rõ ràng và các thẻ tách khỏi nền dễ nhìn.

Ba mức nền giữ nguyên khoảng cách giữa chúng, để phần "nổi khối" của
giao diện không bị dẹt đi. Viền, nhãn trạng thái và bóng đổ nâng theo:
để nguyên thì chúng chìm vào nền mới.

---

## QĐ-105. Hộp chọn quy cách chỉ hiện ĐÚNG nút vừa bấm

Sản phẩm có quy cách thì phải chọn trước khi mua (QĐ-85), và hộp chọn
hiện ra sau khi bấm. Bản trước hộp đó luôn có **cả hai** nút "Thêm vào
giỏ" và "Mua ngay", bất kể khách vừa bấm cái nào.

Đó là hỏi lại một câu khách vừa trả lời. Tệ hơn: nó mở đường cho một
kết quả khách không yêu cầu — bấm "Mua ngay" rồi bấm nhầm "Thêm vào giỏ"
trong hộp thì họ nhận về một món trong giỏ thay vì trang thanh toán.

**Quyết định:** hộp chỉ hiện nút tương ứng, tiêu đề nói rõ đang làm gì
(*"Mua ngay: Bó tulip Hà Lan"*), và nút đó được đặt tiêu điểm sẵn.

Nút kia phải `hidden` **và** `disabled`. Chỉ ẩn là chưa đủ: `display:
none` không ngăn một nút gửi biểu mẫu nếu có gì đó kích hoạt nó, và
`disabled` mới là thứ trình duyệt thật sự tôn trọng.

---

## QĐ-106. Sửa giỏ hàng không tải lại trang — máy chủ vẫn là nơi vẽ

**Điều này KHÔNG mâu thuẫn với QĐ-101**, nơi đã kết luận "chia trang
thành nhiều lời gọi AJAX: **KHÔNG**". Hai việc khác nhau:

| | QĐ-101 bác bỏ | QĐ-106 làm |
|---|---|---|
| Mục đích | giấu độ trễ máy chủ | giữ vị trí cuộn và bối cảnh |
| Lý do bác bỏ/làm | máy chủ dựng trang 12–137 ms, không có độ trễ nào để giấu | tải lại trang ném khách về đầu danh sách |
| Phạm vi | tách trang thành nhiều request | vài nút mà tải lại là mất chỗ đang đứng |

Bốn thao tác được chuyển: **sửa số lượng**, **xoá món**, **tích chọn
món** trong giỏ, và **nút tim yêu thích** trên thẻ sản phẩm.

**Luật bất di bất dịch: JavaScript KHÔNG cộng trừ một con số tiền nào.**

Đổi một dòng trong giỏ làm đổi thành tiền của dòng, tạm tính, giảm giá,
phí giao, tổng cộng, số món đang chọn và cả danh sách phụ kiện mua kèm.
Trả về từng con số rồi để trình duyệt ráp lại là **chép luật tính tiền
sang JavaScript** — ngay lần sửa cách tính khuyến mại đầu tiên, hai bên
lệch nhau, và bên khách hàng nhìn thấy là bên sai.

Vì vậy máy chủ **vẽ lại cả khối giỏ** (`shop/cart/partials/noi-dung`) và
trả về HTML; trình duyệt chỉ thay chỗ. Con số hiện ra sau khi bấm luôn
là con số máy chủ vừa tính, y như khi tải lại cả trang.

**Bốn điều phải giữ, mỗi điều có một cách hỏng riêng:**

1. **Đường không-JavaScript nguyên vẹn.** Mọi thao tác vẫn là `<form
   method="POST">` thật tới đúng route thật. `CartLiveTest` kiểm **cả
   hai** đường cho từng thao tác — nhánh dễ hỏng không phải nhánh JSON
   mà là nhánh cũ, vì trình duyệt của người viết code luôn có JavaScript.

2. **Gắn sự kiện theo kiểu uỷ quyền.** Cả khối bị thay mới sau mỗi lần
   bấm, nên listener gắn thẳng vào từng nút sẽ chết ngay sau thao tác
   đầu tiên.

3. **Một lượt một.** Bấm xoá hai dòng thật nhanh sẽ có hai câu trả lời
   về không theo thứ tự, và câu về sau (vẽ theo trạng thái cũ hơn) ghi
   đè câu về trước — dòng vừa xoá hiện lại.

4. **Quyền kiểm ở nhánh mới nữa.** Thêm một đường vào hàm là thêm một
   đường phải kiểm quyền, và đường mới là đường dễ quên.

**Một lỗi có sẵn lộ ra khi làm việc này:** khối "Có thể bạn cần thêm"
nằm ngay trên trang giỏ hàng và đi qua route thêm-vào-giỏ, vốn đã không
tải lại trang từ trước. Nghĩa là bấm thêm phụ kiện ở đó **chưa bao giờ**
làm danh sách hàng bên cạnh cập nhật — món vừa thêm không hiện ra, tổng
tiền không nhúc nhích, và khách bấm lại lần nữa vì tưởng hụt. Thêm route
`GET /gio-hang/khoi` (chỉ đọc và vẽ) để nghe sự kiện `cart:added` rồi vẽ
lại.

**Nút tim ở CHÍNH trang "Yêu thích" cố ý KHÔNG chặn.** Ở nơi khác, bấm
tim chỉ đổi một icon. Ở đó, nó làm sản phẩm rời khỏi danh sách — kéo
theo phân trang và trạng thái "danh sách trống". Xoá một thẻ trong DOM
rồi để nguyên "Trang 1/3" là hiển thị một con số không còn đúng.

---

## QĐ-107. Breadcrumb phải là ĐƯỜNG DẪN, không phải nhãn thứ hai của trang

Trên `/san-pham`, ba dòng chữ xếp chồng trong khoảng 60px chiều cao:

```
Sản phẩm            ← breadcrumb (một chữ, không bấm được)
SẢN PHẨM            ← nhãn nhỏ
Tất cả sản phẩm     ← tiêu đề
```

Vấn đề không nằm ở chỗ lặp chữ mà ở chỗ **dải trên cùng không phải một
đường dẫn**. Breadcrumb tồn tại để nói *"bạn đang ở đâu trong cây trang"*
và cho bấm ngược lên. Một mục duy nhất, không link, thì không nói được vị
trí (đứng một mình thì so với cái gì?) và không dẫn đi đâu.

**Hai sửa, mỗi cái chữa một vấn đề khác nhau:**

**1. Component tự thêm "Trang chủ" vào đầu.** `Trang chủ / Sản phẩm` nói
đúng một điều mà tiêu đề không nói, và bấm được. Tự thêm ở component chứ
không bắt 18 trang tự khai — bỏ sót một trang là trang đó lại rơi về đúng
lỗi cũ, và không ai phát hiện vì trông vẫn "có breadcrumb".

**2. Bỏ nhãn nhỏ khi nó chỉ chép lại chữ bên cạnh** (`/san-pham`,
`/danh-muc`). Nhãn nhỏ chỉ được giữ khi nó nói thêm được gì đó — ví dụ
trang sản phẩm khi đang xem một chương trình khuyến mại, lúc đó tiêu đề
là TÊN chương trình và nhãn là thứ duy nhất cho biết đây là đợt khuyến
mại chứ không phải một danh mục.

**CỐ Ý GIỮ:** `Trang chủ / Giỏ hàng` + tiêu đề `Giỏ hàng`. Khi crumb cuối
nằm trong một đường dẫn có gốc, nó đọc ra là **vị trí**, không phải nhãn
lặp — đây là quy ước chuẩn của mọi trang thương mại điện tử.

### Ba chỗ lặp khác tìm được bằng cách quét tự động

Viết một đoạn quét so từng phần tử chỉ-chứa-chữ với 3 phần tử kế nó trên
17 trang, thay vì nhìn bằng mắt từng trang:

| Chỗ | Lặp gì | Sửa |
|---|---|---|
| `/danh-muc/{slug}` | 12 thẻ sản phẩm đều in "Cây bonsai" dưới tiêu đề "Cây bonsai" | `:show-category="false"` — khách vừa tự bấm vào danh mục ấy |
| Hồ sơ tài khoản | *"(chỉ cần khi đổi email)"* trên hai nhãn cách nhau vài dòng | một dòng ghi chú chung cho cả hai ô |
| Mọi trang quản trị | topbar in tên trang, `<h1>` ngay dưới in đúng chữ đó | `d-lg-none` — dưới 992px sidebar thu vào nên topbar là thứ duy nhất chỉ vị trí; từ 992px mục đang xem đã được tô sáng trong sidebar |

`/nguon-anh` có nhiều link "CC BY-SA 2.0" giống nhau và `/danh-muc` có
nhiều thẻ cùng ghi "5 sản phẩm" — **không sửa**: đó là dữ liệu thật trùng
nhau, không phải giao diện nói hai lần.

---

## QĐ-108. Gỡ chuyển khoản ngân hàng vì quy trình phía sau là thủ công

Hình thức "Chuyển khoản ngân hàng" chạy được về mặt kỹ thuật: sinh mã
VietQR đúng chuẩn EMVCo ngay trên máy chủ, hiện hướng dẫn ở trang đơn và
trong thư xác nhận. Nhưng **bước cuối cùng là một con người mở app ngân
hàng ra nhìn**, rồi bấm "Đã thanh toán".

Tự động hoá bước đó cần API đối soát của ngân hàng — nằm ngoài phạm vi
đồ án. Nên gỡ hẳn thay vì để lại một lựa chọn mà quy trình phía sau không
đạt yêu cầu.

**Đã xoá sạch:** `VietQr`, `BankQrImage`, component hướng dẫn chuyển
khoản, `bank.css`, khối thông tin ngân hàng trong thư và trong trang cài
đặt, ba trường `bank_*` trong `StoreProfile`, và bài kiểm thử VietQR.

### Thứ tự bắt buộc: dữ liệu trước, mã nguồn sau

`orders.payment_method` ép kiểu sang enum trong model. Xoá case
`BankTransfer` khi trong bảng còn dòng mang giá trị `'bank_transfer'` thì
Eloquent ném `ValueError` **ngay lúc nạp** — trang chi tiết đơn ở khu
quản trị lỗi 500, và lỗi đó chỉ lộ ra khi có người mở đúng đơn cũ đó.

Migration chạy trước, đổi 1 đơn sang COD và **ghi lý do vào `admin_note`**
— không đổi ngầm, nhìn vào đơn sau này vẫn biết nó vốn là đơn chuyển
khoản. `down()` cố ý để trống: khôi phục dữ liệu mà không khôi phục mã
nguồn là dựng lại đúng cái lỗi 500 đó.

### Một lỗi về TIỀN lộ ra khi gỡ

`Coupon::acceptsPayment()` coi *"danh sách hình thức sau khi lọc bị rỗng"*
là *"không khai giới hạn nào"*. Hai thứ đó khác nhau.

Một mã lưu `["bank_transfer"]` sau khi hình thức đó bị gỡ sẽ lọc ra mảng
rỗng — và **mã vốn chỉ dành cho đơn trả trước bỗng áp dụng được cho mọi
đơn, kể cả COD**. Nới lỏng một điều kiện về tiền, âm thầm, không lỗi,
không cảnh báo.

Nay tách `hasPaymentRestriction()` hỏi trên dữ liệu thô: có khai giới hạn
mà không giá trị nào còn hiệu lực thì mã không dùng được với hình thức
nào. Chặt hơn là hướng an toàn — cùng lắm một mã không dùng được và có
người báo; hướng kia là mất tiền mà không ai biết.

---

## QĐ-109. Nền móng cho cổng thanh toán: `available()` chứ không `cases()`

MoMo dự kiến tuần sau. Phần dựng trước **không có UI, không có bảng mới,
không có route mới** — chỉ có ba thứ, và cả ba đều để chỗ duy nhất cần
sửa khi thêm cổng là chính enum:

| | Việc |
|---|---|
| `PaymentMethod::gatewayKey()` | hình thức này đọc cấu hình nào |
| `PaymentMethod::available()` | những hình thức **dùng thật được** lúc này |
| `config/payment.php` | khoá bí mật đọc từ `.env`, `enabled` **tính** từ việc có đủ khoá |

`cases()` là mọi hình thức hệ thống **biết**; `available()` là những hình
thức hệ thống **làm được**. Hai câu hỏi khác nhau, và mọi nơi dựng danh
sách cho người dùng phải hỏi câu thứ hai.

Ba nơi đã đổi sang `available()`: bước thanh toán, kiểm tra dữ liệu gửi
lên, ô "giới hạn hình thức" khi tạo mã giảm giá. Nơi thứ hai quan trọng
nhất — `Rule::enum()` nhận **mọi** case kể cả cổng chưa cấu hình, và
giao diện không hiện lựa chọn đó nhưng ai cũng sửa được gói tin gửi lên.

`enabled` **không** phải công tắc admin gõ tay: nó tính từ việc `.env` có
đủ khoá hay không. Để admin bật một cổng chưa có khoá là dựng ra lựa chọn
hỏng giữa đường, sau khi khách đã điền hết địa chỉ.

Thêm MoMo tuần sau = 1 `case` + 1 lớp implements `PaymentGateway` + điền
`.env`. Không phải sửa giao diện, không phải sửa validation.

---

## QĐ-110. GHN là shipper, nên hỏi GHN chính là "shipper xác nhận"

Hai yêu cầu tưởng như mâu thuẫn:

- COD chỉ được ghi là đã trả tiền khi **shipper xác nhận**;
- nhưng **admin tự kiểm rồi bấm xác nhận là thủ công**, không đạt.

Chúng không mâu thuẫn. **GHN chính là shipper**, và GHN có API báo trạng
thái vận đơn. Hỏi GHN là shipper xác nhận — chỉ khác ở chỗ máy hỏi thay
vì người hỏi.

`ghn:dong-bo` chạy 30 phút một lần. GHN báo `delivered` → đơn tự chuyển
`Đã giao` + `Đã thanh toán`.

**Ba luật:**

1. **Chỉ `delivered`.** `delivering` là shipper đang trên đường, khách
   vẫn có quyền từ chối nhận tại cửa. Ghi sớm là cửa hàng ghi vào sổ một
   khoản không tồn tại và không bao giờ đi đòi.
2. **Không tự huỷ đơn** khi GHN báo `returned`/`lost` — huỷ kéo theo hoàn
   kho, hoàn tiền, và thường là một cuộc gọi cho khách.
3. **Đi qua `OrderService`**, không `update()` thẳng: đổi trạng thái còn
   kéo theo thư gửi khách và mốc thời gian.

Việc này cũng sửa một lỗi có sẵn: `orders.shipping_status` trước đây chỉ
được ghi lúc tạo vận đơn (`ready_to_pick`) và lúc huỷ, rồi **không bao
giờ đổi nữa**. Đơn giao xong ba hôm trước vẫn hiện "chờ lấy hàng" — không
phải thiếu tính năng, mà là một con số sai hiển thị cho cả khách lẫn
admin.

Nút bấm tay vẫn giữ, cho đơn không đi qua GHN và cho lúc GHN sập. Tự động
là đường chính, bấm tay là đường lùi.

---

## QĐ-111. Tự động hoá im lặng khi hỏng thì tệ hơn không tự động

Hai lỗi trong chính đoạn mã tự động vừa viết, cả hai đều do bài kiểm thử
bắt chứ không phải do đọc lại code:

**1. Hỏng mà báo thành công.** `GHNService::post()` không ném lỗi — nó
trả về mảng có `code` khác 200 và ghi log. Bản đầu của `syncOne()` chỉ
đọc `data.status`, không thấy thì trả `false`. Nghĩa là GHN sập cả buổi
mà lệnh vẫn báo *"đã hỏi 40 vận đơn, 0 lỗi"* — một hệ thống hỏng trông y
hệt một hệ thống không có việc gì làm.

**2. Chụp trạng thái sau khi đã đổi.** `changeStatus()` sửa thẳng trên
đối tượng `$order`, nên đọc `$order->status` **sau** lời gọi là đọc trạng
thái mới. Phép so "có nhúc nhích không" luôn đúng bằng nhau và vòng lặp
thoát ngay sau bước đầu. Hậu quả đo được: đơn ở "Đã xác nhận" mà GHN báo
đã giao chỉ nhích lên "Đang chuẩn bị" rồi dừng — khách không nhận được
thư "đơn đã giao", trong khi tiền COD thì đã ghi là đã thu.

Rút ra: **việc máy làm cũng phải có trong nhật ký.** Nhật ký quản trị
trước đây chỉ ghi việc do người bấm. Admin mở lên thấy đơn tự thanh toán
mà không có dòng nào giải thích thì câu hỏi đầu tiên là *"ai làm cái
này?"* — tự động hoá không có nhật ký trông y hệt lỗi.

Xem `docs/TU-DONG-HOA.md` để biết chỗ nào đã tự động, chỗ nào cố ý giữ
người, và vì sao.

---

## QĐ-112. Phân loại sinh học phải là một CÂY, nhãn sinh thái thì không

Khách tìm cây theo bốn kiểu khác nhau, và chúng cần hai cơ chế lưu khác
nhau — không phải một.

**Nhãn phẳng → `product_traits`, KHÔNG migration nào.**

Môi trường sống, dạng sống, dáng, màu đều là nhãn nhiều-nhiều phẳng.
Bảng `product_traits` đã có sẵn đúng hình dạng đó. Thêm bốn cột vào
`products` là làm hỏng cả hai chiều: một sản phẩm không mang được hai màu,
và không lọc ngược từ nhãn ra sản phẩm được.

Bốn `case` mới trong `TraitType` + bốn enum giá trị. Không sửa cơ sở dữ
liệu, không sửa model.

**Phân cấp → bảng `plant_taxa` riêng, có `parent_id`.**

Giới → Ngành → Lớp → Bộ → Họ → Chi → Loài là một **cây có thứ bậc**. Lưu
phẳng thành bảy nhãn mỗi sản phẩm thì:

| | Hậu quả |
|---|---|
| Không có ràng buộc nhất quán | Một sản phẩm mang Chi = *Monstera* nhưng Họ = *Rosaceae* — sai hoàn toàn, CSDL vẫn nhận |
| Sửa một tên = sửa N sản phẩm | Giới khoa học đổi vị trí phân loại của một chi là chuyện thường |
| Không duyệt ngược lên được | "Cho tôi xem mọi cây họ Ráy" phải quét toàn bộ bảng nhãn |

**LUẬT QUAN TRỌNG NHẤT: chọn bậc RỘNG phải ra NHIỀU hàng hơn.**

Sản phẩm gắn ở bậc Loài, khách bấm vào bậc Họ. `scopeInTaxon` lấy cả
nhánh con cháu. Chỉ khớp đúng nút được chọn thì trang Họ luôn trống —
ngược hẳn trực giác người dùng. Có một bài kiểm thử riêng cho đúng điều
này.

### Tách khỏi "Danh mục", cố ý

- **Danh mục** = cách CỬA HÀNG bày hàng theo dịp mua: "Hoa cưới", "Cây để
  bàn". Đổi theo mùa.
- **Phân loại** = cách THIÊN NHIÊN xếp. Không quan tâm ai bán gì.

Cùng một cây nằm ở cả hai chỗ, ở hai vị trí không liên quan gì nhau. Ép
thành một cây là buộc phải bỏ một trong hai cách tìm.

---

## QĐ-113. Không bịa xuống tới loài cho đủ bảy bậc

27/33 cây được gán phân loại. Sáu cây còn lại **cố ý để trống**, và nhiều
cây khác dừng ở bậc Họ hoặc Chi:

| Mặt hàng | Dừng ở | Vì sao |
|---|---|---|
| Sen đá mix chậu đá | **Họ** Thuốc bỏng | Nhiều loài trong một chậu |
| Xương rồng bi | **Họ** Xương rồng | "Bi" là tên chợ, không phải tên loài |
| Vạn niên thanh | **Họ** Ráy | Tên này ở Việt Nam dùng cho vài chi khác nhau |
| Hoa hồng, lan hồ điệp, cúc | **Chi** | Hàng thương mại là giống lai, không có loài đơn |
| Lẵng hoa khai trương, hoa cưới, set quà | **không gán** | Sản phẩm phối nhiều loài |

Bịa một tên loài cho đủ bảy bậc thì trang chi tiết trông "đầy đủ" hơn, và
sai. Một người biết cây nhìn vào là mất tin cậy ngay ở dòng đầu tiên.

Nhãn sinh thái theo cùng nguyên tắc: bó hoa cưới có **dáng** và **màu**
(đặc điểm của chính sản phẩm) nhưng không có **môi trường sống** hay
**dạng sống** (đặc điểm của một loài). Bịa cho đủ bốn nhãn thì khách lọc
"cây sa mạc" mà ra một bó hoa cưới.

**Ảnh đại diện lấy từ ảnh sản phẩm đã có**, không tải mới: mỗi ảnh mới là
một giấy phép phải kiểm và một dòng ghi công phải thêm. Một tấm ảnh
Monstera thật minh hoạ cho Chi *Monstera* đúng y như ảnh tải mới. 15/15 Họ
có ảnh.

---

## QĐ-114. "Hàng mới về" phải giới hạn theo NGÀY, và được phép rỗng

Khối đó là `latest()->take(8)` — nghĩa là "8 món thêm sau cùng", KHÔNG
phải "8 món mới". Hai thứ trùng nhau khi cửa hàng nhập hàng đều tay, và
tách hẳn khi không: nghỉ nhập ba tháng thì trang chủ vẫn trưng tám món
của quý trước dưới chữ "Hàng mới về".

**60 ngày** (`config/catalog.php`), và con số là lựa chọn nghiệp vụ:

- ngắn hơn (2 tuần) hợp hoa tươi, nhưng cây cảnh và bonsai bán chậm hơn
  nhiều — cây mới nhập vẫn còn "mới" với khách sau một tháng;
- dài hơn (6 tháng) thì quá nửa danh mục luôn là "mới", và chữ "mới" mất
  nghĩa.

**Rỗng thì ẩn hẳn cả khối.** Cách hỏng cũ không phải là hiện sai vài món —
mà là khối đó KHÔNG BAO GIỜ rỗng, nên nó luôn nói "có hàng mới" kể cả khi
không có. Nay rỗng là câu trả lời hợp lệ, và một khối trống chiếm trọn
màn hình đầu trang để nói rằng không có gì thì thà nhường chỗ cho khối
gợi ý.

---

## QĐ-115. Ảnh tải lên phải được tối ưu ngay, không chờ ai gõ lệnh

Việc sinh bản WebP trước đây **chỉ nằm trong lệnh `anh:toi-uu` chạy tay**.
Admin thêm sản phẩm và tải ảnh lên thì ảnh đó lưu nguyên bản JPEG — không
bản WebP, không có trong manifest.

**Và không có gì hỏng nhìn thấy được.** `<x-site.image>` không tìm thấy
bản tối ưu thì dùng thẳng ảnh gốc, trang vẫn hiện bình thường. Ảnh đó chỉ
đơn giản là nặng gấp mấy lần những ảnh khác, mãi mãi. Kiểu hỏng này không
có biểu hiện nào trên màn hình — nên nó phải có bài kiểm thử, không thể
"để ý là biết".

**Sửa bằng cách để chỉ có MỘT cách lưu ảnh.** Trước đây tám chỗ trong năm
tệp tự gọi `$file->store(...)`. Nhắc mọi người "nhớ gọi thêm hàm tối ưu"
thì chỗ thứ chín sẽ quên; cho tất cả đi qua `ImageStore` thì không có gì
để quên.

**Tối ưu NGAY, không đẩy vào hàng đợi.** Dự án đặt `QUEUE_CONNECTION=database`
mà không có worker nào chạy — đẩy vào hàng đợi nghĩa là công việc nằm
trong bảng `jobs` mãi mãi, hỏng y hệt trước nhưng khó phát hiện hơn. Chi
phí thật: 100–300ms cho một ảnh, sau khi admin đã bấm "Lưu".

**Tối ưu hỏng không được làm hỏng việc lưu ảnh.** Thiếu GD, ảnh lạ, hết
đĩa — ảnh gốc thì đã lưu xong và giao diện tự lùi về dùng nó. Ném lỗi ra
ngoài là làm hỏng cả việc tạo sản phẩm chỉ vì một bước làm-cho-nhẹ-hơn.

### Hai chỗ mù tìm ra khi làm

1. **`files()` không đệ quy** — lệnh quét không nhìn thấy `products/gallery/`,
   nên toàn bộ ảnh phụ của sản phẩm chưa bao giờ được tối ưu. Đổi sang
   `allFiles()`.
2. **Thư mục `hero` không có trong danh sách quét** — mà đó là ảnh TO NHẤT
   và là thứ khách nhìn thấy đầu tiên.

---

## QĐ-116. Chuỗi phân loại dừng sớm phải NÓI RA vì sao

Kiểm lại cả cây phân loại: 66 nút, **không nhánh nào thiếu bậc trung
gian** (không có Họ nào nhảy thẳng lên Ngành). Nhưng 11 sản phẩm dừng ở
bậc Chi hoặc Họ, và trên màn hình chuỗi cụt đó trông y hệt dữ liệu làm dở.

Thêm cột `products.taxon_note`. **Đặt ở sản phẩm chứ không ở nút phân
loại**, vì lý do dừng là chuyện của MÓN HÀNG:

- "Sen đá mix chậu đá" dừng ở họ Thuốc bỏng vì **chậu đó** có nhiều loài;
- "Sen đá nâu chậu sứ mini" dừng ở đúng họ đó vì một lý do khác hẳn —
  tên gọi theo màu ngoài chợ.

Ghi lên nút thì mọi sản phẩm dưới nút phải chung một lời giải thích, và
lời đó sai với ít nhất một trong số chúng.

Seeder **cảnh báo** khi có món dừng sớm mà chưa có lời giải thích — im
lặng bỏ qua thì người thêm sản phẩm mới không biết mình còn thiếu gì.

### Một lỗi tên khoa học đã sửa

`Dracaena` được đặt tên tiếng Việt là **"Chi Huyết dụ"**. Sai: "huyết dụ"
là tên của *Cordyline fruticosa*, một chi hoàn toàn khác. Tên tiếng Việt
của *Dracaena* là **"huyết giác"**. Cả nhánh Lưỡi hổ đang nằm dưới một
cái tên chỉ vào loài khác — đúng kiểu sai mà một trang "phân loại khoa
học" không được phép có.

---

## QĐ-117. Bốn nhóm hàng phụ trợ, chia theo VIỆC KHÁCH ĐANG LÀM

Cũ có hai nhóm và ranh giới giữa chúng là **bền hay tiêu hao**. Ranh giới
đó sai với cách khách đi mua:

- bình tưới và kéo cắt cành nằm ở "Phụ kiện" cùng chậu sứ, dù chúng là
  **dụng cụ chăm cây**;
- người vừa mua cây tìm đá, sỏi, rêu phủ mặt chậu — **không có nhóm nào**;
- người mua cây làm quà Noel hay Tết tìm quả cầu, nơ, đồ treo — cũng không.

| Nhóm | Khách đang làm gì |
|---|---|
| Vật tư & dụng cụ chăm sóc | nuôi cây sống |
| Chậu & đế lót | đựng cây |
| **Phủ gốc & tiểu cảnh** (mới) | làm mặt chậu đẹp lên |
| **Phụ kiện trang trí** (đổi nghĩa) | trang trí theo dịp |

"Phụ kiện" **giữ nguyên slug** `phu-kien` nhưng đổi nghĩa thành đồ trang
trí — đúng nghĩa người Việt hiểu khi nghe từ đó. Đổi slug thì mọi liên
kết đã chia sẻ đều gãy, mà cái tên thì vẫn hợp.

Toàn bộ hàng phụ trợ chuyển từ `PlantAdvisorSeeder` sang
`SupplyCatalogSeeder`. Một seeder tên "tư vấn chọn cây" mà lại quyết định
cửa hàng có danh mục phụ kiện nào thì không ai đi tìm ở đó.

---

## QĐ-118. Bộ lọc tiêu đề ảnh dùng danh sách HOẶC là một cái bẫy

Tám ảnh sản phẩm sai chủ đề đã lên trang. Nguyên nhân **không phải** do
Openverse trả kết quả kém, mà do bộ lọc `must` trong
`tools/fetch-product-photos.mjs` dùng danh sách từ HOẶC gồm những từ quá
thường gặp:

| Bộ lọc | Tiêu đề lọt qua | Ảnh nhận về |
|---|---|---|
| `/water\|can/i` | "Crews work to clean up debris so **water** **can** flow" | máy xúc, xe ben |
| `/flower\|vase/i` | "...statue, holding a mala, **flower**, **vase** of nectar..." | tượng Phật mạ vàng |
| `/…\|flower\|…/i` | "Walwhalleya proluta **flower**head10" | bàn tay cầm bông cỏ |
| `/fertili\|…/i` | "**Fertili**sed Bulb" | một củ hoa |
| `/saucer\|tray\|pot\|dish/i` | "Teacup Plant **Pot**" | tách trà làm chậu |
| `/daisy\|…\|flower/i` | "A single **flower**" | một bông đồng tiền **đỏ** |

Mỗi từ trong danh sách HOẶC làm bộ lọc **lỏng thêm**, không chặt thêm.
Càng liệt kê nhiều phương án để "chắc ăn tìm được ảnh" thì càng dễ nhận
ảnh sai.

**Hai sửa:**

1. **Cụm từ hoàn chỉnh thay cho từ rời** — `/watering[- ]?can/i` thay cho
   `/water|can/i`.
2. **Thêm tuỳ chọn `block` theo từng món.** `TITLE_BLOCK` chung chặn
   những thứ xấu cho mọi món, nhưng có từ chỉ sai với **một** món: ảnh
   "Pachira aquatica **flowers**" đúng loài mà sai bộ phận — sản phẩm là
   cây bện thân. Cấm từ "flower" ở danh sách chung thì hỏng hàng chục
   sản phẩm khác.

**Ảnh sai chủ đề đã bị GỠ, không để nguyên chờ tải lại.** Xoá cả tệp chứ
không chỉ đặt `main_image = null`: `products:link-photos` gắn ảnh theo
tên tệp, nên để tệp lại thì lần chạy sau nó gắn đúng tấm ảnh sai đó về
chỗ cũ.

Bốn ảnh **đúng loài nhưng không phải tấm đẹp nhất** (hoa Pachira, hoa
Dieffenbachia, chậu hoa kiểu Picasso, xương rồng cho chậu sen đá mix) thì
GIỮ — chúng không đánh lừa ai, và gỡ hết thì trang trống trơn.

---

## QĐ-119. Giá niêm yết đã gồm VAT — thuế được TÁCH RA, không cộng thêm

Hai mô hình có thể chọn:

1. **Giá chưa thuế, cộng thuế ở bước cuối** (kiểu Mỹ). Khách nhìn
   "260.000₫" trên thẻ sản phẩm rồi phải trả 280.800₫ — con số ở trang
   danh sách nói dối.
2. **Giá đã gồm thuế** (Việt Nam, EU). Con số trên thẻ chính là con số
   khách trả; phần thuế được tách ra từ tổng.

Chọn (2). **Hệ quả quan trọng nhất: bật thuế lên không làm khách phải
trả thêm một đồng nào**, và không con số nào trên giao diện khách hàng
đổi. Có một bài kiểm thử riêng giữ đúng điều này — đặt hai đơn giống hệt
nhau với thuế 0% và 10%, tổng phải bằng nhau.

**Công thức:** `thuế = tổng − tổng / (1 + thuế suất)`

Lỗi hay gặp nhất là nhân thẳng `tổng × thuế suất`. Với 8% thì cách sai
cho ra 8.640₫ trên đơn 108.000₫, cách đúng là 8.000₫ — **lệch 8% mãi
mãi**, và chỉ lộ ra ở kỳ quyết toán.

**Lưu cả `tax_rate` lẫn `tax_amount` vào đơn**, không phải dư thừa:
`tax_rate` trả lời "vì sao con số này" cho kiểm toán; `tax_amount` là con
số đã chốt — tính lại từ tỉ lệ sẽ ra sai số làm tròn khác và tổng một
trăm đơn không khớp sổ.

**Đơn cũ để NULL, không điền ngược.** NULL đọc ra là "không có số liệu",
0 đọc ra là "thuế bằng không" — hai điều khác hẳn nhau khi đối chiếu sổ.
Điền ngược là bịa dữ liệu kế toán chưa từng tồn tại.

**Thuế suất nhập theo phần trăm, lưu theo thập phân.** Kế toán nói "8%",
không nói "0,08". Bắt admin tự quy đổi là mời một lỗi gõ nhầm gấp 100 lần
vào đúng con số thuế.

Con số mặc định trong `config/tax.php` là **giá trị mặc định kỹ thuật,
không phải lời tư vấn thuế** — trang Cấu hình ghi rõ điều đó.

---

## QĐ-120. Định dạng tiền phải có MỘT nơi chịu trách nhiệm

49 chỗ trong 19 tệp tự gọi `number_format($x, 0, ',', '.')` rồi tự nối ký
hiệu — ba biến thể chỉ riêng phần ký hiệu (`₫`, `&#8363;`, `đ`), có chỗ
có dấu cách trước có chỗ không.

Hậu quả không phải "trông hơi lệch nhau" mà là **đơn vị tiền tệ không sửa
được**: thêm một ô cấu hình mà 49 chỗ vẫn viết cứng thì ô đó là chức năng
giả.

`App\Services\Shop\Money` + `<x-site.money>` là nơi duy nhất. Bốn tham số
sửa được từ trang Cấu hình: mã, ký hiệu, vị trí, số lẻ. **Dấu ngăn nghìn
KHÔNG có ô riêng** — nó suy ra từ mã tiền tệ; cho admin chọn từng dấu là
mở đường cho những tổ hợp không tồn tại ở đâu cả (`1.234.56`).

Đoạn JavaScript xem trước giá khuyến mại cũng nhận tham số qua `@json`
thay vì viết cứng `'vi-VN'` — nếu không thì đổi tiền tệ xong, cả trang
đổi mà riêng ô xem trước vẫn hiện ký hiệu cũ, và admin tin vào con số sai
đơn vị ngay lúc đang đặt giá.

Tên cửa hàng cũng vậy: 17 chỗ viết cứng → `StoreProfile::name()`.

---

## QĐ-121. `static` là sai phạm vi cho bộ nhớ tạm cấu hình

`Setting::$memo` là một thuộc tính `static`. Với PHP-FPM thì vô hại — mỗi
request là một tiến trình mới. Nhưng dự án có tiến trình **sống rất lâu**:

- `php artisan schedule:work` chạy liên tục hàng giờ;
- `ghn:dong-bo` gửi thư cho khách qua tiến trình đó, và thư ký tên bằng
  `StoreProfile::name()`.

`Setting::set()` xoá được cache chung và memo của **tiến trình gọi nó** —
tức là tiến trình web. Tiến trình nền không biết gì, và `$memo` của nó
chặn trước khi kịp chạm cache. **Admin đổi tên cửa hàng lúc 9h sáng, thư
gửi cả ngày vẫn ký tên cũ** cho tới khi có người khởi động lại tiến trình.

Lỗi này lộ ra ở bài kiểm thử trước: `RefreshDatabase` khôi phục cơ sở dữ
liệu nhưng không đụng tới thuộc tính static, nên giá trị bài trước rò
sang bài sau. Dễ bị coi là "lỗi của test" — nhưng nó là lỗi thật của mã
chạy production, chỉ tình cờ lộ ra ở test.

Sửa: đăng ký `scoped` trong container. `scoped` được dọn giữa mỗi request
và mỗi job — đúng phạm vi bộ nhớ tạm này cần.

---

## QĐ-122. SỰ CỐ: script sửa hàng loạt đã xoá trắng một tệp

**Chuyện đã xảy ra.** Script Python thay tên cửa hàng trong các tệp view
viết như sau:

```python
io.open(p, 'w', encoding='utf-8').write(rx.sub(TEN, s))
```

`io.open(p, 'w')` **cắt trắng tệp NGAY LẬP TỨC**, trước khi `rx.sub()`
chạy. Chuỗi thay thế chứa `\A` (từ `\App\Services\...`) nên `re.sub` hiểu
đó là escape của regex và ném `PatternError` — tệp đang mở ở chế độ ghi
bị bỏ lại ở **0 byte**.

`resources/views/welcome.blade.php` mất sạch. Dự án **không dùng Git**,
không có bản sao nào trên đĩa.

**Đã dựng lại** từ những nguồn còn có thật:

| Nguồn | Cho biết |
|---|---|
| `HomeController` | đủ 5 biến view và ý nghĩa từng khối |
| CSS `sections.css` | tên lớp của mọi khối chỉ dùng ở trang chủ |
| Bài kiểm thử | trang chủ phải có `class="product-buy"`, `data-scheme`, chữ "Hàng mới về" |
| Kết quả quét lặp chữ chạy trước đó | tiêu đề `h1` đúng nguyên văn |
| Chú thích trong controller | vì sao từng khối tồn tại |

Kết quả: 364/364 bài kiểm thử xanh, trang chủ 108.629 byte, đủ 9 khối.
Nhưng **câu chữ của một vài tiêu đề phụ có thể khác bản gốc** — đó là
giới hạn thật của việc dựng lại, không phải điều có thể khẳng định là
giống hệt.

### Ba luật rút ra

1. **Không bao giờ mở tệp ở chế độ `'w'` trước khi tính xong nội dung
   mới.** Tính trước, rồi mới ghi:
   ```python
   moi = rx.sub(TEN, s)      # hỏng ở đây thì tệp cũ còn nguyên
   io.open(p, 'w').write(moi)
   ```
2. **`str.replace()` thay cho `re.sub()`** khi chuỗi thay thế chứa dấu
   `\` — regex hiểu `\A`, `\S`, `\1` theo nghĩa riêng của nó.
3. **Dự án này cần Git.** Không có nó thì một lỗi script là mất vĩnh
   viễn, và mọi thao tác sửa hàng loạt đều là canh bạc.

---

## QĐ-123. Nhật ký cá nhân là dữ liệu riêng tư, và lời hứa đó phải nằm trong mã

Trang danh sách sổ nói thẳng với người dùng: *"Chỉ mình bạn đọc được. Cửa
hàng không dùng nội dung ở đây cho gợi ý sản phẩm hay bất kỳ thống kê
nào."*

Một câu như thế mà chỉ nằm trong tài liệu thì lần sửa sau sẽ quên. Ba
tháng nữa, khi có người muốn "gợi ý thông minh hơn", ba bảng nhật ký nằm
ngay đó và đầy ắp **đúng thứ là tín hiệu tốt nhất về sở thích**: người
này trồng cây gì, chăm được không, đang chờ giá bao nhiêu. Không có gì
trong mã nguồn ngăn họ dùng, và người viết đoạn đó sẽ không thấy mình
đang làm gì sai.

Vì vậy lời hứa được ép ở **tầng SQL**, không phải ở tầng ý định:
`JournalPrivacyTest` bọc `DB::listen()` quanh `RecommendationService` và
`PlantAdvisor`, rồi khẳng định không câu truy vấn nào chạm vào
`journals`, `journal_entries`, `journal_metrics`.

Không kiểm bằng cách so kết quả gợi ý trước/sau — cách đó bỏ lọt trường
hợp nhật ký **được đọc** nhưng tình cờ không đổi thứ hạng. Đọc là đã vi
phạm rồi, bất kể có đổi kết quả hay không.

Ba lựa chọn đi kèm:

- **404 chứ không 403** khi mở sổ người khác. 403 xác nhận quyển sổ đó
  *có tồn tại* — người dò id đếm được người khác có bao nhiêu sổ.
- **Lọc ngay trong truy vấn** (`scopeOwnedBy` rồi mới `findOrFail`), không
  phải nạp ra rồi mới kiểm. Nạp trước có một khoảnh khắc đối tượng của
  người khác đã nằm trong tay code chưa kiểm; thêm một dòng ghi log vào
  giữa là rò dữ liệu.
- **Xoá tài khoản thì nhật ký đi theo** (`cascadeOnDelete`), và xoá sổ là
  xoá thật chứ không xoá mềm. Giữ lại bản "đã xoá" của dữ liệu riêng tư
  mà người ta chủ động yêu cầu xoá là không làm đúng điều họ vừa yêu cầu.

---

## QĐ-124. Một bài kiểm thử quyền riêng tư chỉ đi qua nhánh dễ nhất thì không bảo vệ ai

Đây là bài học đắt nhất của tính năng nhật ký, và nó suýt lọt.

`JournalPrivacyTest` bản đầu **XANH**. Trông như quyền riêng tư đã được
bảo vệ. Để chắc, tôi chèn thử một câu đọc thẳng bảng `journals` vào giữa
`RecommendationService` — bài **VẪN XANH**.

Lý do: `forViewer()` **thoát sớm** khi người dùng chưa có lịch sử xem.

```php
if ($history->isEmpty()) {
    return ['items' => $this->popular($limit, $excluded), 'personalized' => false];
}
```

Dữ liệu kiểm thử chỉ có một quyển sổ, không có sự kiện xem sản phẩm nào.
Nên bài chạy đúng nhánh *"chưa biết gì về người này"* — và không bao giờ
chạm tới nhánh cá nhân hoá, **đúng cái nhánh có nguy cơ đọc nhật ký
nhất**. Nó canh đúng cánh cửa duy nhất không dẫn đi đâu.

Sửa: thêm một `UserEvent` (ProductView) vào dữ liệu kiểm thử. Chèn lại
câu truy vấn vi phạm → bài **đỏ**, kèm đúng câu SQL:

```
Bộ máy gợi ý đã đọc dữ liệu nhật ký riêng tư:
select count(*) as "aggregate" from "journals" where "user_id" = ?
```

Ba quy tắc rút ra, áp cho mọi bài kiểm thử sau này:

1. **Một bài kiểm thử mới xanh ngay lần đầu là điều đáng nghi, không phải
   điều đáng mừng.** Phải phá thử mã nguồn và thấy nó đỏ thì mới biết nó
   đang kiểm cái gì.
2. **Dữ liệu kiểm thử phải đủ để đi vào nhánh phức tạp nhất**, không phải
   đủ để chạy hết hàm.
3. Bài kiểm thử nên **tự khẳng định là mình có chạy thật**. Ở đây là
   `assertNotEmpty($sql)`; ở `RouteSmokeTest` là phép đếm số trang quản
   trị vào được. Không có nó, một thay đổi phân quyền có thể làm bài lặng
   lẽ ngừng bảo vệ mà vẫn xanh.

Mười hai bài của `JournalTest` cũng đã qua cách kiểm này. Năm đột biến
được chèn vào mã thật, mỗi đột biến làm đỏ đúng bài canh nó:

| Đột biến | Bài đỏ |
|---|---|
| `goalProgress()` trả `0.0` thay vì `null` khi chưa có số liệu | tiến độ mục tiêu là null |
| chuỗi biểu đồ xếp theo `id` thay vì theo `entry_date` | ghi bù ngày cũ |
| tiến độ lấy lần ghi **đầu** thay vì gần nhất | tiến độ theo lần gần nhất |
| bỏ guard hàng chỉ số trống | hàng bỏ trống bị bỏ qua (500: NOT NULL) |
| bỏ `before_or_equal:today` | không nhận ngày ở tương lai |

---

## QĐ-125. Chỉ số nhật ký là hàng trong bảng, không phải cột

Cách dễ hơn là thêm cột `chieu_cao`, `so_la`, `duong_kinh` vào
`journal_entries`. Làm vậy là phải **đoán trước mọi chỉ số của mọi loài**:
người trồng lan đo "số nụ", người chơi bonsai đo "đường kính thân", người
theo dõi giá ghi "giá ngoài chợ". Đoán sai thì họ không ghi được thứ họ
cần, và mỗi lần bổ sung là một migration.

`journal_metrics` (tên do người dùng đặt, giá trị, đơn vị) mở cho mọi chỉ
số. Biểu mẫu **gợi ý sẵn** tên theo kiểu sổ để người mới không nhìn ô
trống, nhưng gõ đè được hết.

Đánh đổi đã chấp nhận: không thống kê chéo giữa các sổ được, vì "Chiều
cao" của người này và "chiều cao" của người kia là hai chuỗi khác nhau.
Không sao — **không có thống kê chéo nào được phép tồn tại ở đây**
(QĐ-123), nên cái giá đó bằng không.

---

## QĐ-126. Ngày ghi tách khỏi `created_at`, và chặn ngày ở tương lai

Người ta hay ghi bù: chủ nhật ngồi ghi lại cả tuần. Nếu dòng thời gian
xếp theo `created_at` thì bốn trang của bốn ngày dồn hết vào chủ nhật, và
**biểu đồ sinh trưởng thành một cột dựng đứng** — mất luôn thứ duy nhất
biểu đồ dùng để làm.

Nên có cột `entry_date` riêng, sửa được, mặc định là hôm nay.

Chặn `before_or_equal:today`: nhật ký là ghi lại thứ **đã quan sát được**.
Một trang đề ngày mai là dữ liệu chưa tồn tại — và nếu lọt vào thì mọi
phép tính "gần nhất" (tiến độ mục tiêu) đều lấy nhầm nó.

---

## QĐ-127. Chưa có số liệu thì trả `null`, đừng trả 0%

`goalProgress()` trả `null` khi sổ chưa đặt mục tiêu **hoặc đã đặt mà chưa
ghi số nào**, và giao diện hiện "Chưa có số liệu cho chỉ số này" thay vì
một thanh tiến độ rỗng.

"0%" đọc ra là *"đã bắt đầu và chưa đi được bước nào"*. "Chưa đo lần nào"
là chuyện khác hẳn. Hiện 0% làm người ta tưởng mình đang tụt lại trong
khi thật ra chưa có gì để đo — một con số bịa ra từ chỗ trống.

Cùng nguyên tắc ở biểu đồ: **một điểm thì không vẽ đường**. Một điểm
không có "thay đổi theo thời gian" nào để nhìn; kẻ một đường qua đúng một
điểm là bày ra một xu hướng không tồn tại. Thay vào đó là một câu: *"Mới
có một lần ghi — thêm một lần nữa là có đường biểu diễn."*

---

## QĐ-128. Hàng nhập liệu bỏ trống thì bỏ qua, không báo lỗi

Biểu mẫu ghi trang nhật ký luôn có ba hàng chỉ số, hàng cuối để trống cho
người dùng tự thêm. Hàng nào thiếu tên **hoặc** thiếu giá trị thì bị bỏ
qua lúc lưu.

Bắt lỗi một hàng người ta không định điền là **chặn họ vì một việc họ
không làm**. Họ sẽ phải đọc thông báo lỗi, tìm xem ô nào đỏ, rồi nhận ra
mình chẳng làm gì sai cả.

Bỏ guard này ra thì ràng buộc `NOT NULL` của `journal_metrics.value` vỡ
thành lỗi 500 — đã kiểm chứng bằng cách chèn đột biến và xem bài đỏ.

---

## QĐ-129. Sổ chỉ gắn được vào cây đã mua

`product_id` không validate bằng `exists:products,id` mà bằng
`Rule::in()` trên danh sách cây người này **đã mua thật** (lấy từ đơn
hàng, không phải từ giỏ hay danh sách yêu thích).

Cho gắn vào sản phẩm bất kỳ thì trang sổ thành một cách **dò xem cửa hàng
bán gì** kể cả sản phẩm đang ẩn — và tệ hơn, một cách dựng dữ liệu giả về
việc mình đã mua.

"Cây của tôi" nghĩa là cây đã về tay, không phải cây đang ngắm.

---

## QĐ-130. Lưu trữ mới là thứ người dùng thường muốn, không phải xoá

Cây chết rồi thì quyển sổ vẫn là kỷ niệm, và vẫn là bài học cho lần trồng
sau. Bắt người ta phải **xoá** mới cho gọn màn hình là bắt họ đánh đổi
sai.

Nên có `is_archived`: ẩn khỏi danh sách chính, vẫn mở và vẫn ghi thêm
được, có lối quay lại.

Nút **Xoá vĩnh viễn** tách hẳn ra một khối riêng ở cuối trang sửa, không
đứng cạnh nút Lưu — nút xoá cạnh nút lưu là công thức để có người bấm
nhầm. Kèm câu nói rõ hậu quả và một câu nhắc rằng Lưu trữ mới là thứ họ
đang muốn trong hầu hết trường hợp.

---

## QĐ-131. Biểu đồ nhật ký dựng bằng SVG ở máy chủ, không kéo thư viện

Một đường gấp khúc là mấy phép tính tỉ lệ. Kéo về 60KB JavaScript cho
việc đó là đổi tốc độ tải trang lấy thứ không cần.

SVG dựng sẵn ở máy chủ còn có nghĩa là **biểu đồ hiện ra ngay cả khi
JavaScript hỏng**, và tooltip dùng thẻ `<title>` gốc của trình duyệt —
trình đọc màn hình đọc được, không cần một dòng script nào.

Đổi chỉ số vẽ biểu đồ bằng **đường dẫn** (`?chi-so=...`) chứ không bằng
JavaScript: gửi link cho nhau được, nút Back chạy đúng — cùng cách đã
dùng cho bộ lọc sản phẩm.

Theo bộ quy tắc trực quan hoá dữ liệu: một chuỗi duy nhất nên **không có
chú giải** (tiêu đề ngay trên biểu đồ đã nói nó là chỉ số gì); nhãn giá
trị **chọn lọc** ở điểm đầu, điểm cuối và điểm cao nhất chứ không ghi số
lên mọi điểm; lưới chỉ ba đường ngang. Màu đường lấy `--brand-400` ở nền
tối chứ không phải `--brand-500` — bộ kiểm tra bảng màu chấm
`--brand-500` trên nền tối chỉ đạt 2,84:1, **trượt**.

---

## QĐ-132. Một bài quét 5xx cho mọi đường dẫn GET

`RouteSmokeTest` mở 47 đường dẫn GET không tham số — cả trang khách lẫn
trang quản trị — và chỉ hỏi *"có vỡ không?"*. Hết khoảng hai giây.

Đó là câu hỏi mà những bài kiểm thử chi tiết hay bỏ sót, vì mỗi bài chỉ
mở vài trang thuộc phần mình. Sửa một biến trong bố cục dùng chung, đổi
tên một cột, xoá một biến view — hỏng ở một trang không ai nghĩ tới.

Bài tự kiểm chính nó bằng phép đếm số trang quản trị vào được (xem
QĐ-124): nếu tất cả trang quản trị trả 403 thì bài vẫn xanh, vì 403 không
phải 5xx — và nó sẽ trông như đang canh gác cả khu quản trị trong khi
chưa từng mở nổi một trang nào ở đó. Hiện tại: 17 trang quản trị vào được,
0 lỗi 5xx. Đã kiểm chứng bằng cách ném một ngoại lệ vào `/nhat-ky` và
thấy bài đỏ đúng đường dẫn đó.

---

## QĐ-133. Gợi ý chấm điểm trên bốn trục, và mỗi trục phải chuẩn hoá trước khi cộng

Bộ máy gợi ý trước đây chỉ biết **danh mục** và **hình thức bán**. Với nó,
hai chậu sen đá — một xanh một tím — là giống hệt nhau, trong khi cửa
hàng đã có sẵn dữ liệu màu cho 33 sản phẩm, dáng cho 33, môi trường sống
cho 27, và loài thực vật cho 27. Dữ liệu nằm đó không được dùng.

`TasteProfile` thêm hai trục: **đặc điểm** (màu, dáng, dạng sống, môi
trường, vị trí đặt, hợp mệnh) và **phân loại thực vật**.

**Chuẩn hoá từng trục về 0..1 rồi mới nhân trọng số.** Cộng thẳng điểm
thô là sai và sai nặng: một sản phẩm mang bảy nhãn sẽ luôn thắng một sản
phẩm đúng danh mục nhưng chỉ có hai nhãn — không phải vì nó hợp hơn, mà
vì **nó được gắn nhiều nhãn hơn**. Thứ tự gợi ý khi ấy phản ánh công sức
nhập liệu của admin chứ không phản ánh sở thích của khách.

Trọng số trục là chỗ **duy nhất** tuyên bố cửa hàng tin trục nào quan
trọng hơn: danh mục 3.0, đặc điểm 2.0, hình thức bán 2.0, phân loại 1.0.

Riêng độ hợp đặc điểm chia cho **TỔNG** điểm nhãn chứ không chia cho nhãn
cao nhất. Nhờ vậy khớp nhiều đặc điểm được cộng thêm thật, nhưng không
bao giờ vượt 1.0. Chia cho giá trị lớn nhất — cách viết thoạt nhìn cũng
hợp lý — khiến sản phẩm chạm một đặc điểm được coi là hợp y hệt sản phẩm
chạm cả ba, và trục mất khả năng phân biệt.

`accessory_for` bị loại khỏi chân dung: đó là nhãn nội bộ để tra phụ kiện
mua kèm, không phải sở thích. Để lọt vào thì mọi phụ kiện "dùng cho mọi
loại hàng" khớp với tất cả mọi người, và khối gợi ý biến thành quầy bán
chậu.

---

## QĐ-134. Quan hệ họ hàng chỉ tính từ bậc HỌ trở xuống

Mọi cây trong cửa hàng đều cùng **Giới Thực vật**. Nếu trục phân loại lan
điểm lên tới bậc đó thì nó cộng đúng một hằng số vào mọi ứng viên — không
phân biệt được gì, mà vẫn *trông như* đang chạy.

Tệ hơn: câu lý do sẽ là "Cùng giới Thực vật", một câu đúng tuyệt đối và
vô dụng tuyệt đối.

Từ **Họ → Chi → Loài** mới là quan hệ đủ hẹp để có nghĩa với người mua.
Đo trên dữ liệu thật: khách xem Monstera (họ Ráy) thì được gợi ý "Trầu bà
leo cột — Cùng họ Ráy". Đó là thứ trước đây bộ máy không nói được.

Điểm được **lan lên chuỗi tổ tiên** chứ không chỉ nằm ở đúng nút: xem một
cây Monstera deliciosa thì Chi Monstera và Họ Ráy cũng được điểm — vì đó
chính là thứ làm Trầu bà, cùng họ khác chi, trở nên đáng gợi ý.

---

## QĐ-135. Trọng số quyết định THỨ TỰ, độ cụ thể quyết định LỜI GIẢI THÍCH

Hai việc khác nhau, và gộp chúng làm hỏng việc thứ hai.

Bản đầu lấy lý do từ trục có điểm nhân trọng số cao nhất. Kết quả: danh
mục (trọng số 3.0) gần như luôn thắng, và **mọi gợi ý trên trang đều nói
đúng một câu**: *"Vì bạn quan tâm Cây để bàn"*. Câu đó đúng, nhưng nó
không giải thích vì sao khách thấy CHÍNH sản phẩm này chứ không phải mười
sản phẩm khác cùng danh mục — mà đó mới là câu hỏi họ đang có. Hai trục
mới khi ấy chạy trong bóng tối: đổi thứ tự nhưng không bao giờ được nhắc
tên.

Nên lý do xếp theo **độ hẹp**: loài/chi → đặc điểm → hình thức bán → danh
mục. Trục hẹp hơn nói được nhiều hơn.

Kèm **ngưỡng 0.5** để chặn nói quá: một trục chỉ được đứng ra làm lý do
khi độ hợp của nó ít nhất bằng một nửa. Không có ngưỡng thì một sản phẩm
trùng đúng một nhãn phụ sẽ được quảng cáo là "cũng tông màu trắng" trong
khi nó lọt vào danh sách chủ yếu nhờ danh mục — một lời giải thích sai.

---

## QĐ-136. Cố vấn giá: không đủ dữ liệu thì KHÔNG đề xuất

Đây là nguyên tắc số một của `PricingAdvisor`, và là lý do phần lớn bài
kiểm thử của nó khẳng định công cụ **im lặng**.

Cửa hàng có vài chục đơn. Ở quy mô đó, phần lớn sản phẩm không có đủ số
liệu để nói bất cứ điều gì về giá — và câu trả lời đúng là im lặng, không
phải một đề xuất nghe cho có.

**Một công cụ luôn đưa ra được năm đề xuất cho mọi cửa hàng là một công cụ
đang bịa.** Nguy hiểm hơn cả việc không có công cụ nào, vì admin sẽ đổi
giá bán thật theo nó.

Ba tầng chặn:

1. `min_views = 20` — sản phẩm dưới ngưỡng bị bỏ qua hoàn toàn. Một món
   có 3 lượt xem và 0 đơn không nói gì về giá; nó chỉ nói rằng gần như
   chưa ai nhìn thấy. Vấn đề ở đó là **hiển thị**, không phải giá.
2. `confident_orders = 30` — dưới ngưỡng thì trang **tự cảnh báo mẫu
   mỏng**, ngay trên đầu, trước khi admin đọc đề xuất nào.
3. Mỗi đề xuất **bắt buộc mang theo con số** đã sinh ra nó.

Trang cũng công khai số sản phẩm bị bỏ qua. Đo trên dữ liệu thật: xét 53
sản phẩm, **39 bị bỏ qua vì thiếu dữ liệu**, 10 có đề xuất. Nếu chỉ khoe
10 đề xuất mà im lặng về 39 kia thì admin sẽ tưởng đó là toàn cảnh cửa
hàng.

---

## QĐ-137. Không đề xuất một con số giá cụ thể — chỉ đưa mốc tham chiếu có thật

Muốn nói "nên bán 420.000đ" thì phải biết **độ co giãn của cầu theo giá**,
thứ chỉ đo được bằng cách thử nhiều mức giá trên nhiều nghìn lượt mua. Ở
đây không có dữ liệu đó và sẽ không có.

Thứ tính được thật là **trung vị giá của những sản phẩm cùng danh mục ĐÃ
BÁN ĐƯỢC**. Một con số có thật, admin đối chiếu được, không giả vờ là lời
tiên tri.

Ba lựa chọn bên trong nó:

- **Trung vị, không phải trung bình.** Một lẵng hoa khai trương 3.000.000đ
  nằm chung danh mục với chục bó vài trăm nghìn sẽ kéo trung bình lên tới
  mức không sản phẩm nào ở gần — rồi mọi món còn lại đều bị gắn nhãn
  "dưới mặt bằng".
- **Chỉ tính hàng đã bán được.** Mặt bằng phải là giá khách THẬT SỰ đã
  trả. Gộp cả hàng ế vào thì mốc bị kéo về phía đúng những mức giá đang
  không hiệu quả — rồi công cụ lấy chính mốc hỏng đó đi khuyên người khác.
- **Cần ít nhất ba sản phẩm.** Trung vị của hai món là điểm giữa của đúng
  hai con số, không phải một mặt bằng.

---

## QĐ-138. Năm tình huống giá là năm câu hỏi khác nhau, không phải một thang mức độ

Cám dỗ là gộp tất cả thành "nên giảm nhiều / vừa / ít". Nhưng bốn trong
năm tình huống **không dẫn tới giảm giá**, và một cái dẫn tới điều ngược
lại. Gộp vào một thang là biến công cụ thành cái máy khuyên giảm giá —
thứ admin sẽ bỏ qua sau tuần đầu.

| Tình huống | Việc nên làm |
|---|---|
| Nhiều người xem, chưa ai đặt | thử giảm giá, hoặc xem lại ảnh/mô tả |
| **Thêm giỏ nhiều, ít đơn** | **giá KHÔNG phải vấn đề** — kiểm phí ship và bước thanh toán |
| Tồn kho nằm lâu | cân nhắc xả hàng |
| Đang giảm giá mà vẫn không bán | giảm sâu thêm cũng không giải quyết được |
| Bán tốt, giá dưới mặt bằng | cân nhắc **nâng** giá |

Tình huống thứ hai là tình huống đáng giá nhất. Khách đã bỏ vào giỏ tức
là **đã chấp nhận giá**; nghẽn nằm ở bước thanh toán. Gộp nó chung với
"không ai mua" là dẫn admin đi giảm giá để chữa một vấn đề không nằm ở
giá — tiền mất, mà lỗi vẫn còn nguyên.

Lời khuyên viết như **đề xuất, không như lệnh**. Công cụ nhìn thấy lượt
xem và đơn hàng; nó không nhìn thấy giá vốn, hợp đồng với nhà vườn, hàng
sắp về, hay việc admin đang giữ giá cao có chủ đích.

---

## QĐ-139. Công cụ không biết tính mùa vụ, và phải tự nói ra điều đó

Lỗi thật, thấy ngay trên dữ liệu của cửa hàng: tháng 9, công cụ khuyên xả
**"Cành đào phai chơi Tết"** vì 57 ngày không bán được và còn 5 cành trong
kho.

Cả hai con số đều đúng. Kết luận thì sai hoàn toàn — đào không bán được
vào tháng 9 là chuyện đương nhiên, không phải dấu hiệu ế.

Không sửa được bằng dữ liệu: cần lịch sử bán ít nhất qua một vòng năm mới
nhìn ra chu kỳ, mà cửa hàng chưa có. Sửa bằng cách **đoán theo tên sản
phẩm** thì càng tệ — nó sẽ đúng vài lần rồi sai một lần không ai kiểm
được.

Nên nói thẳng giới hạn, và nói **ngay trong câu khuyên** chứ không giấu
xuống chú thích cuối trang: *"công cụ KHÔNG biết hàng nào bán theo mùa —
đào, quất, hoa Tết nằm im trái vụ là bình thường."* Admin biết món nào
theo mùa; công cụ thì không.

---

## QĐ-140. "Bán được" ở cố vấn giá là ĐƠN ĐÃ ĐẶT, khác với trang Phân tích

Trang Phân tích dùng trạng thái `Completed` để tính doanh thu. Cố vấn giá
dùng **mọi đơn trừ đơn huỷ**.

Không phải mâu thuẫn — hai câu hỏi khác nhau. Ở đây câu hỏi là "có bao
nhiêu người **muốn** mua món này", mà ý muốn thể hiện ngay lúc đặt. Một
đơn đang trên đường giao vẫn là một lần khách quyết định mua. Chỉ trừ đơn
đã huỷ: đó là ý muốn đã rút lại.

Nếu chỉ tính đơn đã giao thì mọi đơn đang vận chuyển biến mất khỏi số
liệu, và một món vừa bán mười đơn tuần này vẫn bị báo là "không ai mua".

Vì hai trang cho hai con số khác nhau nên giao diện **phải ghi rõ "đơn đã
đặt"** — nếu không admin sẽ tưởng một trong hai đang sai.

---

## QĐ-141. Lịch dịp lễ chỉ NHẮC, và không bao giờ đoán ngày âm lịch

`OccasionCalendar` đối chiếu các dịp sắp tới với những chương trình đang
chạy hoặc đã lên lịch, rồi nhắc dịp nào chưa có gì phủ.

**Không tự tạo chương trình.** Tạo chương trình là quyết định giá bán, và
quyết định giá phải có người bấm nút — không phải một tác vụ nền chạy lúc
nửa đêm rồi sáng ra cả cửa hàng giảm 20%. Route của trang này chỉ có
`GET`, không có đường ghi nào.

**Dịp âm lịch không được gán ngày dương.** Tết Nguyên đán, Vu Lan, Trung
thu rơi vào ngày dương khác nhau mỗi năm; viết cứng một ngày là ghi một
dữ kiện sai cho mọi năm trừ một năm — và nó sai **một cách im lặng**, vì
hệ thống vẫn chạy.

PHP không có sẵn phép đổi âm–dương, và kéo một thư viện lịch về chỉ để
nhắc vài lần một năm là đổi một phụ thuộc lấy một tiện ích nhỏ. Nên các
dịp âm lịch nằm ở danh sách riêng: có tên, có ghi chú "15 tháng 7 âm
lịch", **không có ngày và không kết luận đã phủ hay chưa** — vì không
biết ngày thì không kiểm được. Nói "chưa có chương trình cho Tết" khi
admin đã tạo một chương trình Tết là cảnh báo sai, và vài lần như thế là
admin ngừng đọc cả khối.

---

## QĐ-142. Đếm và khuyên phải nằm ở hai lớp khác nhau

`DemandSignals` chỉ **đếm**; `PricingAdvisor` chỉ **khuyên**.

Đếm là việc của cơ sở dữ liệu và không có chỗ cho ý kiến. Khuyên thì toàn
là ý kiến. Trộn hai thứ vào một lớp là mất khả năng phân biệt "số liệu
nói vậy" với "chúng ta nghĩ vậy" — và khi admin hỏi *"vì sao lại đề xuất
giảm giá"*, câu trả lời phải chỉ được vào **một con số**, không phải vào
một đoạn mã.

Cùng lý do, mọi ngưỡng nằm ở `config/pricing-advisor.php` chứ không rải
trong mã: chúng là **phán đoán, không phải sự thật**. Không con số nào ở
đó rút ra được từ lý thuyết; chúng hợp lý cho một cửa hàng cỡ này và sẽ
sai khi lượng truy cập tăng. Chôn vào giữa mã nguồn thì lần sửa sau phải
tìm trong năm hàm và sẽ sót một chỗ. Mỗi con số kèm lý do chọn — sửa thì
sửa cả lý do.

`ProductDemand::conversionRate()` trả **null khi chưa có lượt xem nào**,
không trả 0%: chưa ai xem thì chưa có gì để chuyển đổi, còn "0%" đọc ra
là "có người xem mà không ai mua" — một kết luận sai hoàn toàn. Cùng
nguyên tắc với `Journal::goalProgress()` ở QĐ-127.

Tỉ lệ chuyển đổi tính theo **số đơn**, không theo số lượng: một đơn 20
cành hoa là MỘT người quyết định mua, không phải hai mươi. Chia số lượng
cho lượt xem sẽ cho tỉ lệ trên 100% ở hàng bán theo lô — một con số vô
nghĩa mà vẫn trông như đang hoạt động tốt.

---

## QĐ-143. Nhật ký vẫn bị cấm cửa ở mọi bộ máy mới

Mục 4 thêm ba lớp đọc dữ liệu khách hàng: `TasteProfile`, `DemandSignals`,
`PricingAdvisor`. Cả ba đều nằm trong phạm vi cấm của QĐ-123.

Cám dỗ lớn nhất **không** nằm ở bộ máy gợi ý mà ở **cố vấn giá**: nhật ký
kiểu "Bảng giá" là nơi khách tự tay ghi ra mức giá họ đang chờ. Với một
công cụ định giá thì đó là dữ liệu quý nhất có thể tưởng tượng — và dùng
nó là bán đứng đúng lời hứa in trên trang nhật ký của họ.

`JournalPrivacyTest` mở rộng thêm hai bài: một bọc `DB::listen()` quanh
`PricingAdvisor`, một quanh cả **trang** `/admin/de-xuat-gia` (vì một
khối phụ trong Blade cũng có thể nạp quan hệ và mở lại cánh cửa vừa
khoá). Đã kiểm chứng bằng cách chèn một câu đọc bảng `journals` vào giữa
`PricingAdvisor` — cả hai bài đỏ, kèm đúng câu SQL.

---

## QĐ-144. Thứ tự chèn dữ liệu có thể làm một bài kiểm thử xanh mà không kiểm gì

Bài học lặp lại của QĐ-124, lần này ở dạng khác và suýt lọt lần nữa.

Bài "màu sắc đã xem đẩy sản phẩm cùng màu lên trước" **XANH kể cả khi tắt
hẳn trục đặc điểm** (đặt trọng số về 0). Bài "cùng chi thực vật được gợi
ý" cũng vậy.

Lý do: khi mọi ứng viên hoà điểm, `sortByDesc` của Laravel **giữ nguyên
thứ tự cũ** — tức là thứ tự id, tức là thứ tự tạo trong bài. Ứng viên
"đúng" tình cờ được tạo trước nên luôn đứng đầu. Bài đang đo thứ tự chèn
dữ liệu chứ không đo thuật toán.

Sửa: tạo ứng viên "đúng" **sau cùng**. Khi đó chỉ một trục chạy thật mới
kéo được nó lên đầu.

Cùng loại lỗi ở bài "mặt bằng giá chỉ tính từ hàng đã bán": bản đầu tạo
đúng một món ế, và bỏ hẳn phép lọc vẫn cho trung vị y hệt (thêm một giá
trị vào dãy 100/150/200/300 vẫn ra 200). Phải ba món ế thì trung vị mới
bị kéo từ 200k lên 300k, và bài mới canh được cái nó tuyên bố.

**Quy tắc rút ra:** với bài kiểm thử về THỨ TỰ hoặc về THỐNG KÊ, dữ liệu
mẫu phải được dựng sao cho kết quả ĐỔI khi luật bị phá. Xanh không đủ —
phải phá thử và thấy nó đỏ.

Toàn bộ 12 bài mới của mục 4 đã qua cách kiểm này: 12 đột biến chèn vào
mã thật, mỗi cái làm đỏ đúng bài canh nó.

---

## QĐ-145. Dự án đã có Git — sau hai lần suýt mất việc cả buổi

QĐ-122 ghi lại sự cố một script sửa hàng loạt xoá trắng
`welcome.blade.php`, và kết luận là "dự án cần Git". Nay đã có.

`git init` + commit đầu tiên gồm 666 tệp. Trước khi commit đã quét toàn
bộ cây thư mục tìm token, mật khẩu và số tài khoản: **không có gì nằm
ngoài `.env`**, và `.env` đã được `.gitignore` từ trước.

Chưa có kho trên GitHub. Việc tạo kho và đẩy lên cần đăng nhập, nên phải
do chủ dự án tự chạy — xem hướng dẫn ở cuối `README`.

Cấu hình đặt ở mức repo, không phải toàn máy:

```
git config user.name  "Rin5"
git config user.email "tuanhung6a6@gmail.com"
```

---

## QĐ-146. Tên thương hiệu: Angevil

Đổi ở **một chỗ**: `StoreProfile::FIELDS['site_name']` và bản ghi tương
ứng trong bảng `settings`.

Đó chính là thứ QĐ-120 dựng lên để có: trước khi có `StoreProfile`, tên
cửa hàng viết cứng ở 24 chỗ, và đổi tên nghĩa là sửa 24 tệp rồi bỏ sót
một chỗ — thường là một mẫu thư, tức là chỗ khách nhìn thấy mà chủ cửa
hàng thì không.

Lần này chỉ còn ba chỗ viết cứng sót lại (một câu cảm ơn trong
`OrderStatus`, một câu ở `ProfileController`, một phép khẳng định trong
bài kiểm thử). Cả ba đã nối vào `StoreProfile::name()`.

---

## QĐ-147. Mỗi loại sổ một KẾT CẤU, không chỉ một bộ gợi ý chỉ số

Bản đầu của nhật ký: năm loại sổ dùng chung đúng một bố cục và đúng một
biểu mẫu. Loại sổ chỉ quyết định **chỉ số nào được điền sẵn**.

Hậu quả đo được trên giao diện thật:

- Sổ "Theo dõi giá" hiện ô **tình trạng cây** và ô **tải ảnh**, còn ô để
  ghi GIÁ thì không có — người dùng phải tự gõ chữ "Giá" vào một hàng chỉ
  số.
- Sổ "Mục tiêu" không có chỗ nào để liệt kê các bước cần làm — thứ duy
  nhất khiến nó là sổ mục tiêu.

Nay `JournalKind` khai ba danh sách:

| | Sinh trưởng | Mục tiêu | Theo dõi giá | Phân tích | Tự do |
|---|---|---|---|---|---|
| **Khối trang sổ** | tiến độ, biểu đồ, dải ảnh, tổng hợp chăm sóc, dòng thời gian | tiến độ, **mốc cần đạt**, biểu đồ, dòng thời gian | **thống kê giá**, biểu đồ, **bảng khảo giá** | **điểm chấm**, **được/chưa được**, biểu đồ, dòng thời gian | biểu đồ, dòng thời gian |
| **Ô biểu mẫu** | tình trạng, **việc đã chăm**, ảnh, nhãn dán, chỉ số | nhãn dán, chỉ số | **giá**, **nơi khảo** | **chấm 1–5**, **được/chưa được**, tình trạng, ảnh | ảnh, chỉ số |
| **Nhãn dán** | 12 | 3 | 3 | 10 | 12 |

`show.blade.php` KHÔNG biết sổ mục tiêu khác sổ giá ở chỗ nào — nó lặp
qua `panels()` rồi vẽ. Viết `@if($journal->kind === Price)` trong Blade
thì năm loại × sáu khối là ba mươi nhánh điều kiện trong một tệp, và thêm
loại sổ thứ sáu là phải đọc lại cả ba mươi.

---

## QĐ-148. Cột JSON `data` được phép tồn tại, với đúng ba điều kiện

Mỗi loại sổ cần những trường khác hẳn nhau. Làm thành cột riêng thì
`journal_entries` có thêm chín cột mà mỗi trang chỉ dùng hai ba cột, bảy
cột còn lại NULL vĩnh viễn — và mỗi loại sổ mới là một migration nữa.

Cột JSON `data` giải quyết được, nhưng chỉ khi giữ đúng ba điều kiện —
copy nguyên từ ràng buộc đã đặt cho `product_traits` (xem `TraitType`):

1. Khoá hợp lệ do `JournalKind::dataFields()` khai, **đóng**.
2. Controller chỉ ghi những khoá loại sổ đó khai (`truongRieng()`).
3. **Không khoá nào được dùng để truy vấn, lọc hay thống kê chéo** —
   chúng chỉ để hiển thị lại đúng trang đó.

Mất điều kiện 3 thì phải tách cột thật, vì JSON không đánh chỉ mục được
theo cách này.

Đo được: điều kiện 1 và 2 là **hai lớp chặn độc lập**. Phá riêng lớp
validate, hoặc riêng `truongRieng()`, thì bài kiểm thử vẫn xanh — lớp còn
lại giữ được. Chỉ khi phá cả hai nó mới đỏ. Đó là chủ ý, và đã ghi rõ
trong bài để người đọc không tưởng nó canh đúng một dòng.

---

## QĐ-149. Mốc mục tiêu là bảng riêng, và `done_at` thay cho cột boolean

Một mốc KHÔNG thuộc về một trang nhật ký nào cả. Nó thuộc về cả quyển sổ,
có thứ tự riêng, được đánh dấu hoàn thành ở một thời điểm khác với lúc
tạo. Đó là một **thực thể**, không phải một thuộc tính — nên nó là bảng.

`done_at` thay cho `is_done`: biết một mốc đã xong thì hữu ích, biết nó
xong **ngày nào** thì hữu ích hơn nhiều — đó là thứ dựng được câu "mất ba
tuần để đi từ mốc này sang mốc kia". NULL nghĩa là chưa xong. Một cột trả
lời được hai câu hỏi.

`done_at` **cố ý không nằm trong `$fillable`**, cùng lý do với `user_id`:
nó là thứ hệ thống ghi lúc người dùng bấm nút, không phải thứ nhận từ dữ
liệu gửi lên. Cho vào `$fillable` thì ai cũng đặt được một ngày hoàn
thành tuỳ ý, và mọi phép tính thời gian thành vô nghĩa.

Mốc **đã xong thì không bao giờ là quá hạn**, kể cả khi xong muộn: đánh
dấu đỏ một việc người ta đã làm xong là trách móc chuyện đã qua, và nó
đẩy sự chú ý ra khỏi những mốc còn đang dở.

---

## QĐ-150. Trang trí sổ: bộ chọn sẵn, không phải ô chọn màu tự do

Sáu bộ giao diện (giấy, màu nhấn, hoa văn) và mười hai nhãn dán.

**Không cho chọn mã màu tự do.** Nghe thì tự do hơn, nhưng kết quả là
những quyển sổ chữ xám nhạt trên nền xám nhạt — và không có gì trong hệ
thống ngăn được, vì màu nào cũng "hợp lệ".

Sáu bộ đã **ĐO** tương phản màu nhấn trên màu giấy, ở cả hai chế độ nền:

| bộ | nền sáng | nền tối |
|---|---|---|
| Giấy trắng | 6,05 | 4,61 |
| Lá non | 8,18 | 7,34 |
| Gốm đỏ | 5,63 | 7,20 |
| Chiều tím | 7,90 | 7,18 |
| Cát ấm | 5,87 | 8,77 |
| Rêu đá | 7,78 | 7,52 |

Cả mười hai tổ hợp vượt 4,5:1 — ngưỡng của **chữ thường**, cao hơn hẳn
ngưỡng 3:1 mà nét đồ hoạ cần. Thêm bộ mới thì phải đo lại và ghi số vào
bảng: con số đo được là thứ duy nhất chứng minh được, "trông thì ổn" thì
không.

Nền tối **không phải phép lật tự động**: mỗi bộ khai lại ba biến của
riêng nó, vì "giấy trắng" ở nền tối không thể là màu trắng.

---

## QĐ-151. Nhãn dán vẽ bằng SVG và CÓ NGHĨA, không dùng emoji

Ba lý do không dùng emoji:

1. Hiển thị khác nhau trên từng hệ điều hành — cùng một trang nhật ký,
   máy này ra hình này, máy kia ra hình khác.
2. Không ăn theo màu được; SVG thì theo được màu của bộ giao diện sổ.
3. Trình đọc màn hình đọc emoji ra một cái tên tiếng Anh dài dòng.

Mỗi nhãn kèm một `meaning()` — "hôm nay đã tưới", "cây ra hoa", "bị sâu".
Nhìn lướt dòng thời gian là thấy chuyện gì đã xảy ra mà không phải đọc
từng trang. Đó cũng là lý do bộ nhãn **đóng** chứ không cho tự tải lên:
một bộ hình có ý nghĩa chung thì đọc lướt được; một bộ ai thích gì dán
nấy thì chỉ là hình.

Nhãn dán và ô tích "việc đã chăm" dùng **chung một bộ từ vựng**. Tách làm
hai bộ thì người dùng phải khai hai lần cho một việc, và hai chỗ sẽ lệch
nhau.

Bài kiểm thử `moi_nhan_dan_deu_ve_ra_hinh_that` render từng nhãn và
khẳng định SVG có nét vẽ: enum có 12 case nhưng hình nằm trong một
`@switch` ở Blade, và `@switch` không khớp thì **lặng lẽ** bỏ qua — thêm
case mà quên vẽ hình thì nhãn đó hiện ra một ô trống, không có gì báo.

---

## QĐ-152. Hai lỗi tìm ra khi đối chiếu ba danh sách khai báo với nhau

`JournalKind` khai ba thứ tách rời — `panels()`, `entryFields()`,
`dataFields()`. Không có gì trong PHP bắt chúng khớp nhau, và cả hai lỗi
dưới đây đều **xanh ở mọi bài kiểm thử đang có** lúc đó:

**Lỗi 1 — biểu đồ trống vĩnh viễn.** Sổ Theo dõi giá có `'chart'` trong
`panels()` nhưng KHÔNG có `'metrics'` trong `entryFields()` — nó có một ô
nhập giá riêng. Người dùng tạo sổ giá bằng giao diện thật sẽ thấy một
khối biểu đồ không bao giờ có dữ liệu.

Lỗi bị che vì dữ liệu mẫu tôi dựng bằng script đã tự ghi thêm chỉ số
"Giá" — thứ mà biểu mẫu thật không làm.

Sửa: giá vừa nhập được ghi luôn thành chỉ số "Giá". Không phải bịa dữ
liệu — nó chính là con số họ vừa gõ, chỉ được ghi thêm vào chỗ mà biểu đồ
đọc.

**Lỗi 2 — lời hứa hão trên trang tạo sổ.** Khối "sổ này hoạt động thế
nào" quảng cáo *"chỉ số điền sẵn: Giá (₫)"* cho một loại sổ không có ô
chỉ số nào. Đúng loại lời hứa hão mà cả khối đó sinh ra để tránh.

`JournalKindStructureTest` nay đối chiếu ba danh sách với nhau: **có khối
biểu đồ thì phải có đường ghi ra chỉ số**.

---

## QĐ-153. Đường dẫn tệp không bao giờ được nằm trong `$fillable`

Lỗi thật, bắt được bằng `JournalCoverTest::thay_anh_bia_thi_xoa_tep_cu`:

`cover_image` nằm trong `$fillable` của `Journal`. Khi sửa sổ,
`fill($validated)` gán thẳng **đối tượng UploadedFile** đè lên đường dẫn
cũ. Dòng ngay sau đó đọc `$so->cover_image` để xoá tệp cũ — và nhận được
một đối tượng, không phải đường dẫn. Tệp cũ nằm lại vĩnh viễn.

Mỗi lần đổi ảnh bìa là một tệp rác. Không ai thấy, vì trang vẫn hiện đúng
ảnh mới.

Cùng nguyên tắc với `user_id` và `JournalMilestone::done_at`: những giá
trị do **hệ thống sinh ra** — id chủ sở hữu, dấu thời gian, đường dẫn tệp
— không bao giờ được nhận từ dữ liệu gửi lên.

Ảnh bìa cũng có **ba trạng thái, không phải hai**: ô tải tệp để trống có
thể nghĩa là "không đổi gì" HOẶC "bỏ ảnh đi", và trình duyệt gửi lên y
hệt nhau. Không phân biệt được thì người dùng không bao giờ gỡ được ảnh
bìa đã lỡ chọn — mỗi lần lưu là ảnh cũ lại quay về. Nên có ô tích
`remove_cover` riêng.

---

## QĐ-154. Đổi phần bên dưới theo kiểu sổ, bằng cách nâng cấp dần

Trang tạo sổ hiện một khối "sổ này hoạt động thế nào" **đổi theo kiểu sổ
đang chọn**: biểu mẫu sẽ hỏi những ô nào, nút ghi tên gì, có bao nhiêu
nhãn dán, giao diện mặc định là bộ nào.

Máy chủ vẽ ra **đủ cả năm** khối; JavaScript chỉ ẩn bớt. Không có script
thì cả năm cùng hiện — dài hơn nhưng đọc vẫn đúng, và không mất ô nhập
nào. Vẽ sẵn một khối rồi để script đổi nội dung thì người tắt script kẹt
vĩnh viễn ở kiểu sổ mặc định.

Nội dung mỗi khối **lấy từ enum**, không chép tay: `entryFields()` và
`suggestedMetrics()` là cùng nguồn mà biểu mẫu ghi thêm sẽ dùng sau này.
Chép tay thì mô tả ở đây và thứ hiện ra thật sẽ lệch nhau ngay lần sửa
đầu tiên — và người dùng phát hiện bằng cách chọn nhầm kiểu sổ.

Lỗi đã bắt được khi đo: bản đầu đặt `data-for-kinds` liệt kê **cả năm**
loại lên khối duy nhất, nên script chạy mà không bao giờ ẩn gì. Bài kiểm
bằng cách bấm lần lượt năm loại rồi đọc lại trạng thái `hidden` mới lộ ra.

---

## QĐ-155. Không nhắc "đã đến lúc tưới chưa"

Khối "Đã chăm những gì" đếm số lần đã làm từng việc và nói lần gần nhất
cách đây bao lâu. Nó **không** nhắc "nên tưới hôm nay".

Chu kỳ tưới phụ thuộc loài, mùa, chậu, chỗ đặt và thời tiết tuần đó — hệ
thống không biết gì trong số đó. Đưa ra một lời nhắc dựa trên phép đếm
ngày là bịa một lời khuyên chăm cây, và người tin theo có thể làm úng
cây.

Nói *"đã tưới 6 lần, gần nhất 3 ngày trước"* là sự thật. Nói *"nên tưới
hôm nay"* thì không.

Cùng ranh giới ở sổ Theo dõi giá: đưa ra số liệu của chính khách rồi để
họ tự quyết, **không** khuyên "nên mua" hay "nên đợi" — sổ này là ghi
chép của khách, còn cửa hàng thì có lợi ích trong việc họ mua sớm.

---

## QĐ-156. Thư viện ảnh sản phẩm: một khung rỗng có viền suốt từ đầu

`components/product/gallery.blade.php` dựng sẵn khung thư viện đầy đủ:
ảnh lớn, hàng ảnh nhỏ bấm để đổi, JavaScript đổi cả `<source>` lẫn
`<img>`. Hàng ảnh nhỏ có điều kiện `@if(count($paths) > 1)`.

Bảng `product_images` **rỗng hoàn toàn** — 0 dòng. Nên điều kiện đó chưa
bao giờ đúng, và mọi sản phẩm hiện đúng một ảnh. Cả tính năng chạy không
lỗi, không cảnh báo, và không làm gì.

Nay 101 ảnh phụ cho 51/53 sản phẩm. Hai món còn lại (hoa hồng đỏ, phân
bón NPK) không tìm được ảnh thứ hai đủ điều kiện — và đó là câu trả lời
đúng, không phải chỗ để nhét một ảnh gần đúng.

Ba lựa chọn trong `tools/fetch-product-gallery.mjs`:

- **Không lấy trùng ảnh đại diện.** Cùng câu truy vấn thì Openverse trả
  về cùng thứ tự, nên ảnh đầu gần như luôn là ảnh đã dùng. Loại theo
  `foreign_landing_url` chứ không theo tên tệp — cùng một bức có thể tải
  về dưới hai tên.
- **Gom kết quả từ MỌI câu truy vấn**, khác `fetchInto` vốn dừng ở câu
  đầu tiên có kết quả. Câu thứ hai thường cho góc chụp khác hẳn, đúng thứ
  một thư viện cần.
- **Hai ảnh phụ, không hơn.** Cộng ảnh đại diện là ba ô — đủ để hàng ảnh
  nhỏ có nghĩa. Nhiều hơn thì mỗi lần mở trang thêm vài trăm KB cho thứ
  phần lớn khách không bấm tới, và ảnh stock thứ tư trở đi thường đã lạc
  đề.

`alt` của ảnh nhỏ để **rỗng** có chủ ý: nút bấm đã mang
`aria-label="Xem ảnh N"`, nên lặp lại làm trình đọc màn hình đọc hai lần.

---

## QĐ-157. Hai lỗi im lặng trong danh sách truy vấn ảnh

**Slug trùng.** `vien-dat-nung-lot-day-chau-1kg` nằm hai lần trong danh
sách. Hậu quả không hiện ra ở đâu:

- mỗi lần chạy tốn gấp đôi lượt gọi API cho món đó;
- hai mục ghi đè lên **cùng một tên tệp**, nên tệp trên ổ đĩa là của mục
  chạy sau, còn dòng ghi công có thể là của mục chạy trước — tức là
  ASSETS.md ghi **sai tác giả và sai giấy phép**.

Ghi sai giấy phép nghiêm trọng hơn hẳn một lỗi kỹ thuật: đó là điều kiện
để được dùng bức ảnh. Nay `fetchInto()` ném lỗi ngay khi thấy slug trùng.

**Slug không khớp sản phẩm nào.** `monstera-deliciosa` (đúng là
`monstera-deliciosa-chau-gom`) và `cay-luoi-ho-vang-vien-de-ban` (đúng là
`luoi-ho-vang-vien-de-ban`). Lệnh gán báo `thiếu: 2` suốt từ đầu — một
con số đếm mà không ai đi tìm được.

Nay lệnh **nêu tên** từng slug lạc kèm lý do và chỉ luôn tệp cần sửa. Một
con số không đủ để ai hành động; một cái tên thì đủ.

Danh sách truy vấn cũng đã tách khỏi `fetch-product-photos.mjs` sang
`tools/lib/product-targets.mjs` để công cụ ảnh thư viện dùng chung. Chép
bản thứ hai thì sớm muộn hai bản lệch, và lệch ở đây nghĩa là mất những
luật `must`/`block` đã chặn được một bức tranh sơn dầu năm 1840 lọt vào
chỗ giỏ hoa baby.

---

## QĐ-158. Banner khuyến mại tải về nhưng KHÔNG tự gán

`tools/fetch-promotion-banners.mjs` tải ảnh nền cho ba chủ đề (Giáng
sinh, Tết, Valentine) nhưng **không ghi vào cơ sở dữ liệu**.

Banner là quyết định thương hiệu. Một bức ảnh stock chọn hộ chưa chắc hợp
với chiến dịch đang chạy, và khối `campaign-banner` đã có sẵn trạng thái
không-ảnh trông vẫn tử tế (`campaign-banner--has-image` là một modifier,
không phải mặc định). Ảnh chỉ làm nó đẹp hơn, không phải thứ thiếu-thì-vỡ.

Khoá theo `theme_key` chứ không theo slug chương trình: nhiều chương
trình cùng chủ đề dùng chung được một ảnh, và chủ đề lặp lại hằng năm còn
slug thì không.

---

## QĐ-159. Ảnh nút phân loại: chỉ bậc HỌ mới cần

31/66 nút phân loại chưa có ảnh, và **không nút nào trong số đó từng hiện
ra**: trang danh sách chỉ hiện bậc Họ (xem chú thích trong
`shop/taxa/index.blade.php` — Ngành và Lớp quá rộng, Chi và Loài quá
hẹp), còn trang chi tiết không dùng ảnh nút.

Cả 15 họ đều đã có ảnh. Nên 31 nút kia không phải việc còn thiếu — chúng
là dữ liệu không có chỗ hiển thị. Tải ảnh cho chúng là tốn băng thông và
tốn chỗ trong ASSETS.md để đổi lấy đúng con số không.

---

## QĐ-160. Voucher hết hạn kẹt trong ví vĩnh viễn — lỗi nằm ở thứ tự @elseif

Nút "Bỏ khỏi ví" nằm bên trong nhánh `@elseif($saved)` của một chuỗi
điều kiện. Ba nhánh đứng TRƯỚC nó — "đã dùng hết lượt", "đã hết mã",
"hết hạn sử dụng" — bắt trước, nên một mã đã lưu mà hết hạn **không bao
giờ chạy tới** nhánh `$saved`.

Kết quả: ví đầy dần bằng mã không dùng được nữa, và mã còn dùng được thì
lẫn vào giữa. Mọi bài kiểm thử lúc đó đều xanh, vì không bài nào mở
trang ví với một mã vừa đã-lưu vừa đã-hết-hạn.

**Trạng thái và hành động là hai câu hỏi khác nhau**: *"mã này còn dùng
được không"* và *"tôi có muốn giữ nó không"*. Gộp vào một chuỗi điều
kiện thì câu thứ hai bị câu thứ nhất nuốt mất. Nút bỏ nay đứng ngoài
chuỗi: đã ở trong ví thì bỏ được, bất kể trạng thái.

---

## QĐ-161. Bỏ mã đã dùng thì ẨN, không xoá — và phải có đường quay lại

Hàng `coupon_user` là **bằng chứng** khách đã dùng mã mấy lần, và
`per_user_limit` đếm dựa vào nó. Xoá hàng của một mã đã dùng là cho họ
dùng lại từ đầu — một mã "mỗi người một lần" thành mã không giới hạn cho
ai biết bấm nút xoá.

Nhưng nhu cầu của khách là **dọn ví**, không phải **xoá bằng chứng**. Nên
tách hai đường:

| | Hành động | Vì sao |
|---|---|---|
| Chưa dùng lần nào | xoá hàng thật | không có gì để giữ, và lưu lại được nếu đổi ý |
| Đã dùng | đánh dấu `hidden_at` | hàng còn nguyên, `per_user_limit` vẫn đếm đúng |

Đáp ứng đúng nhu cầu thì không phải nới lỏng gì cả.

**Phải có đường quay lại.** Một nút chỉ đi một chiều là cái bẫy: bấm nhầm
rồi thì mã biến mất và khách không biết nó đi đâu. Mục "mã đã ẩn" chỉ
hiện khi thật sự có mã bị ẩn — bày "đã ẩn (0)" cho mọi người là thêm thứ
để đọc mà không thêm thông tin.

---

## QĐ-162. Ảnh công khai PHẢI bị tước metadata — GPS là địa chỉ nhà khách

Ảnh chụp bằng điện thoại mang theo khối EXIF chứa **GPSLatitude /
GPSLongitude chính xác tới vài mét** — tức là địa chỉ nhà người chụp —
cùng giờ chụp, loại máy, đôi khi cả tên chủ máy.

Khách chụp cây trên ban công rồi đăng lên "Góc cây của bạn" là đăng luôn
chỗ mình ở, nếu không ai tước. Đây không phải rủi ro lý thuyết; đó là
cách người ta bị tìm ra địa chỉ từ một bức ảnh đăng công khai.

**Bản WebP vốn đã sạch** — `ImageOptimizer` dựng chúng bằng GD, mà GD chỉ
chép pixel. Nhưng **ảnh gốc** được lưu nguyên xi trong đĩa `public`, truy
cập thẳng được bằng `/storage/…`, và `<x-site.image>` cũng lùi về nó khi
chưa có WebP. Chỉ dựa vào WebP là để hở đúng tệp nguy hiểm nhất.

`ImageMetadataStripper` ghi đè chính ảnh gốc. Ba lựa chọn:

- **Tước cho MỌI ảnh, không có cờ bật/tắt.** Một cờ là một thứ để quên,
  và chỗ quên sẽ là chỗ mới thêm sau này. Không có trường hợp nào cửa
  hàng cần giữ toạ độ GPS của khách.
- **XOAY ẢNH TRƯỚC KHI XOÁ EXIF.** Điện thoại lưu ảnh ngang rồi ghi cờ
  `Orientation` bảo trình xem xoay 90°. Xoá EXIF mà không xoay pixel
  trước thì mọi ảnh chụp dọc nằm nghiêng vĩnh viễn. Thứ tự bắt buộc: đọc
  cờ, xoay pixel, rồi mới ghi lại không EXIF.
- **Tước hỏng thì ghi log mức `error`, không phải `warning`.** Ảnh không
  tối ưu được chỉ nặng hơn; ảnh không tước được là dữ liệu vị trí của
  khách còn nằm trên máy chủ.

Bài kiểm thử dựng một JPEG có **khối EXIF GPS thật**, ghép bằng tay ở mức
byte (PHP không có hàm ghi exif), và có một bài **tự bảo hiểm** khẳng
định tệp mẫu thật sự có GPS — không có nó thì mọi bài "đã xoá GPS" xanh
một cách vô nghĩa.

---

## QĐ-163. Blog là nền tảng chính, social là tầng phụ — theo đúng thứ tự đó

Ba tầng, ưu tiên giảm dần:

1. **Cẩm nang** (`/cam-nang`) — người mua cây gần như luôn tìm hiểu trước
   khi mua, và họ gõ câu hỏi vào Google. Một bài "7 loại cây để bàn ít
   cần ánh sáng" kéo khách suốt nhiều tháng; một bài kiểu mạng xã hội
   sống được vài ngày.
2. **Góc cây của bạn** (`/goc-cay`) — social **nhẹ**, cố ý nhẹ.
3. Đánh giá sản phẩm — đã có sẵn từ trước.

**Không dựng một Facebook thứ hai.** Không follower, không bảng tin theo
thuật toán, không nhắn tin, không story. Lý do không phải lười: khu vực
social mới sinh ra gặp bài toán con gà–quả trứng — không có người thì
trống trơn, mà trống trơn thì không ai vào. Một dòng ảnh khách đăng thì
dùng được ngay từ ngày đầu và không cần ai theo dõi ai.

**Cẩm nang ra thanh menu chính; Góc cây vào menu "Khác".** Cẩm nang là
cửa vào của khách đến từ Google — họ đọc bài rồi mới biết cửa hàng tồn
tại. Giấu nó sau một menu bung ra là giấu đúng lối đi mà nó sinh ra để
mở. Góc cây thì ngược lại: khách xem nó SAU khi đã biết cửa hàng.

**Bảng `blog_post_product` là thứ biến blog thành doanh thu.** Không có
nó thì bài viết chỉ là chữ, và khách đọc xong phải tự đi tìm cây. Dùng
bảng nối thay vì dán link vào nội dung: link dán tay chết khi sản phẩm
đổi slug hoặc ngừng bán, và không có gì báo.

Cột `note` trong bảng nối là lý do RIÊNG của bài này khi nhắc cây đó
("chịu bóng tốt nhất danh sách"). Cùng một cây ở ba bài có ba lý do khác
nhau; lấy mô tả chung của sản phẩm thì cả ba chỗ đọc như nhau.

---

## QĐ-164. Bài viết là chỗ DUY NHẤT in HTML thô — chỉ an toàn nhờ hai điều kiện

Mọi nơi khác trong dự án đều escape. Bài hướng dẫn thì cần đoạn văn, tiêu
đề phụ, danh sách — escape hết thì admin nhìn thấy thẻ `p` hiện ra thành
chữ.

An toàn được nhờ **cả hai** điều kiện, và cả hai phải giữ:

1. **Chỉ admin viết được.** Route ghi nằm sau `role:admin`. Nội dung
   người lạ gửi lên nằm ở "Góc cây của bạn", và ở đó nó được escape.
2. **Đi qua `HtmlSanitizer` LÚC LƯU**, không lúc hiện. Làm sạch lúc hiện
   thì mọi chỗ in bài phải nhớ gọi — trang bài, xem trước ở quản trị, thẻ
   mô tả, bản RSS về sau — và chỗ thứ ba sẽ quên.

**Danh sách CHO PHÉP, không phải danh sách cấm.** Cấm thẻ `script` thì
còn `iframe`, cấm cả hai thì còn `object`, `embed`, `svg onload`… không
bao giờ liệt kê hết. Danh sách cho phép thì thứ chưa nghĩ tới mặc định bị
gỡ — sai về phía an toàn.

**Lỗi đã sửa: thứ tự gỡ vỏ.** Bản đầu quyết định giữ/gỡ TRƯỚC rồi mới đệ
quy. Với `div > section > p` thì `div` bị gỡ vỏ, `section` được đẩy lên
chỉ số mà vòng lặp **vừa đi qua**, và không bao giờ được kiểm. Một thẻ
không được phép vẫn lọt ra trang, chỉ cần bọc nó trong một thẻ cũng không
được phép. Sửa: làm sạch cây con TRƯỚC, rồi mới gỡ vỏ.

Cũng ở đây: `loadHTML` mặc định đoán bảng mã ISO-8859-1 nên tiếng Việt có
dấu biến thành ký tự lạ — hỏng **lặng lẽ**, chữ vẫn hiện chỉ sai dấu.

---

## QĐ-165. Duyệt trước khi hiện, và từ chối phải có lý do

Nội dung người lạ đăng lên một trang bán hàng. Hiện ngay rồi gỡ sau nghĩa
là trong khoảng giữa hai việc đó, trang của cửa hàng đang hiển thị bất kỳ
thứ gì vừa được gửi lên — kể cả lúc 2 giờ sáng khi không ai trực.

Số bài mỗi ngày của một cửa hàng nhỏ đếm trên đầu ngón tay, nên duyệt tay
không phải gánh nặng. Khi nào nhiều tới mức không duyệt xuể thì đó là lúc
bàn tới tự động — không phải bây giờ.

- **`approved_at` / `rejected_at` thay cho cột trạng thái chuỗi.** Ba
  trạng thái suy ra được, và mỗi mốc kèm luôn thời điểm — thứ cần khi
  khách hỏi "bài tôi gửi hôm kia sao rồi".
- **Duyệt thì xoá dấu từ chối và ngược lại.** Một trạng thái phải là MỘT
  trạng thái, không phải hai dấu chồng nhau để `statusText()` đoán cái
  nào mới hơn.
- **Lý do từ chối bắt buộc, và hiện lại cho chính người đăng.** Từ chối
  im lặng thì khách đăng lại y hệt, rồi lại bị từ chối, và họ kết luận là
  trang bị hỏng.
- **Người đăng vẫn thấy bài của mình kèm trạng thái.** Gửi xong mà màn
  hình không đổi gì thì họ tưởng hỏng và gửi lại — rồi admin có ba bài
  giống hệt để duyệt.

---

## QĐ-166. Slug bài viết KHÔNG đổi theo tiêu đề

Sửa tiêu đề mà slug đổi theo là làm chết mọi link đã chia sẻ và mọi thứ
hạng Google đã có — **đúng thứ cả khu vực Cẩm nang sinh ra để xây**.

Slug sinh một lần lúc tạo bài rồi giữ nguyên. Admin đổi được bằng tay nếu
thật sự cần, nhưng nó không tự đổi sau lưng họ.

Kiểm trùng phải dùng `withTrashed()`: bài xoá mềm vẫn giữ slug, nên tạo
bài mới trùng tên sẽ đụng ràng buộc UNIQUE nếu không tính tới nó.

`published_at` là **trạng thái**, không chỉ là ngày: null = bản nháp,
quá khứ = đang hiển thị, tương lai = đã hẹn giờ. Một cột trả lời ba câu.
`scopePublished` phải kiểm cả `<= now()` — bỏ vế đó thì cả tính năng hẹn
giờ thành vô nghĩa mà không có gì báo.

Bài nổi bật ở trang danh sách xét theo **số trang yêu cầu**, không xét
trạng thái của đối tượng truy vấn: bản đầu kiểm `$query->getQuery()->
offset`, nhưng `paginate()` ở dòng trên đã đặt offset lên chính đối tượng
đó, nên điều kiện luôn sai và khối nổi bật không bao giờ hiện. Lỗi phụ
thuộc vào THỨ TỰ các khoá trong mảng — thứ không ai đọc code mà đoán ra.

Thời gian đọc đếm theo **khoảng trắng**, không dùng `str_word_count`: hàm
đó viết cho bảng chữ Latin không dấu, và với tiếng Việt mỗi ký tự có dấu
nằm ngoài danh sách sẽ cắt đôi từ.

---

## QĐ-167. Ba bài kiểm thử suýt vô nghĩa, và cách phát hiện

Lần thứ ba trong dự án gặp đúng kiểu này (xem QĐ-124, QĐ-144). Ghi lại vì
nó vẫn tái diễn:

**1. Ảnh giả không có EXIF.** Bài "ảnh đăng lên bị tước metadata" dùng
`UploadedFile::fake()->image()` — ảnh đó **không có EXIF ngay từ đầu**.
Chèn đột biến cho controller gọi thẳng `$file->store()` (bỏ qua toàn bộ
lớp tước) thì bài **vẫn xanh**. Sửa: dựng JPEG có EXIF GPS thật, và thêm
một bài tự bảo hiểm khẳng định tệp mẫu có GPS.

**2. Bài tin vào chính hàm kiểm của mình.** Bài "GPS biến mất" chỉ gọi
`metadataConLai()` của dự án. Cho hàm đó luôn trả mảng rỗng thì bài vẫn
xanh. Sửa: đọc thẳng bằng `exif_read_data` của PHP.

**3. Nhắm sai tầng khi chèn đột biến.** Hai bài về gán hàng loạt vẫn xanh
khi phá `$fillable`, vì controller dựng model bằng mảng tường minh — có
**hai lớp chặn độc lập**, và phá một lớp thì lớp kia giữ. Đó là chủ ý,
nhưng phải ghi rõ trong bài, vì người đọc dễ tưởng nó canh đúng một dòng.

**Quy tắc:** một đột biến sống sót không có nghĩa là mã sai — nó có nghĩa
là **bài kiểm thử chưa đo cái nó tuyên bố**, hoặc có một lớp bảo vệ khác
mà mình chưa biết. Cả hai trường hợp đều phải tìm ra bằng được lý do,
không được bỏ qua.

---

## QĐ-168. Thanh điều hướng tối đa NĂM mục — đo được, không phải sở thích

Đảo lại một phần QĐ-163. Ở đó Cẩm nang được đưa ra thanh chính với lý do
SEO: khách đến từ Google đọc bài rồi mới biết cửa hàng, nên lối vào đó
đáng được thấy ngay.

Lý do vẫn đúng, nhưng **cái giá đo được thì lớn hơn**. Ở khung 1280px —
độ phân giải laptop phổ biến nhất:

```
thương hiệu 186 + điều hướng 613 + khối phải 556 = 1419px
khung nhìn                                       = 1280px
                                        tràn ngang  139px
```

Cả trang trượt sang hai bên. Một lối vào đẹp trên lý thuyết mà làm hỏng
thanh điều hướng ở độ phân giải phổ biến nhất thì không đáng.

Bốn liên kết phẳng + menu "Khác" = năm mục là **ngưỡng đo được** của bố
cục hiện tại, không phải một con số chọn cho tròn. Muốn thêm mục thứ sáu
thì phải bớt chỗ ở nơi khác trước, và sửa cả hằng số trong
`HeaderNavTest` kèm phép đo mới.

Cẩm nang vào menu Khác vẫn bấm được sau một cú bấm, và khách đến từ
Google thì vốn đã ở TRONG bài rồi — họ không cần thanh điều hướng để tìm
ra nó.

---

## QĐ-169. `flex-shrink` vô hiệu nếu quên `min-width: 0` — lần thứ hai

Gỡ Cẩm nang chỉ giải quyết 100 trong 139px. 39px còn lại đến từ một lỗi
khác, và nó là lỗi **đã được ghi trong chính tệp CSS đó** cho một phần tử
khác (`.site-header__brand-text`).

Chuỗi lỗi:

1. `.site-header__actions` đặt `flex-shrink: 0` — "không bao giờ nhường
   chỗ". Khối phải cứng ở 556px.
2. Sửa thành `flex-shrink: 1`: khối co từ 556 xuống 487px — nhưng **các
   con bên trong vẫn cần 556px và tràn ra ngoài hộp cha 70px**. Nút "Đăng
   ký" nằm ở toạ độ 1319 trong khi khung nhìn rộng 1265.
3. Nguyên nhân: `.header-search` khai `flex: 0 1 auto` (co được) nhưng để
   nguyên `min-width: auto` — mặc định của flex item, nghĩa là **không co
   nhỏ hơn nội dung của mình**. Khai co được mà không cho phép co thì
   cũng như không.

Ba dòng phải đi cùng nhau, thiếu một là hỏng:

```css
.site-header__actions { flex-shrink: 1; min-width: 0; }
.site-header__actions > *:not(.header-search) { flex-shrink: 0; }
.header-search { flex: 0 1 auto; min-width: 0; }
.header-search__field { width: 16.25rem; max-width: 100%; }
```

Dòng thứ hai quan trọng không kém: **chỉ ô tìm kiếm được co**. Ô tìm
kiếm hẹp bớt vài chục pixel vẫn gõ được; một cái nút bị bóp méo thì không
bấm trúng, và biểu tượng giỏ hàng bị bóp thì mất vùng bấm 40px tối thiểu.

Đo sau khi sửa — tràn ngang **0px ở mọi khổ**: 1920, 1440, 1280, 1210,
1180, 768, 375. Ô tìm kiếm nhường đúng phần thiếu (260 → 190px ở 1280,
→ 120px ở 1210) rồi ẩn hẳn dưới 992px.

---

## QĐ-170. Ngăn kéo di động phải có ĐỦ mọi mục — thiếu là mục đó không tồn tại

Tìm ra khi dọn thanh điều hướng: ngăn kéo chỉ có **bốn** liên kết và
thiếu **sáu** — Chọn cây, Cẩm nang, Cây theo loài, Phụ kiện, Góc cây,
Voucher.

Dưới 1200px thanh điều hướng ẩn HẲN, nên ngăn kéo là lối đi **duy nhất**.
Thiếu một mục ở đó không phải là "khó tìm hơn": với người dùng điện
thoại, mục đó **không tồn tại**. Cẩm nang — cả một khu vực vừa dựng xong
— chưa từng có đường vào nào trên di động.

Cũng sửa một chỗ lệch tên: ngăn kéo gọi trang sản phẩm là "Sản phẩm"
trong khi thanh chính gọi là "Hoa & cây cảnh". Hai tên cho cùng một trang
làm người ta tưởng là hai chỗ khác nhau.

Mười liên kết xếp dọc thì phải đọc hết mới tìm được dòng cần, nên chia ba
cụm có tiêu đề.

`HeaderNavTest::moi_muc_dieu_huong_deu_vao_duoc_tu_dien_thoai` canh việc
này. Nó quan trọng hơn bài đếm số mục: "dọn gọn thanh điều hướng" là thao
tác sẽ còn lặp lại, và mỗi lần lặp là một cơ hội âm thầm xoá một khu vực
khỏi bản di động.

---

## QĐ-171. Hai lỗi trong chính hàm phụ trợ của bài kiểm thử

Cả hai bài mới đều ĐỎ ngay lần chạy đầu, và cả hai lần đều do hàm phụ trợ
sai chứ không phải mã sai. Ghi lại vì đây là kiểu lỗi làm người ta nghi
oan cho mã nguồn:

**1. Đếm nhầm nút "Khác" hai lần.** Nút đó là một `<summary>` mang luôn
lớp `site-header__link`, nên `substr_count(..., 'site-header__link ')` +
`substr_count(..., 'more-toggle')` cộng nó hai lần — ra 6 trong khi thanh
chỉ có 5 mục. Phải trừ số nút menu ra khỏi số liên kết trước khi cộng.

**2. Cắt chuỗi ngăn kéo ở `</div>` đầu tiên.** Bên trong ngăn kéo có các
thẻ `<div class="mobile-drawer__heading">` — chính những tiêu đề nhóm vừa
thêm — nên phép cắt dừng ngay ở tiêu đề thứ nhất và bỏ sót sáu liên kết
phía sau. Bài báo "thiếu Cẩm nang" trong khi Cẩm nang vẫn ở đó.

Mốc cắt nay là `<main` — thẻ mở ngay sau header. Chắc chắn nằm ngoài ngăn
kéo, và **không lấn sang chân trang**: chân trang cũng có link Cẩm nang,
lấy nhầm thì bài xanh một cách vô nghĩa.

Bài học chung với QĐ-167: khi một bài kiểm thử mới báo đỏ, phải xác định
lỗi nằm ở **mã** hay ở **phép đo** trước khi sửa bất cứ thứ gì. Sửa mã
theo một phép đo sai là cách hỏng thêm một chỗ đang đúng.
