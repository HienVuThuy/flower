<?php

return [

    /* THEME REGISTRY */

    'active' => env('THEME_ACTIVE', 'default'),

    'themes' => [

        'default' => [
            'label' => 'Botanical',
            'description' => 'Diện mạo quanh năm: xanh sage, kem ấm, không hiệu ứng lễ hội.',
            'swatch' => ['#33513f', '#b5583a'],
            'hero' => [
                ['file' => 'default-1.jpg', 'alt' => 'Bó hoa tươi nhiều màu trong bình'],
                ['file' => 'default-2.jpg', 'alt' => 'Bình hoa cắm kiểu Nhật'],
                ['file' => 'default-3.jpg', 'alt' => 'Cây xanh trong chậu trắng'],
            ],

            'effect' => null,
        ],

        'tet' => [
            'label' => 'Tết Nguyên Đán',
            'description' => 'Đỏ son, vàng ấm, cành mai — cánh hoa rơi rất nhẹ.',
            'swatch' => ['#a91e2c', '#c9a13b'],
            'hero' => [
                ['file' => 'tet-1.jpg', 'alt' => 'Cành hoa anh đào nở rộ'],
                ['file' => 'tet-2.jpg', 'alt' => 'Hoa đào hồng nở đầu xuân'],
                ['file' => 'tet-3.jpg', 'alt' => 'Cận cảnh hoa đào'],
            ],

            'effect' => 'tet',
        ],

        'noel' => [
            'label' => 'Giáng sinh',
            'description' => 'Xanh thông, đỏ mận, cành thông — tuyết rơi nhẹ.',
            'swatch' => ['#2f4a37', '#8a2e2e'],
            'hero' => [
                ['file' => 'noel-1.jpg', 'alt' => 'Hoa trạng nguyên đỏ mùa Giáng sinh'],
                ['file' => 'noel-2.jpg', 'alt' => 'Tháp hoa trạng nguyên đỏ'],
                ['file' => 'noel-3.jpg', 'alt' => 'Vòng nguyệt quế treo cửa'],
            ],

            'effect' => 'noel',
        ],

        'valentine' => [
            'label' => 'Valentine',
            'description' => 'Burgundy, hồng phấn, hoa hồng — cánh hoa bay rất thưa.',
            'swatch' => ['#7a2436', '#b5495f'],
            'hero' => [
                ['file' => 'valentine-1.jpg', 'alt' => 'Hoa hồng đỏ nở'],
                ['file' => 'valentine-2.jpg', 'alt' => 'Hoa hồng đỏ sau mưa'],
                ['file' => 'valentine-3.jpg', 'alt' => 'Hoa hồng trong ánh chiều'],
            ],

            'effect' => 'valentine',
        ],

    ],

];
