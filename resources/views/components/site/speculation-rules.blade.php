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
                { "not": { "selector_matches": "[rel~=nofollow]" } }
            ]
        },
        "eagerness": "moderate"
    }]
}
</script>
