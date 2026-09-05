{{--
    TẢI TRƯỚC TRANG KHÁCH SẮP BẤM — Speculation Rules API.
    ============================================================
    VẤN ĐỀ: đây là ứng dụng nhiều trang (Blade dựng HTML ở máy chủ), nên
    mỗi lần bấm là một lần tải lại cả trang. Đó là kiến trúc, không phải
    lỗi — và đổi sang SPA thì phải viết lại toàn bộ giao diện bằng React
    hoặc Vue, thứ dự án này cố ý không dùng.

    CÁCH KHÁC, KHÔNG ĐỔI KIẾN TRÚC: bảo trình duyệt tải trang tiếp theo
    NGAY KHI khách rê chuột lên liên kết. Người ta mất khoảng 200-300ms
    giữa lúc rê chuột và lúc bấm — đủ để tải xong một trang 250ms. Bấm
    xuống là trang đã nằm sẵn trong bộ nhớ.

    ĐÂY LÀ CHUẨN CỦA TRÌNH DUYỆT, KHÔNG PHẢI THƯ VIỆN:
    không thêm một byte JavaScript nào của mình, không có gì để hỏng.
    Trình duyệt chưa hỗ trợ (Firefox, Safari) thì bỏ qua thẻ này và trang
    chạy y như trước.

    "moderate" chứ không phải "eager": eager tải TRƯỚC MỌI liên kết trên
    trang ngay khi trang hiện ra — với trang danh sách 12 sản phẩm là 12
    lượt tải mà khách chỉ bấm một. Trên máy chủ dev một luồng thì đó là
    tự làm chậm chính mình. "moderate" chỉ tải khi con trỏ dừng trên liên
    kết ~200ms, tức là khi khách đã có ý định.

    prefetch chứ không prerender: prerender chạy luôn cả JavaScript của
    trang đích, nghĩa là mỗi lần rê chuột là một lượt ghi `product_view`
    và `view_count` tăng lên cho một sản phẩm khách chưa hề mở. Số liệu
    phân tích sẽ sai, mà "không bịa dữ liệu" là nguyên tắc của dự án này.
--}}
<script type="speculationrules">
{
    "prefetch": [{
        "source": "document",
        "where": {
            "and": [
                { "href_matches": "/*" },
                { "not": { "href_matches": "/dang-xuat" } },
                { "not": { "href_matches": "/admin/*" } },
                { "not": { "href_matches": "/api/*" } },
                { "not": { "selector_matches": "[rel~=nofollow]" } }
            ]
        },
        "eagerness": "moderate"
    }]
}
</script>
