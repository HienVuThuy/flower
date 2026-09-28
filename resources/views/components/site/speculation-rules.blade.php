{{-- TẢI TRƯỚC TRANG KHÁCH SẮP BẤM — Speculation Rules API. --}}
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

                {{-- Các đường dẫn MỞ LƯỢT THANH TOÁN: tải trước là tự tạo giao dịch ở cổng. --}}
                { "not": { "href_matches": "/thanh-toan/momo/*" } },
                { "not": { "href_matches": "/don-hang/*/thanh-toan-momo" } },
                { "not": { "href_matches": "/don-hang/*/tra-gop-momo" } },

                { "not": { "selector_matches": "[rel~=nofollow]" } }
            ]
        },
        "eagerness": "moderate"
    }]
}
</script>
