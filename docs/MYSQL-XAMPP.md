# MySQL trong XAMPP hay "mất" — nguyên nhân và cách xử lý

> Viết sau sự cố ngày 05/09/2026. Không phải lý thuyết: mọi con số dưới
> đây đo được trên chính máy này.

## Triệu chứng

XAMPP Control Panel báo:

```
Status change detected: running
Status change detected: stopped
Error: MySQL shutdown unexpectedly.
```

Bấm Start bao nhiêu lần cũng vậy.

## Đừng gỡ XAMPP cài lại

Cách hay được mách trên mạng là xoá thư mục `data`, chép `backup` đè vào,
hoặc cài lại XAMPP. **Cả ba đều làm mất dữ liệu, và cả ba đều chữa nhầm
bệnh.** Bảng bị hỏng gần như luôn là bảng *phân quyền hệ thống*, không
phải database của bạn.

## Bước 1 — Hỏi cho ra lý do thật

Control Panel không nói vì sao. Chạy thẳng máy chủ ở chế độ in ra màn
hình:

```
D:\xampp\mysql\bin\mysqld.exe --defaults-file=D:\xampp\mysql\bin\my.ini --console
```

Lần này nó nói thẳng:

```
[ERROR] Table '.\mysql\db' is marked as crashed and last (automatic?) repair failed
[ERROR] Fatal error: Can't open and lock privilege tables
[ERROR] Aborting
```

Cũng xem `D:\xampp\mysql\data\mysql_error.log`.

## Bước 2 — Hiểu vì sao dữ liệu vẫn an toàn

| | Định dạng | Khi bị giết ngang |
|---|---|---|
| `btlar`, `cafe_management`, ... | **InnoDB** | tự phục hồi được (crash recovery) |
| `mysql.db`, `mysql.tables_priv`, ... | **Aria** | **không** — bị đánh dấu "crashed" |

Máy chủ không mở được bảng phân quyền thì nó không khởi động, dù toàn bộ
dữ liệu thật vẫn nguyên vẹn trong `ibdata1`. Đó là lý do bệnh trông nặng
hơn thực tế rất nhiều.

## Bước 3 — Sửa

Chạy `D:\xampp-tools\sua-bang-he-thong.bat`, hoặc làm tay:

```
REM MySQL phải TẮT trước
cd /d D:\xampp\mysql\data\mysql
D:\xampp\mysql\bin\aria_chk.exe --datadir=D:\xampp\mysql\data -r *.MAI
D:\xampp\mysql\bin\aria_chk.exe --datadir=D:\xampp\mysql\data -o *.MAI
D:\xampp\mysql\bin\aria_chk.exe --datadir=D:\xampp\mysql\data -c *.MAI
```

**Vì sao chạy cả `-r` rồi `-o`:** `-r` nhanh nhưng dùng bộ đệm sắp xếp, và
MariaDB 10.4 trên Windows báo `aria_sort_buffer_size is too small` kể cả
khi đã truyền `--sort_buffer_size=256M` (đã thử, tham số bị bỏ qua). `-o`
chậm hơn nhưng không dùng bộ đệm đó nên luôn xong việc. Lần này `-r` sửa
được 13/16 bảng, `-o` dọn nốt 3 bảng còn lại.

Kết quả đo được sau khi sửa: 43 sản phẩm, 10 người dùng, 42 đơn hàng,
39 đánh giá — **không mất dòng nào**.

## Bước 4 — Chữa nguyên nhân, không chỉ chữa triệu chứng

Hai số đo trên máy này:

```
Số lần MySQL khởi động ............................ 17
Số lần MySQL tắt bình thường ......................  0
Fast Startup của Windows (HiberbootEnabled) ....... 1 (đang bật)
```

**Chưa một lần nào** MySQL được tắt sạch. Mỗi lần đều bị giết ngang — đóng
Control Panel, tắt máy, hoặc máy sập. Bảng Aria không chịu được điều đó,
và nó tích luỹ cho tới lúc hỏng hẳn.

Ba việc, theo thứ tự quan trọng:

1. **Luôn bấm Stop cho MySQL trong Control Panel trước khi đóng nó hoặc
   tắt máy.** Đây là việc chữa được 90% vấn đề và không tốn gì.

2. **Tắt Fast Startup của Windows.** Nó không tắt máy thật mà ngủ đông
   nhân hệ điều hành, nên trạng thái tệp không phải lúc nào cũng được ghi
   xong. Chạy PowerShell **với quyền quản trị**:

   ```
   powercfg /hibernate off
   ```

3. **Loại thư mục XAMPP khỏi Windows Defender.** Phần mềm diệt virus quét
   giữa lúc MySQL đang ghi là một nguyên nhân phổ biến khác. PowerShell
   **với quyền quản trị**:

   ```
   Add-MpPreference -ExclusionPath "D:\xampp"
   ```

Và mở Control Panel bằng **Run as administrator** — nếu không, chính nó
cảnh báo là các thao tác với dịch vụ "will break".

## Bước 5 — Để lần sau không còn đáng sợ

`D:\xampp-tools\sao-luu-db.bat` — chạy trước mỗi buổi làm việc. Nó xuất
một tệp `.sql` vào `D:\xampp-backup-sql\` và tự giữ 20 bản gần nhất.

**Vì sao là `.sql` chứ không phải chép thư mục `data`:** tệp `.sql` chạy
được ở bất kỳ máy nào có MySQL, không phụ thuộc phiên bản InnoDB hay
thư mục data của cài đặt này. Chép `data` mà thiếu `ibdata1` là mất sạch.

Phục hồi:

```
D:\xampp\mysql\bin\mysql.exe -u root < "D:\xampp-backup-sql\btlar-<ngày>.sql"
```

Đã **thử phục hồi thật** vào một database riêng để kiểm chứng, không chỉ
tạo ra tệp rồi tin là nó chạy được: 32 bảng, đúng 43/10/42/39 dòng như
bản gốc.
