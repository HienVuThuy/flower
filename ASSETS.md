# Nguồn gốc & giấy phép tài nguyên

Mọi ảnh/hình trong dự án đều được **tải về lưu cục bộ**, không hotlink từ
website khác. File này ghi lại nguồn và giấy phép của từng thứ — bắt buộc
với các giấy phép yêu cầu ghi công (CC BY).

---

## 1. Artwork theme mùa vụ (`resources/images/themes/`)

Các file SVG này **không vẽ tay** mà được ghép từ hình gốc của bộ
**Game-icons.net**, rồi tô lại màu theo token của từng theme.

- **Nguồn:** https://game-icons.net — https://github.com/game-icons/icons
- **Giấy phép:** CC BY 3.0 — https://creativecommons.org/licenses/by/3.0/
- **Yêu cầu:** phải ghi tên tác giả. Danh sách bên dưới.

| Hình gốc | Tác giả | Dùng trong |
|---|---|---|
| `spoted-flower` | Lorc | hoa mai (Tết), hoa đào |
| `three-leaves` | Lorc | lá trên cành mai/đào |
| `asian-lantern` | Delapouite | đèn lồng Tết |
| `firework-rocket` | Lorc | pháo hoa Tết |
| `two-coins` | Delapouite | tiền vàng Tết |
| `tied-scroll` | Lorc | câu đối Tết |
| `present` | Delapouite | hộp quà (cả 3 theme) |
| `pine-tree` | Lorc | hàng thông Noel |
| `snowflake-2` | Lorc | bông tuyết Noel |
| `snowman` | Delapouite | người tuyết |
| `deer` | Delapouite | tuần lộc |
| `socks` | Delapouite | tất Giáng sinh |
| `candy-canes` | Delapouite | kẹo gậy |
| `gingerbread-man` | Delapouite | người bánh gừng |
| `ringing-bell` | Lorc | chuông Giáng sinh |
| `shut-rose` | Lorc | hoa hồng Valentine |
| `bow-tie-ribbon` | Delapouite | nơ Valentine |
| `chocolate-bar` | Delapouite | thanh chocolate |
| `love-letter` | Lorc | thư tình |
| `ring-box` | Delapouite | hộp nhẫn |
| `bear-face` | Delapouite | gấu bông |
| `cupidon-arrow` | Lorc | mũi tên thần tình yêu |
| `shining-heart` | Lorc | trái tim Valentine |

Phần **không** lấy từ game-icons — tự vẽ vì chỉ gồm hình học đơn giản:

- `banhChung()` — bánh chưng (hình vuông + lạt buộc)
- `banhGiay()` — bánh giày (đĩa tròn + lá chuối)
- `liXi()` — phong bao lì xì
- `snowDrift()` — **tuyết đọng** trên nút, sinh bằng thuật toán với PRNG
  hạt giống cố định nên gò tuyết cao thấp ngẫu nhiên mà build lại vẫn ra
  đúng một hình. Dựng bằng hợp của các hình tròn chồng nhau, và được
  **lát lặp** (`repeat-x`) chứ không kéo giãn — kéo giãn theo bề rộng
  nút là nguyên nhân làm mặt tuyết bẹt.
- nét cành mai/đào, cành treo và các quả châu, dây treo đèn lồng

### Dựng lại artwork

```bash
npm run build:artwork
```

Script `tools/build-theme-artwork.mjs` đọc gói `@iconify-json/game-icons`
rồi ghi đè các file `.svg` ở trên. Chỉ chạy khi muốn đổi bố cục hoặc màu —
website chạy bằng file `.svg` đã sinh sẵn, nên deploy không cần chạy.

Xem thử trước khi build:

```bash
npm run preview:artwork
```

---

## 2. Icon giao diện (`resources/images/icons/`)

- **Nguồn:** Bootstrap Icons — https://icons.getbootstrap.com
- **Giấy phép:** MIT
- Được gộp thành một SVG sprite thay cho icon font, nên chỉ nạp đúng số
  icon thực dùng.
- Bổ sung cho phần Đánh giá & Yêu thích: `star`, `star-fill`, `star-half`,
  `heart`, `heart-fill`, `trash` — lấy nguyên đường vẽ từ
  `node_modules/bootstrap-icons/icons/`, không vẽ tay.

---

## 3. Ảnh danh mục / nhu cầu / sản phẩm (`resources/images/catalog/`)

- **Nguồn:** Openverse — https://openverse.org (gom ảnh Creative Commons
  từ Flickr và nhiều kho khác)
- **Tải lại bằng:** `npm run fetch:photos`
- **Xử lý:** cắt về 900×640 và nén JPEG chất lượng 76 — tổng dung lượng
  giảm từ khoảng 2.4 MB xuống dưới 900 KB.

> **CC BY / CC BY-SA bắt buộc ghi tên tác giả.** Bảng dưới là phần ghi
> công đó — không được xoá. Nếu thay ảnh khác thì phải sửa bảng này theo.

| File | Dùng cho | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `cat-bonsai.jpg` | Cây bonsai | Drew Avery | BY 2.0 | [Japanese Cork bark Black P](https://www.flickr.com/photos/33590535@N06/5622489648) |
| `cat-cay-canh.jpg` | Cây cảnh | greckor | BY 2.0 | [Balcony Plants](https://www.flickr.com/photos/12531571@N00/3684451437) |
| `cat-cay-de-ban.jpg` | Cây để bàn | srboisvert | BY 2.0 | [echeveria ramillette](https://www.flickr.com/photos/35034346289@N01/5560515385) |
| `cat-hoa.jpg` | Hoa | Muffet | BY 2.0 | [birthday bouquet](https://www.flickr.com/photos/53133240@N00/5526964611) |
| `intent-nguoi-moi.jpg` | Người mới trồng cây | Chris Hunkeler | BY-SA 2.0 | [Vertical Succulent Wall](https://www.flickr.com/photos/14913305@N00/8850172234) |
| `intent-su-kien.jpg` | Khai trương & sự kiện | callocx | BY 2.0 | [Wedding Table Decorations](https://www.flickr.com/photos/61400543@N04/6005359137) |
| `intent-tang-nguoi-thuong.jpg` | Tặng người thương | Images by Petra | BY 2.0 | [Bouquet](https://www.flickr.com/photos/78541979@N05/15143632852) |
| `intent-trang-tri.jpg` | Trang trí không gian sống | faith goble | BY 2.0 | [The Summer Window](https://www.flickr.com/photos/24420613@N08/4878334266) |
| `product-monstera.jpg` | Monstera Deliciosa | blumenbiene | BY 2.0 | [Fensterblatt (Monstera del](https://www.flickr.com/photos/47439717@N05/4527903734) |

### Ảnh này đi đâu trong hệ thống

| Loại | Cách lưu | Admin đổi được không |
|---|---|---|
| Danh mục (`cat-*.jpg`) | Chép vào `storage/app/public/categories/`, ghi đường dẫn vào cột `categories.image` | Có — qua form sửa danh mục |
| Nhu cầu (`intent-*.jpg`) | Tài nguyên giao diện, gọi bằng `Vite::asset()` | Không — phải sửa code |
| Sản phẩm (`product-*.jpg`) | Chép vào `storage/app/public/products/`, ghi vào `products.main_image` | Có — qua form sửa sản phẩm |

> **Lưu ý về ảnh sản phẩm:** `product-monstera.jpg` chỉ là ảnh TẠM cho
> sản phẩm chưa có ảnh. Cửa hàng thật phải chụp đúng sản phẩm mình bán —
> admin nên thay bằng ảnh thật khi có.
>
> Sản phẩm đã có ảnh do admin tải lên thì script KHÔNG ghi đè.

---

## 4. Ảnh nền không khí theo theme (`resources/images/themes/*/bg.jpg`)

Ảnh phủ toàn trang phía sau nội dung, hiển thị ở độ mờ 16–20%. Ảnh giữ
**nguyên độ nét** — chỉ trong suốt, không làm mờ. (Bản trước có làm mờ
sẵn bằng `blur(9)` nhưng kết quả nhìn như một tấm màng đục phủ lên
trang, nên đã bỏ.)

Chỉ tải khi theme tương ứng đang bật, vì quy tắc nằm trong
`[data-theme="..."]`.

| File | Dùng cho | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `themes/tet/bg.jpg` | Theme tet | Dileep Kaluaratchie | BY-SA 4.0 | [Chinese New Year Lanterns ](https://commons.wikimedia.org/w/index.php?curid=87138117) |
| `themes/noel/bg.jpg` | Theme noel | Patrick Gillespie | BY 2.0 | [Presents under the tree on](https://www.flickr.com/photos/40423570@N07/16114981585) |
| `themes/valentine/bg.jpg` | Theme valentine | jessicahtam | BY 2.0 | [Hearts](https://www.flickr.com/photos/42305326@N05/4367986259) |

---

## 5. Ảnh hero luân phiên theo theme (`resources/images/hero/`)

Khung ảnh lớn ở đầu trang chủ tự đổi ảnh sau mỗi 6 giây. **Mỗi theme có
bộ ảnh riêng**, khai trong `config/theme.php` — thêm theme mới chỉ cần
thêm ảnh và một mục config, không chỗ nào trong code rẽ nhánh theo tên
theme. Trang chỉ render bộ của theme đang bật nên các bộ khác không tốn
byte nào.

Ảnh cắt về 900×1125 (tỉ lệ 4:5 của khung), tổng ~1.1 MB cho 12 ảnh;
ảnh đầu mỗi bộ tải ngay, hai ảnh sau `loading="lazy"`.

| File | Theme | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `hero/default-1.jpg` | default | ankakay | BY 2.0 | [picture this bouquet...](https://www.flickr.com/photos/56738296@N00/5446059891) |
| `hero/default-2.jpg` | default | Nullumayulife | BY 2.0 | [Japanese flower arrangem](https://www.flickr.com/photos/72859063@N00/465912298) |
| `hero/default-3.jpg` | default | Yam B Chhetri | CC0 1.0 | [A close-up of a green pl](https://wordpress.org/photos/photo/12867b0263/) |
| `hero/tet-1.jpg` | tet | SteFou! | BY 2.0 | [Sakura](https://www.flickr.com/photos/41614647@N04/6081706596) |
| `hero/tet-2.jpg` | tet | 2benny | BY 2.0 | [Spring in Stockholm](https://www.flickr.com/photos/61845874@N07/7124906807) |
| `hero/tet-3.jpg` | tet | batintherain | BY-SA 2.0 | [stasera esco](https://www.flickr.com/photos/82995349@N00/3381568253) |
| `hero/noel-1.jpg` | noel | SusanReimer | BY 2.0 | [Baltimore Conservatory P](https://www.flickr.com/photos/42579661@N06/4179036921) |
| `hero/noel-2.jpg` | noel | SusanReimer | BY 2.0 | [Baltimore Conservatory P](https://www.flickr.com/photos/42579661@N06/4179797730) |
| `hero/noel-3.jpg` | noel | watts_photos | BY 2.0 | [Christmas door wreath - ](https://www.flickr.com/photos/126288307@N05/52573265110) |
| `hero/valentine-1.jpg` | valentine | It's No Game | BY 2.0 | [Red Rose #1](https://www.flickr.com/photos/29057345@N04/8479070428) |
| `hero/valentine-2.jpg` | valentine | moonlightbulb | BY 2.0 | [Red rose after rain](https://www.flickr.com/photos/24532534@N02/2485859239) |
| `hero/valentine-3.jpg` | valentine | jenny downing | BY 2.0 | [twilight roses](https://www.flickr.com/photos/7941044@N06/3079485197) |

Vòng luân phiên dừng khi tab bị ẩn và **không chạy** nếu người dùng bật
`prefers-reduced-motion`.

---

## 6. Ảnh biên tập (`resources/images/editorial/`)

| File | Nguồn | Giấy phép |
|---|---|---|
| `story-studio.jpg` | Unsplash | Unsplash License — dùng thương mại được, không bắt buộc ghi công |

Unsplash License: https://unsplash.com/license

> Đây là ảnh **minh hoạ giao diện**, không phải ảnh sản phẩm thật. Ảnh
> sản phẩm do admin tự tải lên và lưu ở `storage/app/public/products`.

---

## 7. Nhận diện thương hiệu (`resources/images/branding/`)

`mark.svg` — tự vẽ cho dự án, không dùng lại logo của bên nào khác.

---

## 8. Phông chữ (`resources/fonts/`)

| Phông | Giấy phép |
|---|---|
| Inter (400/500/600/700) | SIL Open Font License 1.1 |
| Playfair Display (600, thường + nghiêng) | SIL Open Font License 1.1 |

Tự host dưới dạng `.woff2`, có cắt subset kèm `unicode-range` cho khối
tiếng Việt — không gọi sang Google Fonts.

---

## Quy tắc khi thêm tài nguyên mới

1. Kiểm tra giấy phép **trước** khi dùng; không dùng thứ không rõ nguồn.
2. Tải về trong repo, tuyệt đối không hotlink.
3. Bổ sung một dòng vào file này: file nào, lấy ở đâu, giấy phép gì,
   có phải ghi công không.
4. Không dùng emoji làm icon, logo hay ảnh đại diện sản phẩm.

---

## Ảnh sản phẩm (`storage/app/public/products/`)

- **Nguồn:** Openverse — https://openverse.org
- **Tải lại bằng:** `node tools/fetch-product-photos.mjs` rồi `php artisan products:link-photos`
- **Vì sao nằm trong `storage/` chứ không phải `resources/`:** đây là DỮ LIỆU của
  cửa hàng (`products.main_image` trỏ tới), admin thay được từ trang quản trị.
  Ảnh trong `resources/` là tài sản giao diện, phải build lại mới đổi được.
- Bản ghi nguồn dạng máy đọc: `storage/app/public/products/credits.json`.

> **CC BY / CC BY-SA bắt buộc ghi tên tác giả.** Hai bảng dưới là phần ghi
> công đó. Chúng do `php artisan assets:sync-credits` DỰNG RA từ
> `credits.json` — sửa tay sẽ bị ghi đè, và chép tay thì lần tải ảnh sau
> bảng lệch ngay, tức là thiếu ghi công. Muốn kiểm tra mà không ghi:
> `php artisan assets:sync-credits --check`.

### ⚠️ 19 sản phẩm hiện CHƯA CÓ ẢNH — và đó là chủ ý

**Tám ảnh đã bị GỠ vì sai chủ đề.** Chúng lọt vào vì bộ lọc tiêu đề
trong `tools/fetch-product-photos.mjs` dùng danh sách từ HOẶC quá lỏng —
xem phần "Vì sao ảnh sai lọt được vào" bên dưới:

| Sản phẩm | Ảnh nhận nhầm |
|---|---|
| Bình tưới vòi dài 1.5L | máy xúc và xe ben dọn đường |
| Dung dịch dưỡng hoa tươi | tượng Phật mạ vàng |
| Kệ hoa khai trương hai tầng | bàn tay cầm một bông cỏ dại |
| Giỏ hoa baby trắng | tranh sơn dầu tĩnh vật thế kỷ 19 |
| Bó cúc hoạ mi trắng | một bông đồng tiền **đỏ** |
| Bonsai tùng la hán dáng trực | bonsai **Lantana** — sai loài |
| Phân bón NPK dạng viên | một củ hoa |
| Đĩa lót chậu chống tràn | tách trà dùng làm chậu |

**Mười một món còn lại là hàng mới** (nhóm *Phủ gốc & tiểu cảnh* và *Phụ
kiện trang trí*) cùng "Viên đất nung" chưa bao giờ tải được ảnh.

**Vì sao chưa tải lại được:** API của Openverse đang trả `504 Gateway
Timeout` cho mọi truy vấn (thử lại nhiều lần, cùng kết quả). Đây là sự cố
phía họ.

**Khi Openverse hoạt động lại, chạy đúng hai lệnh:**

```
node tools/fetch-product-photos.mjs
php artisan products:link-photos
php artisan assets:sync-credits
```

Thẻ sản phẩm không có ảnh vẫn hiện hình lá giữ chỗ. **Ảnh sai chủ đề thì
tệ hơn không có ảnh** — đó là lý do gỡ chứ không để nguyên chờ.

### Vì sao ảnh sai lọt được vào

Bộ lọc `must` là một biểu thức chính quy khớp với TIÊU ĐỀ ảnh. Lỗi nằm ở
chỗ dùng danh sách HOẶC gồm những từ quá thường gặp:

| Bộ lọc cũ | Tiêu đề lọt qua |
|---|---|
| `/water\|can/i` | "Crews work to clean up debris so **water** **can** flow" |
| `/flower\|vase/i` | "...statue, holding a mala, **flower**, **vase** of nectar..." |
| `/...\|flower\|.../i` | "Walwhalleya proluta **flower**head10" |
| `/fertili\|.../i` | "**Fertili**sed Bulb" |
| `/saucer\|tray\|pot\|dish/i` | "Teacup Plant **Pot**" |

Đã siết thành CỤM TỪ hoàn chỉnh (`/watering[- ]?can/i`) và thêm tuỳ chọn
`block` để cấm riêng theo từng món — ví dụ ảnh Pachira aquatica đúng loài
nhưng chụp **hoa**, trong khi sản phẩm là cây bện thân.

<!-- credits:products -->
| Tệp | Sản phẩm | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `bao-li-xi-mini-treo-cay-ngay-tet.jpg` | bao-li-xi-mini-treo-cay-ngay-tet | quinn.anya | BY-SA 2.0 | [Day 34: Lunar New Year](https://www.flickr.com/photos/53326337@N00/12298155173) |
| `binh-thuy-tinh-cam-hoa.jpg` | binh-thuy-tinh-cam-hoa | Ron Meck | BY 2.0 | [Glass Flower Vases](https://www.flickr.com/photos/115284274@N07/14498230476) |
| `binh-tuoi-voi-dai-15l.jpg` | binh-tuoi-voi-dai-15l | chimpwithcan | BY 2.0 | [Watering Can Garden Gardening Water Edited 2020](https://www.flickr.com/photos/188454520@N02/49905865953) |
| `bo-6-qua-cau-giang-sinh-treo-cay.jpg` | bo-6-qua-cau-giang-sinh-treo-cay | Michael Fötsch | BY-SA 2.0 | [Ornaments](https://www.flickr.com/photos/23557463@N05/6566261945) |
| `bo-cam-tu-cau-xanh.jpg` | bo-cam-tu-cau-xanh | Scott 97006 | BY 2.0 | [Blue Hydrangea Flower Cluster](https://www.flickr.com/photos/29487672@N07/14584149951) |
| `bo-cuc-hoa-mi-trang.jpg` | bo-cuc-hoa-mi-trang | Eric Kilby | BY-SA 2.0 | [Sea of Daisies](https://www.flickr.com/photos/8749778@N06/3749713017) |
| `bo-hoa-mau-don-do.jpg` | bo-hoa-mau-don-do | Muffet | BY 2.0 | [gathering of peonies](https://www.flickr.com/photos/53133240@N00/3674711901) |
| `bo-tulip-ha-lan.jpg` | bo-tulip-ha-lan | Muffet | BY 2.0 | [birthday bouquet](https://www.flickr.com/photos/53133240@N00/13085174494) |
| `bonsai-mai-chieu-thuy.jpg` | bonsai-mai-chieu-thuy | Daniel Gasteiger | BY 2.0 | [Another Bonsai at the 2011 Philadelphia Flower Show](https://www.flickr.com/photos/30014417@N04/5524991164) |
| `bonsai-tung-la-han-dang-truc.jpg` | bonsai-tung-la-han-dang-truc | MeganEHansen | BY-SA 2.0 | [podocarpus macrophyllus](https://www.flickr.com/photos/24495410@N03/4850131773) |
| `canh-dao-phai-choi-tet.jpg` | canh-dao-phai-choi-tet | jenny downing | BY 2.0 | [floral](https://www.flickr.com/photos/7941044@N06/4529073298) |
| `cay-lan-y-chau-su-trang.jpg` | cay-lan-y-chau-su-trang | daBinsi | BY 2.0 | [Spathiphyllum 'Peace Lily'](https://www.flickr.com/photos/13741829@N07/3328727610) |
| `cay-luoi-ho-vang-vien-de-ban.jpg` | cay-luoi-ho-vang-vien-de-ban | Jungle Garden | BY 2.0 | [Sansevieria trifasciata var. laurentii](https://www.flickr.com/photos/63405895@N07/24414209594) |
| `chau-su-trang-co-vua.jpg` | chau-su-trang-co-vua | john bonham2 | BY-SA 2.0 | [Flower pots parrots](https://www.flickr.com/photos/95205391@N05/9063838073) |
| `co-nhung-nhat-mini-phu-goc.jpg` | co-nhung-nhat-mini-phu-goc | A.Davey | BY 2.0 | [Skunk Cabbage in Forest Moss](https://www.flickr.com/photos/40595948@N00/3053902482) |
| `da-trang-phu-mat-chau-1kg.jpg` | da-trang-phu-mat-chau-1kg | Bold Frontiers | BY 2.0 | [Colorful Stones](https://www.flickr.com/photos/82955120@N05/7995282466) |
| `dat-trong-tron-san-5kg.jpg` | dat-trong-tron-san-5kg | Alex Cheek | BY-SA 2.0 | [These bulbs are breaking through the compacted potting soil, leaving cracks and causing general but small-scale tectonic upheaval in the flowerpots near school.](https://www.flickr.com/photos/76903355@N00/444011608) |
| `day-den-led-mini-quan-cay.jpg` | day-den-led-mini-quan-cay | Dominic's pics | BY 2.0 | [Fairy Lights](https://www.flickr.com/photos/64097751@N00/1128635213) |
| `dia-lot-chau-chong-tran.jpg` | dia-lot-chau-chong-tran | ambabheg | BY 2.0 | [2022 (365 challenge) - Week 41 (fragile) - Day 5- broken clay plant saucer](https://www.flickr.com/photos/31518985@N04/52422107087) |
| `dung-dich-duong-hoa-tuoi.jpg` | dung-dich-duong-hoa-tuoi | ProFlowers.com | BY 2.0 | [woman in white dress watering giant five foot tall red roses from a glass pitcher in a tall glass vase](https://www.flickr.com/photos/127365614@N08/15814839114) |
| `duong-xi-boston-treo.jpg` | duong-xi-boston-treo | Starr Environmental | BY 2.0 | [starr-100623-7772-Nephrolepis_sp-potted_plants_in_shade_house-Pukalani_Plant_Company_Pulehu-Maui](https://www.flickr.com/photos/97499887@N06/24949015421) |
| `gio-hoa-baby-trang.jpg` | gio-hoa-baby-trang | Anne Worner | BY-SA 2.0 | [Gypsophila (Baby's-Breath)](https://www.flickr.com/photos/28652129@N06/5462827678) |
| `gio-hoa-huong-duong-mini.jpg` | gio-hoa-huong-duong-mini | tracydekalb | BY 2.0 | [Basket of sunshine](https://www.flickr.com/photos/11540627@N03/4810587301) |
| `hoa-cai-ao-chu-re.jpg` | hoa-cai-ao-chu-re | hortulus | BY 2.0 | [back from the wedding . . .](https://www.flickr.com/photos/15845498@N00/3789340877) |
| `hoa-cam-tay-co-dau.jpg` | hoa-cam-tay-co-dau | Sailor Coruscant | BY 2.0 | [My bouquet...](https://www.flickr.com/photos/30325243@N00/2949945463) |
| `hoa-de-ban-tiec-cuoi.jpg` | hoa-de-ban-tiec-cuoi | Tracy Hunter | BY 2.0 | [Centerpieces](https://www.flickr.com/photos/11121785@N00/164578909) |
| `hop-hoa-hong-pastel.jpg` | hop-hoa-hong-pastel | Sheba_Also 48,000 photos incl private | BY-SA 2.0 | [Rose Rose I Love You-1=](https://www.flickr.com/photos/34534185@N00/8148974444) |
| `hop-hoa-tulip-vang.jpg` | hop-hoa-tulip-vang | Kirt Edblom | BY-SA 2.0 | [Crayon Box of Flowers](https://www.flickr.com/photos/27190564@N02/16732302779) |
| `huong-duong-ruc-ro.jpg` | huong-duong-ruc-ro | kinglear55 | BY 2.0 | [Sunflower Bouquet](https://www.flickr.com/photos/65469424@N05/50366454337) |
| `ke-hoa-khai-truong-hai-tang.jpg` | ke-hoa-khai-truong-hai-tang | Muffet | BY 2.0 | [floral arrangement](https://www.flickr.com/photos/53133240@N00/15808096543) |
| `keo-cat-canh-mui-cong.jpg` | keo-cat-canh-mui-cong | el cajon yacht club | BY 2.0 | [THOR's hammer, garden trowel, weed digger and pruning shears](https://www.flickr.com/photos/60944636@N00/30173721920) |
| `kim-ngan-ben-than.jpg` | kim-ngan-ben-than | wallygrom | BY-SA 2.0 | [Pachira aquatica](https://www.flickr.com/photos/33037982@N04/8432823742) |
| `kim-tien-chau-su.jpg` | kim-tien-chau-su | wlcutler | BY-SA 2.0 | [Zamioculcas-ZZ-plant_Cutler_20160617_P1260075](https://www.flickr.com/photos/20664893@N00/46385996094) |
| `lan-ho-diep-tim-chau-su.jpg` | lan-ho-diep-tim-chau-su | HenryLeongHimWoh | BY-SA 2.0 | [Purple Orchid,Singapore Botanical Garden](https://www.flickr.com/photos/19517908@N00/4698040551) |
| `lang-hoa-khai-truong.jpg` | lang-hoa-khai-truong | Nullumayulife | BY 2.0 | [Japanese flower arrangement 7, Ikebana: いけばな](https://www.flickr.com/photos/72859063@N00/2624390834) |
| `luoi-ho-mini-de-ban.jpg` | luoi-ho-mini-de-ban | el cajon yacht club | BY 2.0 | [instax-Sansevieria-snake-plant-180704a](https://www.flickr.com/photos/60944636@N00/42298477505) |
| `monstera-deliciosa.jpg` | monstera-deliciosa | Dinesh Valke | BY-SA 2.0 | [Split-leaf Philodendron](https://www.flickr.com/photos/91314344@N00/368807391) |
| `no-ruy-bang-do-trang-tri-chau.jpg` | no-ruy-bang-do-trang-tri-chau | versageek | BY-SA 2.0 | [Christmas Tree Bow](https://www.flickr.com/photos/8241297@N03/3208437447) |
| `phan-bon-npk-dang-vien-tan-cham.jpg` | phan-bon-npk-dang-vien-tan-cham | Graham Steel | PDM 1.0 | [Slow release plant food granules](https://www.flickr.com/photos/7914713@N05/45877074582) |
| `reu-kho-phu-goc-100g.jpg` | reu-kho-phu-goc-100g | Horia Varlan | BY 2.0 | [Legs of a girl wearing black sneakers on green moss](https://www.flickr.com/photos/10361931@N06/4273902982) |
| `sen-da-kim-cuong-chau-treo.jpg` | sen-da-kim-cuong-chau-treo | srboisvert | BY 2.0 | [plants insanity](https://www.flickr.com/photos/35034346289@N01/5804078256) |
| `sen-da-mix-chau-da.jpg` | sen-da-mix-chau-da | joncutrer | BY 2.0 | [echeveria succulent](https://www.flickr.com/photos/47121680@N00/49279608393) |
| `sen-da-nau-chau-su-mini.jpg` | sen-da-nau-chau-su-mini | hortulus | BY 2.0 | [Some of our potted succulent collection](https://www.flickr.com/photos/15845498@N00/5331014669) |
| `set-qua-cay-de-ban-kem-thiep.jpg` | set-qua-cay-de-ban-kem-thiep | The Urban Botanist Images | BY 2.0 | [Succulents and Cacti with Marble Background](https://www.flickr.com/photos/193653073@N07/51443021534) |
| `soi-mau-trang-tri-500g.jpg` | soi-mau-trang-tri-500g | Bold Frontiers | BY 2.0 | [Colorful Stones](https://www.flickr.com/photos/82955120@N05/7995277907) |
| `thiep-chuc-mung-kem-kep-cam.jpg` | thiep-chuc-mung-kem-kep-cam | lifelikeapps | BY 2.0 | [Love Note 2](https://www.flickr.com/photos/56532794@N02/5352646071) |
| `thuoc-tri-nam-la-sinh-hoc.jpg` | thuoc-tri-nam-la-sinh-hoc | Arria Belli | BY-SA 2.0 | [Spray bottle top](https://www.flickr.com/photos/24363893@N00/2489811453) |
| `trau-ba-leo-cot.jpg` | trau-ba-leo-cot | Plant pests and diseases | CC0 1.0 | [Epipremnum aureum (golden pothos): Algal leaf spot caused by Cephaleuros sp.](https://www.flickr.com/photos/62295966@N07/43953782841) |
| `tuong-gom-mini-trang-tri-chau.jpg` | tuong-gom-mini-trang-tri-chau | dozymoo | BY-SA 2.0 | [Garden scene - miniature](https://www.flickr.com/photos/16464111@N08/5618978906) |
| `van-nien-thanh-chau-su.jpg` | van-nien-thanh-chau-su | Dinesh Valke | BY-SA 2.0 | [Dieffenbachia](https://www.flickr.com/photos/91314344@N00/405648176) |
| `vien-dat-nung-lot-day-chau-1kg.jpg` | vien-dat-nung-lot-day-chau-1kg | FAMAB e.V. | BY-SA 2.0 | [LECA (1)](https://www.flickr.com/photos/132007857@N08/23312004510) |
| `xuong-rong-bi-chau-dat-nung.jpg` | xuong-rong-bi-chau-dat-nung | fuentedelateja | BY-SA 2.0 | [Ombligo de la reina - Echinopsis eyriesii](https://www.flickr.com/photos/55917813@N00/2529987643) |
<!-- /credits:products -->

---

## Ảnh danh mục (`storage/app/public/categories/`)

- **Nguồn:** Openverse — https://openverse.org
- **Tải lại bằng:** `node tools/fetch-category-photos.mjs` rồi `php artisan categories:link-photos`
- **Khác ảnh sản phẩm ở chỗ nào:** thẻ danh mục là khung NGANG, nên script
  loại ảnh dọc ngay từ lúc tìm; thẻ sản phẩm là khung đứng 4:5 nên loại ảnh
  panorama. Luật giấy phép thì dùng chung một bản ở `tools/lib/openverse.mjs`.
- Bản ghi nguồn dạng máy đọc: `storage/app/public/categories/credits.json`.

<!-- credits:categories -->
| Tệp | Danh mục | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `chau-va-de-lot.jpg` | chau-va-de-lot | john bonham2 | BY-SA 2.0 | [flower pots red and blue flowers](https://www.flickr.com/photos/95205391@N05/8971105005) |
| `hoa-cuoi.jpg` | hoa-cuoi | jerryfergusonphotography | BY 2.0 | [Wedding Bouquet](https://www.flickr.com/photos/17445097@N03/8633321435) |
| `hoa-khai-truong-su-kien.jpg` | hoa-khai-truong-su-kien | wallygrom | BY-SA 2.0 | [Yellow Weingartia flowers](https://www.flickr.com/photos/33037982@N04/3496998657) |
| `hoa-qua-tang.jpg` | hoa-qua-tang | georigami | BY 2.0 | [Chris Palmer's Queen Flower](https://www.flickr.com/photos/69208357@N00/6405463855) |
| `phu-goc-tieu-canh.jpg` | phu-goc-tieu-canh | Wonderlane | BY 2.0 | [little miniature waterfall and stones, flower pot, koi pond, lily pads, trees, Meditation Garden - Self-Realization Fellowship, Encinitas, California, USA](https://www.flickr.com/photos/71401718@N00/3613472842) |
| `phu-kien.jpg` | phu-kien | ProFlowers.com | BY 2.0 | [lips picture in a frame with flower pot decoration including hyacinth narcissus crocus Wirosa tulips art on shelf](https://www.flickr.com/photos/127365614@N08/16849113245) |
| `sen-da-xuong-rong.jpg` | sen-da-xuong-rong | Loco Steve | BY 2.0 | [California's native plants display in Capitol park Sacramento](https://www.flickr.com/photos/36989019@N08/5209397866) |
| `vat-tu-cham-soc.jpg` | vat-tu-cham-soc | mjmonty | BY 2.0 | [365/66 California Compost](https://www.flickr.com/photos/36295747@N00/3339134710) |
<!-- /credits:categories -->

---

## Ảnh phụ trong thư viện sản phẩm (`storage/app/public/products/gallery/`)

- **Nguồn:** Openverse — https://openverse.org
- **Tải lại bằng:** `node tools/fetch-product-gallery.mjs` rồi `php artisan products:link-gallery`
- **Vì sao tách khỏi ảnh đại diện:** `components/product/gallery.blade.php`
  dựng sẵn khung thư viện có hàng ảnh nhỏ bấm để đổi ảnh lớn, nhưng bảng
  `product_images` rỗng hoàn toàn — mọi sản phẩm hiện đúng một ảnh và hàng
  ảnh nhỏ không bao giờ xuất hiện. Một khung thư viện chỉ có một ảnh là
  một cái khung rỗng có viền.
- **Không lấy trùng ảnh đại diện:** cùng câu truy vấn thì Openverse trả về
  cùng thứ tự kết quả, nên ảnh đầu gần như luôn là ảnh đã dùng. Script
  loại theo `foreign_landing_url` chứ không theo tên tệp — cùng một bức
  ảnh có thể tải về dưới hai tên khác nhau.
- Danh sách sản phẩm và câu truy vấn dùng chung với ảnh đại diện, ở
  `tools/lib/product-targets.mjs`.

<!-- credits:gallery -->
| Tệp | Sản phẩm | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `bao-li-xi-mini-treo-cay-ngay-tet-2.jpg` | bao-li-xi-mini-treo-cay-ngay-tet | Rod Raglin | BY-SA 2.0 | [Betty So hands out Red envelopes on behalf of MP Harjit Sajjan](https://www.flickr.com/photos/78791029@N04/25458550887) |
| `bao-li-xi-mini-treo-cay-ngay-tet-3.jpg` | bao-li-xi-mini-treo-cay-ngay-tet | Rod Raglin | BY-SA 2.0 | [Chinese New Year Red Envelopes](https://commons.wikimedia.org/w/index.php?curid=126389730) |
| `binh-thuy-tinh-cam-hoa-2.jpg` | binh-thuy-tinh-cam-hoa | John Beans | BY 2.0 | [clear glass flower vase near rolled grey mat - Credit to https://myfriendscoffee.com/](https://www.flickr.com/photos/147592390@N06/40713214813) |
| `binh-thuy-tinh-cam-hoa-3.jpg` | binh-thuy-tinh-cam-hoa | Hammer51012 | BY-SA 2.0 | [Glass Flower Vase](https://www.flickr.com/photos/7365168@N03/26585013425) |
| `binh-tuoi-voi-dai-15l-2.jpg` | binh-tuoi-voi-dai-15l | chimpwithcan | BY 2.0 | [Watering Can Garden Tool Green Jug Edited 2020](https://www.flickr.com/photos/188454520@N02/49906680177) |
| `binh-tuoi-voi-dai-15l-3.jpg` | binh-tuoi-voi-dai-15l | nenadstojkovicart | BY 2.0 | [A vintage metal watering can sits abandoned on lush green grass surrounded by well-tended plants in a serene garden](https://www.flickr.com/photos/202846129@N03/54581890734) |
| `bo-6-qua-cau-giang-sinh-treo-cay-2.jpg` | bo-6-qua-cau-giang-sinh-treo-cay | Horia Varlan | BY 2.0 | [Baubles and tinsel used to decorate the Christmas tree](https://www.flickr.com/photos/10361931@N06/4273200347) |
| `bo-6-qua-cau-giang-sinh-treo-cay-3.jpg` | bo-6-qua-cau-giang-sinh-treo-cay | arripay | BY-SA 2.0 | [heart bauble](https://www.flickr.com/photos/27466406@N00/6456914887) |
| `bo-cam-tu-cau-xanh-2.jpg` | bo-cam-tu-cau-xanh | Mike Kniec | BY 2.0 | [Flower Heart](https://www.flickr.com/photos/112923805@N05/14892879160) |
| `bo-cam-tu-cau-xanh-3.jpg` | bo-cam-tu-cau-xanh | Onasill - Bill Badzo - 149 Million Views - Thank Y | PDM 1.0 | [Toronto Ontario - Canada - Endless Summer Hydrangea - Allan Botanical Gardens](https://www.flickr.com/photos/7156765@N05/51683807565) |
| `bo-cuc-hoa-mi-trang-2.jpg` | bo-cuc-hoa-mi-trang | Yazuu | BY 2.0 | [Bunch of daisies](https://www.flickr.com/photos/26022173@N06/2935818816) |
| `bo-cuc-hoa-mi-trang-3.jpg` | bo-cuc-hoa-mi-trang | Swallowtail Garden Seeds | BY 2.0 | [Chamomile Flowers](https://www.flickr.com/photos/97123293@N07/19303821812) |
| `bo-hoa-mau-don-do-2.jpg` | bo-hoa-mau-don-do | Swallowtail Garden Seeds | PDM 1.0 | [Still life of roses, lilac, peonies, tulips, an iris, auriculus, Fritillaria imperialis, morning glory, and other flowers in a terracotta vase on a stone ledge, with a sprig of honeysuckle (1812)](https://www.flickr.com/photos/97123293@N07/17845174254) |
| `bo-hoa-mau-don-do-3.jpg` | bo-hoa-mau-don-do | Swallowtail Garden Seeds | PDM 1.0 | [Crown imperial, peonies, roses, dahlias, anemones, sweet peas, tulip and other flowers in a teracotta, on a marble ledge (1786-1844)](https://www.flickr.com/photos/97123293@N07/17990512312) |
| `bo-tulip-ha-lan-2.jpg` | bo-tulip-ha-lan | Muffet | BY 2.0 | [birthday bouquet](https://www.flickr.com/photos/53133240@N00/8543342510) |
| `bo-tulip-ha-lan-3.jpg` | bo-tulip-ha-lan | Flower's.Lover | BY 2.0 | [Flower](https://www.flickr.com/photos/53231916@N03/7844694878) |
| `bonsai-mai-chieu-thuy-2.jpg` | bonsai-mai-chieu-thuy | MShades | BY 2.0 | [Plum bonsai](https://www.flickr.com/photos/23054755@N00/363300928) |
| `bonsai-mai-chieu-thuy-3.jpg` | bonsai-mai-chieu-thuy | infomatique | BY-SA 2.0 | [Botanic Gardens: Exhibition – 'In celebration of trees' - An exhibition of Bonsai](https://www.flickr.com/photos/80824546@N00/6864698776) |
| `bonsai-tung-la-han-dang-truc-2.jpg` | bonsai-tung-la-han-dang-truc | 阿橋花譜 KHQ Flower Guide | BY-SA 2.0 | [羅漢松 Podocarpus macrophyllus [香港荔枝角公園 Lai Chi Kok Park, Hong Kong]](https://www.flickr.com/photos/52582306@N03/9216097488) |
| `bonsai-tung-la-han-dang-truc-3.jpg` | bonsai-tung-la-han-dang-truc | MeganEHansen | BY-SA 2.0 | [podocarpus macrophyllus](https://www.flickr.com/photos/24495410@N03/4850749898) |
| `canh-dao-phai-choi-tet-2.jpg` | canh-dao-phai-choi-tet | OakleyOriginals | BY 2.0 | [Spring Tree Blossoms](https://www.flickr.com/photos/47264866@N00/3343421030) |
| `canh-dao-phai-choi-tet-3.jpg` | canh-dao-phai-choi-tet | Martina Rathgens | BY 2.0 | [Peach Blossom](https://www.flickr.com/photos/31091837@N08/2907578814) |
| `cay-lan-y-chau-su-trang-2.jpg` | cay-lan-y-chau-su-trang | David Davies | BY-SA 2.0 | [Peace Lily](https://www.flickr.com/photos/44124390461@N01/3250459267) |
| `cay-lan-y-chau-su-trang-3.jpg` | cay-lan-y-chau-su-trang | David Davies | BY-SA 2.0 | [Peace Lily](https://www.flickr.com/photos/44124390461@N01/3250459851) |
| `chau-su-trang-co-vua-2.jpg` | chau-su-trang-co-vua | john bonham2 | BY-SA 2.0 | [Flower pots, color, with yoga master image](https://www.flickr.com/photos/95205391@N05/9107292260) |
| `chau-su-trang-co-vua-3.jpg` | chau-su-trang-co-vua | john bonham2 | BY-SA 2.0 | [Flower pots medieval old ships sailboats](https://www.flickr.com/photos/95205391@N05/8723183672) |
| `co-nhung-nhat-mini-phu-goc-2.jpg` | co-nhung-nhat-mini-phu-goc | jeans_Photos | BY 2.0 | [Moss](https://www.flickr.com/photos/63479603@N00/6148880340) |
| `co-nhung-nhat-mini-phu-goc-3.jpg` | co-nhung-nhat-mini-phu-goc | James St. John | BY 2.0 | [Tillandsia recurvata (ball moss) (Sanibel Island, Florida, USA) 2](https://www.flickr.com/photos/47445767@N05/25736429616) |
| `da-trang-phu-mat-chau-1kg-2.jpg` | da-trang-phu-mat-chau-1kg | pshutterbug | BY 2.0 | [Pebble Art](https://www.flickr.com/photos/95565118@N00/2382209408) |
| `da-trang-phu-mat-chau-1kg-3.jpg` | da-trang-phu-mat-chau-1kg | Bold Frontiers | BY 2.0 | [Colorful Stones](https://www.flickr.com/photos/82955120@N05/7995277667) |
| `dat-trong-tron-san-5kg-2.jpg` | dat-trong-tron-san-5kg | el cajon yacht club | BY 2.0 | [potting soil 20221024_095111](https://www.flickr.com/photos/60944636@N00/52451789748) |
| `dat-trong-tron-san-5kg-3.jpg` | dat-trong-tron-san-5kg | niiicedave | BY-SA 2.0 | [03 Make Sure Potting soil and dirt mixture is moist - IMGP0803](https://www.flickr.com/photos/33671002@N00/5644758958) |
| `day-den-led-mini-quan-cay-2.jpg` | day-den-led-mini-quan-cay | David Jackmanson | BY 2.0 | [An avenue of trees in Melbourne's Southbank, decorated with long strings of fairy lights and two rows of red Chinese lanterns for the 2018 Lunar New Year celebrations.](https://www.flickr.com/photos/58301516@N00/39364202415) |
| `day-den-led-mini-quan-cay-3.jpg` | day-den-led-mini-quan-cay | joncutrer | BY 2.0 | [Mason Jar Fairy Lights](https://www.flickr.com/photos/47121680@N00/44084093015) |
| `dia-lot-chau-chong-tran-2.jpg` | dia-lot-chau-chong-tran | Khong ghi ten | CC0 1.0 | [File:Terracotta saucer-shaped lamp MET GR679.jpg](https://commons.wikimedia.org/w/index.php?curid=60406585) |
| `dung-dich-duong-hoa-tuoi-2.jpg` | dung-dich-duong-hoa-tuoi | ProFlowers.com | BY 2.0 | [woman in white dress watering giant five foot tall red roses from a glass pitcher in a tall glass vase](https://www.flickr.com/photos/127365614@N08/16411391266) |
| `dung-dich-duong-hoa-tuoi-3.jpg` | dung-dich-duong-hoa-tuoi | Daniel Dudek | BY 2.0 | [Flowers in a vase](https://www.flickr.com/photos/57296780@N05/14485310599) |
| `duong-xi-boston-treo-2.jpg` | duong-xi-boston-treo | Starr Environmental | BY 2.0 | [starr-100623-7771-Nephrolepis_sp-potted_plants_in_shade_house-Pukalani_Plant_Company_Pulehu-Maui](https://www.flickr.com/photos/97499887@N06/24746746030) |
| `duong-xi-boston-treo-3.jpg` | duong-xi-boston-treo | Starr Environmental | BY 2.0 | [starr-110124-0033-Nephrolepis_sp-hanging_plant-Sacred_Garden_of_Maliko-Maui](https://www.flickr.com/photos/97499887@N06/24445289403) |
| `gio-hoa-baby-trang-2.jpg` | gio-hoa-baby-trang | Tatters ✾ | BY 2.0 | [What is a colour of Baby's Breath? :)](https://www.flickr.com/photos/62938898@N00/4663271312) |
| `gio-hoa-baby-trang-3.jpg` | gio-hoa-baby-trang | Tero Karppinen | BY 2.0 | [[False Prophet Illuminated \| Gypsophila elegans 1.2]](https://www.flickr.com/photos/191896879@N06/52355532136) |
| `gio-hoa-huong-duong-mini-2.jpg` | gio-hoa-huong-duong-mini | akari | BY-SA 2.0 | [Summer basket](https://www.flickr.com/photos/14299019@N00/4805243792) |
| `gio-hoa-huong-duong-mini-3.jpg` | gio-hoa-huong-duong-mini | Doubbt | BY 2.0 | [abundance-assortment-basket-1458694](https://www.flickr.com/photos/159949631@N04/45545443922) |
| `hoa-cai-ao-chu-re-2.jpg` | hoa-cai-ao-chu-re | babybizcakes | BY 2.0 | [Rose Boutonniere](https://www.flickr.com/photos/43701416@N08/8550569033) |
| `hoa-cai-ao-chu-re-3.jpg` | hoa-cai-ao-chu-re | babybizcakes | BY 2.0 | [Groomsmens Boutonnieres, Ivory/Gold & Brown](https://www.flickr.com/photos/43701416@N08/7635558140) |
| `hoa-cam-tay-co-dau-2.jpg` | hoa-cam-tay-co-dau | arripay | BY-SA 2.0 | [wedding flowers](https://www.flickr.com/photos/27466406@N00/2585587664) |
| `hoa-cam-tay-co-dau-3.jpg` | hoa-cam-tay-co-dau | jerryfergusonphotography | BY 2.0 | [Wedding Bouquet](https://www.flickr.com/photos/17445097@N03/8633321435) |
| `hoa-de-ban-tiec-cuoi-2.jpg` | hoa-de-ban-tiec-cuoi | ProFlowers.com | BY 2.0 | [Candy cane centerpiece vase with white tulips and red carnations and holly filler](https://www.flickr.com/photos/127365614@N08/15922772266) |
| `hoa-de-ban-tiec-cuoi-3.jpg` | hoa-de-ban-tiec-cuoi | parker yo! | BY-SA 2.0 | [flowers for Rachael + Derrick](https://www.flickr.com/photos/42242728@N06/5698323558) |
| `hop-hoa-hong-pastel-2.jpg` | hop-hoa-hong-pastel | slgckgc | BY 2.0 | [Roses](https://www.flickr.com/photos/14771153@N04/6823811307) |
| `hop-hoa-hong-pastel-3.jpg` | hop-hoa-hong-pastel | szeke | BY-SA 2.0 | [Rose](https://www.flickr.com/photos/43355249@N00/34976179810) |
| `hop-hoa-tulip-vang-2.jpg` | hop-hoa-tulip-vang | ProFlowers.com | BY 2.0 | [purple and yellow tulip bouquets in a glass vase with plates and a cups on a table](https://www.flickr.com/photos/127365614@N08/16618360659) |
| `hop-hoa-tulip-vang-3.jpg` | hop-hoa-tulip-vang | ProFlowers.com | BY 2.0 | [purple and yellow tulip bouquets in a glass vase with lemons and chocolates on a table](https://www.flickr.com/photos/127365614@N08/16617112310) |
| `huong-duong-ruc-ro-2.jpg` | huong-duong-ruc-ro | Twinkletoes61 | BY 2.0 | [Heidi and me in the background. (I wore my 'Jungle shirt' attire.) See the monkey on the table, and the monkey napkins and the REAL Kansas sunflower bouquet that decorated the table?! Jess loves balloons and this was quite the bouquet of them, topped wit](https://www.flickr.com/photos/45593232@N00/4967758825) |
| `huong-duong-ruc-ro-3.jpg` | huong-duong-ruc-ro | Muffet | BY 2.0 | [sunflower bouquet](https://www.flickr.com/photos/53133240@N00/1418724603) |
| `ke-hoa-khai-truong-hai-tang-2.jpg` | ke-hoa-khai-truong-hai-tang | Mary_on_Flickr | BY 2.0 | [Flower Arrangement (display)](https://www.flickr.com/photos/51965108@N05/6779062488) |
| `ke-hoa-khai-truong-hai-tang-3.jpg` | ke-hoa-khai-truong-hai-tang | tuchodi | BY 2.0 | [Amazing Flower Displays](https://www.flickr.com/photos/35034360491@N01/5635687563) |
| `keo-cat-canh-mui-cong-2.jpg` | keo-cat-canh-mui-cong | karenblakeman | CC0 1.0 | [Garden secateurs get the Reading Repair Cafe treatment](https://www.flickr.com/photos/11569642@N00/12034281506) |
| `keo-cat-canh-mui-cong-3.jpg` | keo-cat-canh-mui-cong | carlfbagge | BY 2.0 | [DP2M3559 Wolf Garten Medium Bypass Secateur RR19](https://www.flickr.com/photos/12535240@N05/14244856211) |
| `kim-ngan-ben-than-2.jpg` | kim-ngan-ben-than | wallygrom | BY-SA 2.0 | [Pachira aquatica](https://www.flickr.com/photos/33037982@N04/8431738365) |
| `kim-ngan-ben-than-3.jpg` | kim-ngan-ben-than | wallygrom | BY-SA 2.0 | [Pachira aquatica](https://www.flickr.com/photos/33037982@N04/8431739539) |
| `kim-tien-chau-su-2.jpg` | kim-tien-chau-su | olive.titus | PDM 1.0 | [Plante d'éternité, Zamioculcas zamiifolia, Aracées. Originaire de Zanzibar et de Tanzanie](https://www.flickr.com/photos/96064256@N04/45625387205) |
| `kim-tien-chau-su-3.jpg` | kim-tien-chau-su | Daniel Gasteiger | BY 2.0 | [Another Bonsai at the 2011 Philadelphia Flower Show](https://www.flickr.com/photos/30014417@N04/5524991164) |
| `lan-ho-diep-tim-chau-su-2.jpg` | lan-ho-diep-tim-chau-su | healthiermi | BY-SA 2.0 | [Purple Orchid Flower](https://www.flickr.com/photos/54781600@N05/34046966423) |
| `lan-ho-diep-tim-chau-su-3.jpg` | lan-ho-diep-tim-chau-su | Swami Stream | BY 2.0 | [Orchids at Flower Market, Bangkok (Explore)](https://www.flickr.com/photos/21063397@N00/2890011988) |
| `lang-hoa-khai-truong-2.jpg` | lang-hoa-khai-truong | Nullumayulife | BY 2.0 | [Japanese flower arrangement 2, Ikebana: いけばな](https://www.flickr.com/photos/72859063@N00/465912298) |
| `lang-hoa-khai-truong-3.jpg` | lang-hoa-khai-truong | Nullumayulife | BY 2.0 | [Japanese flower arrangement 19, Ikebana: いけばな](https://www.flickr.com/photos/72859063@N00/4442958076) |
| `luoi-ho-mini-de-ban-2.jpg` | luoi-ho-mini-de-ban | jalexartis | BY 2.0 | [My Sansevieria Trifasciata [Snake Plant/Mother-in-Law's Tongue], with a critter [inside the house]](https://www.flickr.com/photos/53625232@N00/10443885383) |
| `luoi-ho-mini-de-ban-3.jpg` | luoi-ho-mini-de-ban | Carol (vanhookc) | BY-SA 2.0 | [S is for Sanservierias (Snake Plant)](https://www.flickr.com/photos/97651299@N00/49557333417) |
| `luoi-ho-vang-vien-de-ban-2.jpg` | luoi-ho-vang-vien-de-ban | Jungle Garden | BY 2.0 | [Sansevieria trifasciata var. laurentii](https://www.flickr.com/photos/63405895@N07/24414209594) |
| `luoi-ho-vang-vien-de-ban-3.jpg` | luoi-ho-vang-vien-de-ban | Starr Environmental | BY 2.0 | [starr-980529-4162-Sansevieria_trifasciata-cv_laurentii_habit-Enchanting_Floral_Gardens_of_Kula-Maui](https://www.flickr.com/photos/97499887@N06/24431554651) |
| `monstera-deliciosa-chau-gom-2.jpg` | monstera-deliciosa-chau-gom | Dinesh Valke | BY-SA 2.0 | [Split-leaf Philodendron](https://www.flickr.com/photos/91314344@N00/368807391) |
| `monstera-deliciosa-chau-gom-3.jpg` | monstera-deliciosa-chau-gom | brewbooks | BY-SA 2.0 | [Fruit-salad plant](https://www.flickr.com/photos/93452909@N00/2170428463) |
| `no-ruy-bang-do-trang-tri-chau-2.jpg` | no-ruy-bang-do-trang-tri-chau | docoverachiever | BY 2.0 | [Scarlet Ribbons for Her Hair](https://www.flickr.com/photos/90692748@N04/8539971969) |
| `no-ruy-bang-do-trang-tri-chau-3.jpg` | no-ruy-bang-do-trang-tri-chau | ProFlowers.com | BY 2.0 | [A garland strung across a fireplace mantel with bright red ribbons and a pine wreath with a bow hanging on the wall](https://www.flickr.com/photos/127365614@N08/15699846197) |
| `reu-kho-phu-goc-100g-2.jpg` | reu-kho-phu-goc-100g | Horia Varlan | BY 2.0 | [Large crack on green moss covered stone](https://www.flickr.com/photos/10361931@N06/4514166022) |
| `reu-kho-phu-goc-100g-3.jpg` | reu-kho-phu-goc-100g | qubodup | BY 2.0 | [Flaky red brown tree bark with green moss spots reference](https://www.flickr.com/photos/21051491@N02/2956286322) |
| `sen-da-kim-cuong-chau-treo-2.jpg` | sen-da-kim-cuong-chau-treo | Kumaravel | BY 2.0 | [Graptopetalum](https://www.flickr.com/photos/49694447@N00/8574975098) |
| `sen-da-kim-cuong-chau-treo-3.jpg` | sen-da-kim-cuong-chau-treo | srboisvert | BY 2.0 | [plants insanity](https://www.flickr.com/photos/35034346289@N01/5803522315) |
| `sen-da-mix-chau-da-2.jpg` | sen-da-mix-chau-da | el cajon yacht club | BY 2.0 | [Echeveria succulent IMG_1320](https://www.flickr.com/photos/60944636@N00/52929952655) |
| `sen-da-mix-chau-da-3.jpg` | sen-da-mix-chau-da | jetaime | BY-SA 2.0 | [Echeveria succulent flowering](https://www.flickr.com/photos/73438105@N00/52535410490) |
| `sen-da-nau-chau-su-mini-2.jpg` | sen-da-nau-chau-su-mini | hortulus | BY 2.0 | [Echeveria 'Hummel #1'](https://www.flickr.com/photos/15845498@N00/8737082264) |
| `sen-da-nau-chau-su-mini-3.jpg` | sen-da-nau-chau-su-mini | srboisvert | BY 2.0 | [Echeveria Elegans](https://www.flickr.com/photos/35034346289@N01/5849688118) |
| `set-qua-cay-de-ban-kem-thiep-2.jpg` | set-qua-cay-de-ban-kem-thiep | The Urban Botanist Images | BY 2.0 | [Succulents and Cacti with Marble Background - close up](https://www.flickr.com/photos/193653073@N07/51443242760) |
| `set-qua-cay-de-ban-kem-thiep-3.jpg` | set-qua-cay-de-ban-kem-thiep | tree2mydoor | BY 2.0 | [Cactus Plants in Pots: Creative Commons](https://www.flickr.com/photos/37923999@N02/48078216446) |
| `soi-mau-trang-tri-500g-2.jpg` | soi-mau-trang-tri-500g | Smabs Sputzer (1956-2017) | BY 2.0 | [Painted Pebble Pansies](https://www.flickr.com/photos/10413717@N08/3868218587) |
| `soi-mau-trang-tri-500g-3.jpg` | soi-mau-trang-tri-500g | Bold Frontiers | BY 2.0 | [Colorful Stones](https://www.flickr.com/photos/82955120@N05/7995277497) |
| `thiep-chuc-mung-kem-kep-cam-2.jpg` | thiep-chuc-mung-kem-kep-cam | jenlemen | BY 2.0 | [trust cards you can love flickr](https://www.flickr.com/photos/43127617@N00/3041542140) |
| `thiep-chuc-mung-kem-kep-cam-3.jpg` | thiep-chuc-mung-kem-kep-cam | GlitterandFrills | BY 2.0 | [Alice in Wonderland tags 2](https://www.flickr.com/photos/33334577@N06/4608168348) |
| `thuoc-tri-nam-la-sinh-hoc-2.jpg` | thuoc-tri-nam-la-sinh-hoc | Arria Belli | BY-SA 2.0 | [091/366 - Spray bottle top in HDR](https://www.flickr.com/photos/24363893@N00/2490634896) |
| `thuoc-tri-nam-la-sinh-hoc-3.jpg` | thuoc-tri-nam-la-sinh-hoc | Arria Belli | BY-SA 2.0 | [Spray bottle top in HDR](https://www.flickr.com/photos/24363893@N00/2490631556) |
| `trau-ba-leo-cot-2.jpg` | trau-ba-leo-cot | yellow_bird_woodstock | BY-SA 2.0 | [植え替えしたポトスは元気 (pothos in a new pot)](https://www.flickr.com/photos/32872140@N05/3556523222) |
| `trau-ba-leo-cot-3.jpg` | trau-ba-leo-cot | eggrole | BY 2.0 | [Misted Golden Pothos](https://www.flickr.com/photos/35387910@N04/6351521937) |
| `tuong-gom-mini-trang-tri-chau-2.jpg` | tuong-gom-mini-trang-tri-chau | dozymoo | BY-SA 2.0 | [Garden scene - miniature](https://www.flickr.com/photos/16464111@N08/5618978902) |
| `tuong-gom-mini-trang-tri-chau-3.jpg` | tuong-gom-mini-trang-tri-chau | burge5k | BY 2.0 | [Pete looking a bit like a gnome](https://www.flickr.com/photos/56579997@N00/117661819) |
| `van-nien-thanh-chau-su-2.jpg` | van-nien-thanh-chau-su | blumenbiene | BY 2.0 | [Dieffenbachie (Dieffenbachia 'Reflector')](https://www.flickr.com/photos/47439717@N05/33414052754) |
| `van-nien-thanh-chau-su-3.jpg` | van-nien-thanh-chau-su | Starr Environmental | BY 2.0 | [starr-110215-1243-Dieffenbachia_seguine-habit-KiHana_Nursery_Kihei-Maui](https://www.flickr.com/photos/97499887@N06/24449059743) |
| `vien-dat-nung-lot-day-chau-1kg-2.jpg` | vien-dat-nung-lot-day-chau-1kg | Mark from San Francisco, CA, USA | BY-SA 2.0 | [Piscina Mares (Leca Palmeira)](https://commons.wikimedia.org/w/index.php?curid=3752634) |
| `vien-dat-nung-lot-day-chau-1kg-3.jpg` | vien-dat-nung-lot-day-chau-1kg | bitjungle | BY-SA 2.0 | [Leca](https://www.flickr.com/photos/54318649@N00/334116077) |
| `xuong-rong-bi-chau-dat-nung-2.jpg` | xuong-rong-bi-chau-dat-nung | fuentedelateja | BY-SA 2.0 | [Ombligo de la reina - Echinopsis eyriesii](https://www.flickr.com/photos/55917813@N00/2529957217) |
| `xuong-rong-bi-chau-dat-nung-3.jpg` | xuong-rong-bi-chau-dat-nung | blumenbiene | BY 2.0 | [Echinopsis Hybride](https://www.flickr.com/photos/47439717@N05/5852767405) |
<!-- /credits:gallery -->

---

## Ảnh nền banner khuyến mại (`storage/app/public/promotions/`)

- **Nguồn:** Openverse — https://openverse.org
- **Tải lại bằng:** `node tools/fetch-promotion-banners.mjs`
- **KHÔNG tự gán vào cơ sở dữ liệu.** Banner là quyết định thương hiệu;
  admin tự chọn ở trang Khuyến mại → Sửa. Một bức ảnh stock chọn hộ chưa
  chắc hợp với chiến dịch đang chạy.
- Khoá theo `theme_key` chứ không theo slug chương trình: nhiều chương
  trình cùng chủ đề dùng chung được một ảnh, và chủ đề lặp lại hằng năm
  còn slug thì không.

<!-- credits:promotions -->
| Tệp | Chủ đề | Tác giả | Giấy phép | Nguồn |
|---|---|---|---|---|
| `noel.jpg` | noel | zaimoku_woodpile | BY 2.0 | [christmas tree ornament](https://www.flickr.com/photos/11250735@N07/11338452985) |
| `tet.jpg` | tet | naturalflow | BY-SA 2.0 | [apricot blossoms](https://www.flickr.com/photos/70693287@N00/4479270934) |
| `valentine.jpg` | valentine | Monkeystyle3000 | BY 2.0 | [Red Rose](https://www.flickr.com/photos/132295270@N07/45678290115) |
<!-- /credits:promotions -->

