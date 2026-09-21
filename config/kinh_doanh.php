<?php

/*
 * THAM SỐ KINH DOANH — giá trị MẶC ĐỊNH.
 * Admin đổi được ở Cài đặt › Tham số kinh doanh (App\Services\Shop\ThamSoKinhDoanh);
 * giá trị admin lưu đè lên đây, bỏ trống thì quay về đây.
 */

return [

    /* Tồn kho còn từ 1 đến mức này thì trang sản phẩm hiện "Chỉ còn X". */
    'nguong_chi_con' => 5,

    /* Khu quản trị: tồn từ 1 đến mức này là "sắp hết hàng". */
    'sap_het_hang' => 5,

    /* Trang chủ nhắc ngày lễ tặng hoa trong bao nhiêu ngày tới. */
    'nhac_dip_truoc_ngay' => 30,

    /* Mỗi món trong giỏ được đặt tối đa bao nhiêu cái. */
    'gio_toi_da_moi_mon' => 99,

    /* Đổi hàng được trong bao nhiêu ngày kể từ khi giao xong. */
    'han_doi_ngay' => 7,

    'diem' => [
        /* Chi bao nhiêu đồng thì được 1 điểm. */
        'dong_moi_diem_tich' => 10000,

        /* 1 điểm trừ được bao nhiêu đồng khi thanh toán. */
        'dong_moi_diem_dung' => 100,

        /* Dùng ít nhất bao nhiêu điểm mỗi đơn. */
        'dung_toi_thieu' => 100,

        /* Điểm trừ tối đa bao nhiêu % tiền hàng. */
        'phan_tram_toi_da' => 30,

        /* Điểm thưởng cho đánh giá có nhận xét đủ dài / chỉ chấm sao. */
        'danh_gia_nhan_xet' => 10,
        'danh_gia_chi_sao' => 3,
    ],

    'cham_ho' => [
        /* Phí khi khách cần nhận cây gấp (báo trước ít hơn bao_gap_ngay ngày). 0 = không thu. */
        'phi_gap' => 0,
        'bao_gap_ngay' => 2,

        /* Trước hạn trả bao nhiêu ngày thì phiếu chuyển sang "Sắp trả cây" và nhắc khách. */
        'nhac_truoc_ngay' => 7,

        /* Khách khai giá trị cây từ mức này thì bắt buộc báo giá riêng (0 = không xét). */
        'gia_tri_cao_tu' => 5000000,

        /* Báo giá có hiệu lực bao nhiêu ngày. */
        'bao_gia_hieu_luc_ngay' => 3,
    ],

];
