# Rà soát: chỗ nào trong khu quản trị tự động được

> Viết ngày 05/09/2026, sau khi gỡ hình thức thanh toán chuyển khoản.
> Yêu cầu của môn học: những chức năng đáng lẽ chạy tự động thì không
> được để người bấm tay.

## Câu hỏi phân loại

Không phải cứ thủ công là sai. Câu hỏi đúng là:

> **Hệ thống có nguồn dữ liệu nào để tự trả lời câu hỏi này không?**

- **Có** → để người bấm tay là thiếu sót, phải tự động.
- **Không** → bắt buộc có người, và đó là thiết kế đúng.
- **Có, nhưng việc đó là một quyết định** (về tiền, về quyền, về uy tín)
  → giữ người, nhưng máy phải chuẩn bị sẵn thông tin.

## Kết quả rà soát

| Việc trong khu quản trị | Trước | Nguồn dữ liệu để tự trả lời | Kết luận |
|---|---|---|---|
| Xác nhận khách đã trả tiền COD | admin bấm tay | **GHN** — chính là shipper, có API báo `delivered` | **ĐÃ TỰ ĐỘNG** |
| `Đang giao` → `Đã giao` | admin bấm tay | GHN | **ĐÃ TỰ ĐỘNG** |
| `orders.shipping_status` | ghi 1 lần rồi đứng yên mãi | GHN | **ĐÃ TỰ ĐỘNG** (đây là lỗi, không phải thiếu tính năng) |
| Khuyến mại hết hạn → `Đã kết thúc` | admin đổi tay | `ends_at` nằm sẵn trong bảng | **ĐÃ TỰ ĐỘNG** |
| Khuyến mại tới ngày → `Đang diễn ra` | admin đổi tay | `starts_at` | **ĐÃ TỰ ĐỘNG** |
| Tạo vận đơn GHN | admin bấm tay | — cần cân hàng thật, chọn ca lấy hàng | **GIỮ NGƯỜI** (QĐ-93) |
| Huỷ đơn khi GHN báo hoàn/mất hàng | không có gì | GHN báo, nhưng huỷ kéo theo hoàn kho + hoàn tiền | **GIỮ NGƯỜI** |
| Ẩn/hiện đánh giá | admin bấm tay | — không có cách nào máy đọc được "bài này có xúc phạm không" | **GIỮ NGƯỜI** |
| Trả lời đánh giá | admin viết | — | **GIỮ NGƯỜI** |
| Khoá tài khoản, đổi vai trò | admin bấm tay | — quyền lực phải có người chịu trách nhiệm | **GIỮ NGƯỜI** |
| Hoàn tiền | admin bấm tay | — phần mềm không nhìn thấy tiền ra khỏi tài khoản | **GIỮ NGƯỜI** |
| Đơn `Chờ xác nhận` treo quá lâu | không có gì | `created_at` | **ĐỀ XUẤT**, chưa làm |

## Đã làm

### 1. Đồng bộ vận đơn GHN — và đây là cách COD tự động

```
php artisan ghn:dong-bo          # 30 phút/lần
```

`app/Services/Shipping/GhnStatusSync.php`

Điểm mấu chốt của toàn bộ phần này: **giáo viên không cho admin tự kiểm
rồi bấm xác nhận, nhưng COD thì đúng là phải có shipper xác nhận.** Hai
điều đó không mâu thuẫn — GHN *chính là* shipper, và GHN có API. Hỏi GHN
là shipper xác nhận, chỉ khác ở chỗ máy hỏi thay vì người hỏi.

GHN báo `delivered` → hàng đã tới tay khách và tiền COD đã thu → đơn tự
chuyển sang `Đã giao` và `Đã thanh toán`.

**Ba luật không được phá:**

1. **Chỉ `delivered` mới được ghi là đã thu tiền.** `delivering` (shipper
   đang trên đường) thì chưa — khách vẫn có quyền từ chối nhận tại cửa.
   Ghi sớm là cửa hàng ghi vào sổ một khoản không tồn tại và không bao
   giờ đi đòi. Có một bài kiểm thử riêng cho đúng chuyện này.
2. **Không tự huỷ đơn.** GHN báo `returned` / `lost` thì chỉ ghi lại.
3. **Đi qua `OrderService`, không `update()` thẳng.** Đổi trạng thái còn
   kéo theo gửi thư cho khách và ghi mốc thời gian.

Nút bấm tay ở trang quản trị **vẫn còn** — cần cho đơn không đi qua GHN
(khách tự tới lấy, giao nội bộ) và cho lúc GHN sập. Tự động là đường
chính, bấm tay là đường lùi.

### 2. Nhãn trạng thái khuyến mại

```
php artisan khuyen-mai:cap-nhat-trang-thai      # mỗi giờ
```

Đây **không** phải chuyện về giá: `Promotion::scopeActiveNow()` vốn đã
lọc theo ngày nên giá bán luôn đúng. Sai là ở cái nhãn — đo trên dữ liệu
thật lúc viết: **1 chương trình đã qua ngày kết thúc mà trang quản trị
vẫn hiện "Đang diễn ra"**.

Chỉ động vào hai chiều suy ra được từ ngày tháng. **Không đụng vào `Nháp`
và `Tạm dừng`** — đó là ý định của con người, và máy bật lại thứ người
vừa tắt là máy huỷ một quyết định về giá.

## Hai nguyên tắc rút ra

**1. Việc máy làm cũng phải có trong nhật ký.**

Nhật ký quản trị trước đây chỉ ghi việc do người bấm. Khi máy bắt đầu tự
đổi trạng thái, admin mở lên thấy đơn đã thanh toán mà không có dòng nào
giải thích — và câu hỏi đầu tiên sẽ là *"ai làm cái này?"*. Tự động hoá
không có nhật ký thì trông y hệt lỗi.

**2. Tự động hoá im lặng khi hỏng là tệ hơn không tự động.**

`GHNService::post()` không ném lỗi — nó trả về mảng có `code` khác 200.
Bản đầu của `syncOne()` chỉ đọc `data.status`, không thấy thì trả về
`false`. Nghĩa là GHN sập cả buổi mà lệnh vẫn báo *"đã hỏi 40 vận đơn, 0
lỗi"*. Bài kiểm thử bắt được đúng chỗ này. Nay lượt hỏng được đếm, ghi
log kèm mã đơn, và lệnh thoát với mã lỗi cho cron nhìn thấy.

## ⚠️ Phải có tiến trình chạy nền

Khai lịch trong `routes/console.php` **không** làm lệnh tự chạy:

```bash
php artisan schedule:work
```

Trên máy chủ thật thì đặt vào cron:

```
* * * * * php artisan schedule:run
```

Không có nó thì mọi thứ ở trên vẫn phải bấm tay — đúng cái bẫy đã gặp
với hàng đợi email.

## Đề xuất, chưa làm

**Đơn `Chờ xác nhận` treo quá lâu.** Hiện không có gì dọn (hôm nay chưa
có đơn nào quá 7 ngày nên chưa gấp). Tự huỷ là một quyết định về tiền —
nên hướng đúng có lẽ là cảnh báo trên bảng điều khiển trước, tự huỷ sau,
và chỉ khi có luật rõ ràng về số ngày.
