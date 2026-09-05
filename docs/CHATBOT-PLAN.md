# Chatbot / NLP — dọn chỗ, chưa làm

**Trạng thái: CHƯA TRIỂN KHAI. Tài liệu này là phần chuẩn bị.**

Cố ý chưa viết một dòng mã nào cho tính năng này, và cũng cố ý **không**
đặt sẵn một khung chat rỗng lên giao diện. Một ô chat hiện ra rồi trả lời
"xin lỗi, tôi chưa hiểu" cho mọi câu hỏi thì tệ hơn hẳn việc không có ô
chat — nó tiêu mất lần thử đầu tiên của khách, và lần đó không lấy lại
được.

Việc chuẩn bị đúng nghĩa ở giai đoạn này là **rà xem dữ liệu cần thiết đã
có chưa**. Đó là nội dung dưới đây.

---

## 1. Ba việc khách sẽ hỏi, và mức sẵn sàng của từng việc

| Việc | Dữ liệu đã có? | Còn thiếu gì |
|---|---|---|
| Tư vấn chọn hoa theo dịp | ✅ gần đủ | bảng ánh xạ dịp → sản phẩm |
| Hướng dẫn chăm cây | ✅ đủ | không thiếu gì |
| Chẩn đoán bệnh cây | ❌ chưa có gì | toàn bộ cơ sở tri thức + ảnh mẫu |

### 1.1 Tư vấn chọn hoa theo dịp — gần sẵn sàng

Đã có sẵn:
- `categories` — đã có "Hoa cưới", "Hoa khai trương & sự kiện", "Hoa quà tặng"
- `products.selling_form` — bó / giỏ / hộp / lẵng
- `product_traits` — vị trí đặt, hợp mệnh
- `App\Services\Search\ProductSearch` — đã chuẩn hoá tiếng Việt, bỏ dấu, chịu lỗi gõ

**Chatbot loại này gần như chỉ là một lớp mỏng trên `ProductSearch` và
`PlantAdvisor`.** Câu "mua hoa gì tặng sinh nhật mẹ" sau khi rút ra ý định
(dịp = sinh nhật, người nhận = mẹ) thì phần còn lại là truy vấn mà hệ
thống đã làm được.

Còn thiếu: một trục **"dịp"** (sinh nhật / cưới / khai trương / chia buồn
/ 8-3 / 20-10). Hiện nó nằm lẫn trong tên danh mục chứ không phải một nhãn
riêng — xem QĐ-08 về việc tách trục. Thêm `TraitType::Occasion` là đủ, cấu
trúc đã sẵn.

### 1.2 Hướng dẫn chăm cây — dữ liệu đã đủ

`products.care_info` đã có: ánh sáng, nước tưới, đất, phân bón, nhiệt độ,
vị trí, tần suất, độ khó, ghi chú, và (từ đợt nhắc lịch) chu kỳ tưới/bón
tính bằng ngày.

Đây là cơ sở tri thức **do chính cửa hàng nhập cho từng sản phẩm**, nên
câu trả lời sẽ đúng với hàng đang bán chứ không phải kiến thức chung
chung. Đó là lợi thế lớn hơn hẳn một mô hình ngôn ngữ trả lời bằng kiến
thức internet.

Điều kiện để dùng được: admin phải **nhập đủ** `care_info`. Hiện phần lớn
sản phẩm còn để trống.

### 1.3 Chẩn đoán bệnh cây qua ảnh — chưa có gì

Không có bảng bệnh, không có ảnh mẫu, không có mô hình. Đây là hạng mục
**tốn kém nhất và rủi ro nhất**:

- Cần bộ ảnh có nhãn (vàng lá do thiếu nước / do úng / do thiếu sáng /
  do nấm) — mỗi loại vài trăm ảnh mới đủ huấn luyện.
- Chẩn đoán sai gây **thiệt hại thật**: bảo khách tưới thêm trong khi cây
  đang úng là giết cây nhanh hơn.
- Vì vậy nếu làm, bắt buộc phải trình bày dưới dạng **gợi ý kèm mức độ
  chắc chắn**, kèm câu "hãy gửi ảnh cho nhân viên xem" — không được nói
  như một kết luận.

**Đề xuất: để cuối cùng, hoặc thay bằng luồng đơn giản hơn** — khách gửi
ảnh, nhân viên thật trả lời. Cùng giá trị với khách, không có rủi ro chẩn
đoán sai, và làm được ngay bằng phần tin nhắn thường.

---

## 2. Những chỗ mã nguồn sẽ phải chạm tới

| Việc | Nơi |
|---|---|
| Tìm sản phẩm | `App\Services\Search\ProductSearch` (đã có) |
| Lọc theo nhu cầu | `App\Services\Recommendation\PlantAdvisor` (đã có) |
| Tri thức chăm cây | `products.care_info` (đã có) |
| Tra cứu đơn hàng | `App\Http\Controllers\Shop\OrderLookupController` (đã có) |
| Endpoint JSON | thêm vào nhóm `api` trong `routes/web.php` (đã có khuôn) |

**Ba trong năm phần đã tồn tại và đang chạy.** Đó là ý nghĩa thật của
"dọn chỗ": không phải viết sẵn mã chết, mà là làm cho những thứ chatbot
cần trở nên sẵn sàng như một tác dụng phụ của việc xây các tính năng khác.

---

## 3. Ba ranh giới phải giữ khi làm

1. **Không để chatbot nói về tiền và tồn kho mà không tra dữ liệu thật.**
   Giá và tình trạng hàng phải đọc từ `PricingService` và cột tồn kho,
   không được để mô hình tự sinh ra con số. Bịa giá là hứa hẹn với khách
   một mức mà cửa hàng không bán.

2. **Không để chatbot thay mặt khách thao tác** (đặt hàng, huỷ đơn, đổi
   địa chỉ) ở phiên bản đầu. Hiểu nhầm một câu là tạo ra một đơn có thật.

3. **Luôn có đường thoát sang người thật.** Câu nào bot không chắc thì
   phải chuyển sang hotline/email — đã có `Setting::get('site_hotline')`
   và `site_email`.

---

## 4. Việc cần làm trước, không phụ thuộc chatbot

Hai việc dưới đây làm chatbot khả thi hơn hẳn **và tự nó đã có ích ngay**,
nên đáng làm trước:

- **Nhập đủ `care_info` cho mọi sản phẩm.** Hiện phần lớn để trống. Có dữ
  liệu này thì trang chi tiết sản phẩm đầy đủ hơn, nhắc lịch chăm chạy
  được, và chatbot có cái để trả lời.
- **Thêm trục "dịp" (`TraitType::Occasion`).** Hiện dịp nằm lẫn trong tên
  danh mục. Tách ra thì trang tư vấn lọc được "hoa sinh nhật", và chatbot
  có sẵn một trục để ánh xạ ý định.

Cả hai đều là việc **nhập liệu và cấu trúc**, không phải việc AI — và đó
thường là phần quyết định chatbot có dùng được hay không.
