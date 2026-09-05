/*
 * Danh sách sản phẩm và câu truy vấn ảnh — DÙNG CHUNG.
 * ============================================================
 * Tách khỏi `fetch-product-photos.mjs` khi thêm công cụ tải ảnh thư
 * viện: hai script cần đúng một danh sách này, và chép sang một bản thứ
 * hai thì sớm muộn hai bản sẽ lệch.
 *
 * Lệch ở đây không phải chuyện nhỏ: câu `must` và `block` của từng món là
 * thứ đã chặn được một bức tranh sơn dầu năm 1840 lọt vào chỗ giỏ hoa
 * baby, và chặn ảnh xương rồng lọt vào chỗ sen đá. Một bản chép thiếu
 * những luật đó sẽ âm thầm nhận lại đúng các lỗi ấy.
 *
 * VIẾT TRUY VẤN NGẮN THÔI. Openverse nối các từ bằng AND, nên truy vấn
 * càng dài càng dễ ra 0 kết quả — đo được: "zamioculcas zamiifolia plant
 * pot" trả về 0, còn "zamioculcas" trả về 20. Danh sách `alt` là các
 * phương án lùi dần về từ chung hơn.
 */

export const PRODUCT_TARGETS = [
    { slug: 'bo-tulip-ha-lan', q: 'tulip bouquet flowers', alt: ['colorful tulips bunch', 'tulip flowers vase'] },
    { slug: 'huong-duong-ruc-ro', q: 'sunflower bouquet', alt: ['sunflowers bunch vase', 'sunflower flowers'] },
    /*
     * Không khai `must` nên rơi về MUST_DEFAULT (rất rộng), và đã nhận
     * một BỨC TRANH SƠN DẦU năm 1840. TITLE_BLOCK có chặn "painting"
     * nhưng tiêu đề bức đó không có chữ nào trong danh sách.
     */
    { slug: 'gio-hoa-baby-trang', q: 'gypsophila baby breath', alt: ['babys breath flowers', 'gypsophila white flowers'],
        must: /gypsophila|baby'?s breath|babys breath/i, block: /1[6-9]\d{2}|ledge|still life/i },
    { slug: 'canh-dao-phai-choi-tet', q: 'peach blossom branch pink', alt: ['peach blossom tree flowers', 'plum blossom branch'] },
    { slug: 'lang-hoa-khai-truong', q: 'flower arrangement', alt: ['flower basket', 'floral bouquet vase'], must: /flower|floral|bouquet|ikebana/i },
    { slug: 'hoa-cam-tay-co-dau', q: 'bridal bouquet wedding flowers', alt: ['bride holding bouquet', 'wedding bouquet white roses'] },
    { slug: 'hop-hoa-hong-pastel', q: 'roses', alt: ['pink roses bouquet', 'rose flowers'], must: /rose|flower|floral|bouquet/i },
    { slug: 'set-qua-cay-de-ban-kem-thiep', q: 'small potted plant gift', alt: ['desk plant pot gift', 'succulent gift pot'] },
    { slug: 'kim-tien-chau-su', q: 'zamioculcas', alt: ['houseplant pot', 'green plant pot indoor'] },
    { slug: 'luoi-ho-mini-de-ban', q: 'snake plant', alt: ['sansevieria plant', 'houseplant pot'], must: /sansevieria|snake plant/i },
    { slug: 'trau-ba-leo-cot', q: 'pothos', alt: ['epipremnum', 'philodendron plant'] },
    // Đã nhận ảnh toàn XƯƠNG RỒNG — sen đá và xương rồng là hai nhóm khác nhau.
    { slug: 'sen-da-mix-chau-da', q: 'succulent arrangement pot', alt: ['mixed succulents planter', 'echeveria succulents'],
        must: /succulent|echeveria|sedum|crassula/i, block: /cactus|cacti/i },
    { slug: 'bonsai-mai-chieu-thuy', q: 'bonsai tree pot exhibition', alt: ['bonsai juniper real tree', 'bonsai pine tree pot'] },
    { slug: 'monstera-deliciosa-chau-gom', q: 'monstera deliciosa plant', alt: ['monstera leaves', 'swiss cheese plant'] },

    /* ---------- cây bổ sung ---------- */
    { slug: 'duong-xi-boston-treo', q: 'boston fern', alt: ['nephrolepis fern plant', 'hanging fern pot'] },
    // Đã nhận ảnh HOA Dieffenbachia — sản phẩm là cây lá trồng chậu.
    { slug: 'van-nien-thanh-chau-su', q: 'dieffenbachia plant', alt: ['aglaonema houseplant', 'dieffenbachia leaves'],
        must: /dieffenbachia|aglaonema|dumb ?cane/i, block: /flower|blossom/i },
    /*
     * Đã nhận ảnh HOA của Pachira aquatica — đúng loài, nhưng sản phẩm là
     * cây bện thân trồng chậu, không phải bông hoa.
     */
    { slug: 'kim-ngan-ben-than', q: 'pachira aquatica plant', alt: ['money tree houseplant', 'braided money tree pot'],
        must: /pachira|money[- ]tree/i, block: /flower|blossom|fruit|seed/i },
    { slug: 'lan-ho-diep-tim-chau-su', q: 'phalaenopsis orchid purple', alt: ['purple orchid flower', 'phalaenopsis orchid'] },
    { slug: 'bo-cam-tu-cau-xanh', q: 'blue hydrangea', alt: ['hydrangea flowers blue', 'hydrangea bouquet'] },

    /*
     * ---------- phụ kiện & vật tư ----------
     *
     * Nhóm này KHÓ TÌM hơn hẳn nhóm cây: Openverse là kho ảnh tự do, rất
     * nhiều ảnh cây cỏ nhưng rất ít ảnh "đĩa lót chậu" hay "viên đất
     * nung". Vì vậy `alt` ở đây lùi về từ chung hơn nhiều bước, và món
     * nào không tìm được thì cứ để trống — thẻ sản phẩm đã có hình lá
     * giữ chỗ, còn ảnh sai chủ đề thì tệ hơn không có ảnh.
     */
    { slug: 'chau-su-trang-co-vua', q: 'white ceramic flower pot', alt: ['ceramic plant pot', 'flower pot empty'], must: /pot|planter|ceramic|vase/i },
    /*
     * Đã nhận "Teacup Plant Pot" — một cái tách trà dùng làm chậu, lọt
     * qua vì /pot/ nằm trong danh sách HOẶC.
     */
    { slug: 'dia-lot-chau-chong-tran', q: 'plant pot saucer', alt: ['terracotta saucer', 'plant drip tray'],
        must: /saucer|drip tray|pot tray|plant tray/i },
    /*
     * `/water|can/i` LÀ MỘT LỖI THẬT, và ảnh sai đã lên trang.
     *
     * Hai từ đó là HOẶC, và cả hai đều là từ rất thường gặp — tiêu đề
     * "Crews work to clean up debris so water can flow" chứa đủ cả hai
     * nên lọt qua. Sản phẩm "Bình tưới vòi dài" đã mang ảnh một đội thi
     * công dọn rác trên đường suốt từ đó.
     *
     * Cụm từ hoàn chỉnh chứ không phải hai từ rời.
     */
    { slug: 'binh-tuoi-voi-dai-15l', q: 'watering can garden', alt: ['metal watering can', 'watering can flowers'], must: /watering[- ]?can/i },
    { slug: 'keo-cat-canh-mui-cong', q: 'garden pruning shears', alt: ['secateurs', 'pruning scissors'], must: /shear|prun|scissor|secateur|clipper/i },
    { slug: 'binh-thuy-tinh-cam-hoa', q: 'glass flower vase', alt: ['vase flowers glass', 'clear glass vase'], must: /vase|glass/i },

    /*
     * Đã nhận ảnh một CỦ HOA: tiêu đề "Fertilised Bulb" lọt qua /fertili/.
     */
    { slug: 'phan-bon-npk-dang-vien-tan-cham', q: 'fertilizer granules', alt: ['slow release fertiliser', 'plant food granules'],
        must: /fertili[sz]er|granule|pellet|npk|plant food/i, block: /bulb|flower/i },
    { slug: 'dat-trong-tron-san-5kg', q: 'potting soil', alt: ['compost soil bag', 'garden soil'], must: /soil|compost|potting|substrat/i },
    /*
     * Từng nhận ảnh một PHO TƯỢNG Phật: tiêu đề dài có chữ "flower" và
     * "vase of nectar" nên lọt cả hai vế của /flower|vase/i.
     */
    { slug: 'dung-dich-duong-hoa-tuoi', q: 'flowers in glass vase water', alt: ['fresh cut flowers vase', 'bouquet in vase'],
        must: /vase|bouquet/i, block: /statue|buddha|temple|monastery|shrine|deity/i },
    { slug: 'thuoc-tri-nam-la-sinh-hoc', q: 'spray bottle garden', alt: ['plant spray bottle', 'garden sprayer'], must: /spray|bottle|sprayer/i },

    /* ---------- đợt dữ liệu mẫu bổ sung ---------- */
    { slug: 'sen-da-nau-chau-su-mini', q: 'echeveria succulent pot', alt: ['small succulent pot', 'succulent rosette'], must: /succulent|echeveria|plant|pot/i },
    { slug: 'luoi-ho-vang-vien-de-ban', q: 'sansevieria laurentii', alt: ['snake plant variegated', 'sansevieria pot'], must: /sansevieria|snake plant/i },
    /*
     * ĐÚNG LOẠI NHƯNG SAI LOÀI: đã nhận "Lantana Bonsai" — một cây bonsai
     * thật, nhưng là Lantana chứ không phải tùng la hán. Sản phẩm nói rõ
     * loài, và trang chi tiết còn hiện cả đường dẫn phân loại tới
     * Podocarpus macrophyllus — ảnh sai loài ở đó là tự mâu thuẫn.
     *
     * Không tìm được thì để trống: thẻ sản phẩm đã có hình lá giữ chỗ,
     * còn ảnh sai loài thì tệ hơn không có ảnh.
     */
    { slug: 'bonsai-tung-la-han-dang-truc', q: 'podocarpus macrophyllus', alt: ['podocarpus bonsai', 'buddhist pine tree'],
        must: /podocarpus|buddhist pine|kusamaki/i },
    { slug: 'hoa-cai-ao-chu-re', q: 'boutonniere wedding', alt: ['buttonhole flower groom', 'wedding boutonniere'], must: /boutonniere|buttonhole|flower|wedding/i },
    { slug: 'hoa-de-ban-tiec-cuoi', q: 'wedding table centerpiece flowers', alt: ['table flower arrangement', 'centerpiece flowers'], must: /flower|floral|centerpiece|arrangement/i },
    { slug: 'xuong-rong-bi-chau-dat-nung', q: 'echinopsis cactus pot', alt: ['round cactus terracotta', 'small cactus pot'], must: /cact|echinopsis|plant/i },
    { slug: 'sen-da-kim-cuong-chau-treo', q: 'graptopetalum succulent', alt: ['hanging succulent pot', 'powdery succulent'], must: /succulent|graptopetalum|plant/i },
    { slug: 'hop-hoa-tulip-vang', q: 'yellow tulips box', alt: ['yellow tulips bouquet', 'tulips flower box'], must: /tulip|flower/i },
    { slug: 'gio-hoa-huong-duong-mini', q: 'sunflower basket', alt: ['sunflowers in basket', 'sunflower arrangement'], must: /sunflower|flower|basket/i },
    /*
     * Từng nhận ảnh cận cảnh một BÔNG CỎ dại: tiêu đề
     * "Walwhalleya proluta flowerhead10" chứa "flower" trong
     * "flowerhead".
     */
    { slug: 'ke-hoa-khai-truong-hai-tang', q: 'large flower arrangement stand', alt: ['floral display arrangement', 'flower basket large'],
        must: /flower arrangement|floral arrangement|flower (basket|display|stand)|ikebana|wreath/i },
    /*
     * Đã nhận một bông ĐỒNG TIỀN ĐỎ: tiêu đề chỉ có "A single flower",
     * lọt qua vì /flower/ nằm trong danh sách HOẶC. Sản phẩm là cúc hoạ
     * mi TRẮNG.
     */
    { slug: 'bo-cuc-hoa-mi-trang', q: 'white daisies bunch', alt: ['chamomile flowers', 'marguerite daisy white'],
        must: /daisy|daisies|chamomile|marguerite|chrysanthemum/i, block: /red|gerbera|orange|purple/i },
    { slug: 'bo-hoa-mau-don-do', q: 'red peony bouquet', alt: ['peony flowers red', 'peonies bouquet'], must: /peony|peonies|flower/i },
    { slug: 'cay-lan-y-chau-su-trang', q: 'spathiphyllum peace lily', alt: ['peace lily plant', 'spathiphyllum pot'], must: /spathiphyllum|peace lily|lily|plant/i },

    /* ============================================================
     * PHỦ GỐC & TIỂU CẢNH — nhóm hàng mới
     * ============================================================
     * Nhóm này khó tìm ảnh nhất trong cả tệp: Openverse có vô số ảnh cây
     * cỏ nhưng rất ít ảnh "đá phủ mặt chậu". `alt` lùi dần về từ chung
     * hơn, và món nào không ra thì để trống — hình lá giữ chỗ vẫn hơn
     * một tấm ảnh sai chủ đề.
     */
    { slug: 'da-trang-phu-mat-chau-1kg', q: 'white pebbles stones', alt: ['white gravel decorative', 'white stones garden'],
        must: /pebble|gravel|stone|rock/i, block: /wall|building|beach|mountain/i },
    { slug: 'soi-mau-trang-tri-500g', q: 'colored decorative pebbles', alt: ['coloured gravel stones', 'aquarium gravel colorful'],
        must: /pebble|gravel|stone/i, block: /wall|building|beach/i },
    { slug: 'reu-kho-phu-goc-100g', q: 'sphagnum moss dried', alt: ['moss plant pot', 'green moss'],
        must: /moss|sphagnum/i },
    { slug: 'co-nhung-nhat-mini-phu-goc', q: 'moss ground cover plant', alt: ['dwarf grass ground cover', 'green ground cover plant'],
        must: /moss|ground ?cover|grass|lawn/i },
    { slug: 'tuong-gom-mini-trang-tri-chau', q: 'garden gnome miniature', alt: ['miniature garden figurine', 'fairy garden decoration'],
        must: /figurine|gnome|miniature|ornament|statue/i },

    /* ============================================================
     * PHỤ KIỆN TRANG TRÍ — nhóm hàng mới
     * ============================================================
     * TITLE_BLOCK chặn "plastic" và "artificial" vì chúng dùng để loại
     * CÂY GIẢ. Nhưng quả cầu Giáng sinh thì đúng là đồ nhựa, và đó
     * không phải khuyết điểm — nên nhóm này phải khai `must` riêng đủ
     * hẹp để không cần tới TITLE_BLOCK nữa.
     */
    { slug: 'bo-6-qua-cau-giang-sinh-treo-cay', q: 'christmas baubles ornaments', alt: ['christmas tree ornament balls', 'christmas decoration balls'],
        must: /bauble|ornament|christmas ball/i },
    { slug: 'no-ruy-bang-do-trang-tri-chau', q: 'red ribbon bow', alt: ['gift ribbon bow', 'decorative ribbon'],
        must: /ribbon|bow/i, block: /bow ?tie|archer|violin|rainbow/i },
    { slug: 'day-den-led-mini-quan-cay', q: 'fairy lights string', alt: ['string lights warm', 'led fairy lights'],
        must: /fairy light|string light|led light/i },
    { slug: 'bao-li-xi-mini-treo-cay-ngay-tet', q: 'lunar new year red envelope', alt: ['red envelope decoration', 'tet decoration'],
        must: /red envelope|lucky money|lunar new year|tet|hongbao/i },
    { slug: 'thiep-chuc-mung-kem-kep-cam', q: 'greeting card blank', alt: ['gift card note', 'message card flowers'],
        must: /card|note|tag/i, block: /credit card|playing card|postcard stamp/i },

    /* Món cũ chưa bao giờ tải được ảnh. */
    /*
     * Ba câu truy vấn đầu đều trả về 0: Openverse nối từ bằng AND nên
     * 'expanded clay pebbles' đòi cả ba chữ cùng có trong tiêu đề. Rút
     * xuống một chữ mới ra kết quả — cùng bài học đã ghi ở đầu tệp.
     */
    { slug: 'vien-dat-nung-lot-day-chau-1kg', q: 'leca', alt: ['clay pebbles', 'hydroponics', 'clay granules'],
        must: /clay|leca|hydroponic|granule|pebble/i },
];
