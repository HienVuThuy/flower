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
| `binh-thuy-tinh-cam-hoa.jpg` | binh-thuy-tinh-cam-hoa | Ron Meck | BY 2.0 | [Glass Flower Vases](https://www.flickr.com/photos/115284274@N07/14498230476) |
| `bo-cam-tu-cau-xanh.jpg` | bo-cam-tu-cau-xanh | Scott 97006 | BY 2.0 | [Blue Hydrangea Flower Cluster](https://www.flickr.com/photos/29487672@N07/14584149951) |
| `bo-cuc-hoa-mi-trang.jpg` | bo-cuc-hoa-mi-trang | docoverachiever | BY 2.0 | [A single flower](https://www.flickr.com/photos/90692748@N04/23712660045) |
| `bo-hoa-mau-don-do.jpg` | bo-hoa-mau-don-do | Muffet | BY 2.0 | [gathering of peonies](https://www.flickr.com/photos/53133240@N00/3674711901) |
| `bo-tulip-ha-lan.jpg` | bo-tulip-ha-lan | Muffet | BY 2.0 | [birthday bouquet](https://www.flickr.com/photos/53133240@N00/8543342510) |
| `bonsai-mai-chieu-thuy.jpg` | bonsai-mai-chieu-thuy | Daniel Gasteiger | BY 2.0 | [Another Bonsai at the 2011 Philadelphia Flower Show](https://www.flickr.com/photos/30014417@N04/5524991164) |
| `bonsai-tung-la-han-dang-truc.jpg` | bonsai-tung-la-han-dang-truc | thisfeministrox | BY 2.0 | [Lantana Bonsai](https://www.flickr.com/photos/60088764@N00/1387656483) |
| `canh-dao-phai-choi-tet.jpg` | canh-dao-phai-choi-tet | OakleyOriginals | BY 2.0 | [Spring Tree Blossoms](https://www.flickr.com/photos/47264866@N00/3343421030) |
| `cay-lan-y-chau-su-trang.jpg` | cay-lan-y-chau-su-trang | daBinsi | BY 2.0 | [Spathiphyllum 'Peace Lily'](https://www.flickr.com/photos/13741829@N07/3328727610) |
| `cay-luoi-ho-vang-vien-de-ban.jpg` | cay-luoi-ho-vang-vien-de-ban | Jungle Garden | BY 2.0 | [Sansevieria trifasciata var. laurentii](https://www.flickr.com/photos/63405895@N07/24414209594) |
| `chau-su-trang-co-vua.jpg` | chau-su-trang-co-vua | john bonham2 | BY-SA 2.0 | [Flower pot Pablo Picasso series](https://www.flickr.com/photos/95205391@N05/9090127853) |
| `dat-trong-tron-san-5kg.jpg` | dat-trong-tron-san-5kg | Alex Cheek | BY-SA 2.0 | [These bulbs are breaking through the compacted potting soil, leaving cracks and causing general but small-scale tectonic upheaval in the flowerpots near school.](https://www.flickr.com/photos/76903355@N00/444011608) |
| `dia-lot-chau-chong-tran.jpg` | dia-lot-chau-chong-tran | garryknight | BY 2.0 | [Teacup Plant Pot](https://www.flickr.com/photos/8176740@N05/10534474116) |
| `dung-dich-duong-hoa-tuoi.jpg` | dung-dich-duong-hoa-tuoi | Wonderlane | BY 2.0 | [Ornate multi-headed, multi-armed Chenrayzee statue, holding a mala, flower, vase of nectar, bow and arrow, and bell with dharma wheel, stupa construction, Kopan Monastery and Nunnery, Kapan Village, Kathmandu, Nepal Kathmandu, Nepal](https://www.flickr.com/photos/71401718@N00/5108108964) |
| `duong-xi-boston-treo.jpg` | duong-xi-boston-treo | Starr Environmental | BY 2.0 | [starr-100623-7772-Nephrolepis_sp-potted_plants_in_shade_house-Pukalani_Plant_Company_Pulehu-Maui](https://www.flickr.com/photos/97499887@N06/24949015421) |
| `gio-hoa-baby-trang.jpg` | gio-hoa-baby-trang | Swallowtail Garden Seeds | PDM 1.0 | [Flowers with Fruit and a Bird's Nest on a Marble Ledge (1840)](https://www.flickr.com/photos/97123293@N07/16882592463) |
| `gio-hoa-huong-duong-mini.jpg` | gio-hoa-huong-duong-mini | tracydekalb | BY 2.0 | [Basket of sunshine](https://www.flickr.com/photos/11540627@N03/4810587301) |
| `hoa-cai-ao-chu-re.jpg` | hoa-cai-ao-chu-re | hortulus | BY 2.0 | [back from the wedding . . .](https://www.flickr.com/photos/15845498@N00/3789340877) |
| `hoa-cam-tay-co-dau.jpg` | hoa-cam-tay-co-dau | jerryfergusonphotography | BY 2.0 | [Wedding Bouquet](https://www.flickr.com/photos/17445097@N03/8633321435) |
| `hoa-de-ban-tiec-cuoi.jpg` | hoa-de-ban-tiec-cuoi | Tracy Hunter | BY 2.0 | [Centerpieces](https://www.flickr.com/photos/11121785@N00/164578909) |
| `hop-hoa-hong-pastel.jpg` | hop-hoa-hong-pastel | slgckgc | BY 2.0 | [Rose](https://www.flickr.com/photos/14771153@N04/4568774352) |
| `hop-hoa-tulip-vang.jpg` | hop-hoa-tulip-vang | Kirt Edblom | BY-SA 2.0 | [Crayon Box of Flowers](https://www.flickr.com/photos/27190564@N02/16732302779) |
| `huong-duong-ruc-ro.jpg` | huong-duong-ruc-ro | kinglear55 | BY 2.0 | [Sunflower Bouquet](https://www.flickr.com/photos/65469424@N05/50366454337) |
| `ke-hoa-khai-truong-hai-tang.jpg` | ke-hoa-khai-truong-hai-tang | Macleay Grass Man | BY 2.0 | [Walwhalleya proluta flowerhead10 SWS](https://www.flickr.com/photos/73840284@N04/9250176163) |
| `keo-cat-canh-mui-cong.jpg` | keo-cat-canh-mui-cong | karenblakeman | CC0 1.0 | [Garden secateurs get the Reading Repair Cafe treatment](https://www.flickr.com/photos/11569642@N00/12034281506) |
| `kim-ngan-ben-than.jpg` | kim-ngan-ben-than | mauro halpern | BY 2.0 | [Munguba/Monguba/Castanheiro do Maranhão / Money-tree (Pachira aquatica) flowers. Brazil / Latin-América native tree](https://www.flickr.com/photos/41597043@N00/3452213093) |
| `kim-tien-chau-su.jpg` | kim-tien-chau-su | wlcutler | BY-SA 2.0 | [Zamioculcas-ZZ-plant_Cutler_20160617_P1260075](https://www.flickr.com/photos/20664893@N00/46385996094) |
| `lan-ho-diep-tim-chau-su.jpg` | lan-ho-diep-tim-chau-su | HenryLeongHimWoh | BY-SA 2.0 | [Purple Orchid,Singapore Botanical Garden](https://www.flickr.com/photos/19517908@N00/4698040551) |
| `lang-hoa-khai-truong.jpg` | lang-hoa-khai-truong | Nullumayulife | BY 2.0 | [Japanese flower arrangement 19, Ikebana: いけばな](https://www.flickr.com/photos/72859063@N00/4442958076) |
| `luoi-ho-mini-de-ban.jpg` | luoi-ho-mini-de-ban | el cajon yacht club | BY 2.0 | [instax-Sansevieria-snake-plant-180704a](https://www.flickr.com/photos/60944636@N00/42298477505) |
| `monstera-deliciosa.jpg` | monstera-deliciosa | Dinesh Valke | BY-SA 2.0 | [Split-leaf Philodendron](https://www.flickr.com/photos/91314344@N00/368807391) |
| `phan-bon-npk-dang-vien-tan-cham.jpg` | phan-bon-npk-dang-vien-tan-cham | mikecogh | BY-SA 2.0 | [Fertilised Bulb](https://www.flickr.com/photos/89165847@N00/6305758762) |
| `sen-da-kim-cuong-chau-treo.jpg` | sen-da-kim-cuong-chau-treo | srboisvert | BY 2.0 | [plants insanity](https://www.flickr.com/photos/35034346289@N01/5804078256) |
| `sen-da-mix-chau-da.jpg` | sen-da-mix-chau-da | PattayaPatrol | BY-SA 2.0 | [DSC_5666: many different kinds of cactus in small pots](https://www.flickr.com/photos/194424926@N05/54113170325) |
| `sen-da-nau-chau-su-mini.jpg` | sen-da-nau-chau-su-mini | hortulus | BY 2.0 | [Some of our potted succulent collection](https://www.flickr.com/photos/15845498@N00/5331014669) |
| `set-qua-cay-de-ban-kem-thiep.jpg` | set-qua-cay-de-ban-kem-thiep | The Urban Botanist Images | BY 2.0 | [Succulents and Cacti with Marble Background](https://www.flickr.com/photos/193653073@N07/51443021534) |
| `thuoc-tri-nam-la-sinh-hoc.jpg` | thuoc-tri-nam-la-sinh-hoc | Arria Belli | BY-SA 2.0 | [Spray bottle top](https://www.flickr.com/photos/24363893@N00/2489811453) |
| `trau-ba-leo-cot.jpg` | trau-ba-leo-cot | yellow_bird_woodstock | BY-SA 2.0 | [植え替えしたポトスは元気 (pothos in a new pot)](https://www.flickr.com/photos/32872140@N05/3556523222) |
| `van-nien-thanh-chau-su.jpg` | van-nien-thanh-chau-su | Key West Wedding Photography | BY-SA 2.0 | [Dieffenbachia (Dumb Cane) Flower](https://www.flickr.com/photos/58003213@N00/3949133636) |
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
| `hoa-cuoi.jpg` | hoa-cuoi | jerryfergusonphotography | BY 2.0 | [Wedding Bouquet](https://www.flickr.com/photos/17445097@N03/8633321435) |
| `hoa-khai-truong-su-kien.jpg` | hoa-khai-truong-su-kien | wallygrom | BY-SA 2.0 | [Yellow Weingartia flowers](https://www.flickr.com/photos/33037982@N04/3496998657) |
| `hoa-qua-tang.jpg` | hoa-qua-tang | georigami | BY 2.0 | [Chris Palmer's Queen Flower](https://www.flickr.com/photos/69208357@N00/6405463855) |
| `phu-kien.jpg` | phu-kien | ProFlowers.com | BY 2.0 | [lips picture in a frame with flower pot decoration including hyacinth narcissus crocus Wirosa tulips art on shelf](https://www.flickr.com/photos/127365614@N08/16849113245) |
| `sen-da-xuong-rong.jpg` | sen-da-xuong-rong | Loco Steve | BY 2.0 | [California's native plants display in Capitol park Sacramento](https://www.flickr.com/photos/36989019@N08/5209397866) |
| `vat-tu-cham-soc.jpg` | vat-tu-cham-soc | mjmonty | BY 2.0 | [365/66 California Compost](https://www.flickr.com/photos/36295747@N00/3339134710) |
<!-- /credits:categories -->

