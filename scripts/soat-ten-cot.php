<?php

/**
 * Soát mọi tên cột viết tay trong get([...]) / select([...]) / pluck(...)
 * và đối chiếu với lược đồ THẬT của MySQL.
 *
 * Lý do: SQLite (dùng cho kiểm thử) coi định danh trong nháy kép mà không
 * khớp cột nào là một CHUỖI KÝ TỰ, không báo lỗi. Nên gõ sai tên cột đi
 * lọt qua toàn bộ 905 bài kiểm thử và chỉ nổ trên MySQL lúc chạy thật.
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

/** Mọi cột của mọi bảng, gộp thành một tập. */
$cot = [];
$bang = [];

foreach (Schema::getTableListing() as $t) {
    $t = str_contains($t, '.') ? explode('.', $t)[1] : $t;
    $bang[$t] = Schema::getColumnListing($t);

    foreach ($bang[$t] as $c) {
        $cot[$c] = true;
    }
}

echo 'Lược đồ: ', count($bang), ' bảng, ', count($cot), ' tên cột khác nhau', PHP_EOL, PHP_EOL;

/** Những chữ hợp lệ nhưng không phải tên cột. */
$boQua = ['*', 'id'];

$ngo = [];
$soFile = 0;

$duyet = function (string $thuMuc) use (&$duyet, &$ngo, &$soFile, $cot, $boQua) {
    foreach (scandir($thuMuc) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }

        $duong = $thuMuc . '/' . $f;

        if (is_dir($duong)) {
            $duyet($duong);

            continue;
        }

        if (! str_ends_with($f, '.php')) {
            continue;
        }

        $soFile++;
        $noi = file_get_contents($duong);

        // get([...]) / select([...]) / pluck('x') / value('x') / sum('x')
        preg_match_all(
            "/->(?:get|select|pluck|value|sum|avg|max|min|orderBy|orderByDesc|groupBy)\(\s*(\[[^\]]*\]|'[^']*')/",
            $noi,
            $m,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($m[1] as $k => [$doan, $vt]) {
            preg_match_all("/'([a-z_][a-z0-9_]*)'/i", $doan, $ten);

            foreach ($ten[1] as $c) {
                if (isset($cot[$c]) || in_array($c, $boQua, true)) {
                    continue;
                }

                $dong = substr_count(substr($noi, 0, $vt), "\n") + 1;
                $ngo[] = ['file' => $duong, 'dong' => $dong, 'cot' => $c, 'doan' => trim($m[0][$k][0])];
            }
        }
    }
};

$duyet('app');

echo 'Đã soát ', $soFile, ' tệp PHP trong app/', PHP_EOL, PHP_EOL;

if ($ngo === []) {
    echo 'Không tìm thấy tên cột nào lạ.', PHP_EOL;

    return;
}

echo 'CÓ ', count($ngo), ' TÊN KHÔNG KHỚP CỘT NÀO:', PHP_EOL, PHP_EOL;

foreach ($ngo as $n) {
    printf("  %-60s dòng %-5d  '%s'%s", str_replace('\\', '/', $n['file']), $n['dong'], $n['cot'], PHP_EOL);
    echo '      ', mb_substr($n['doan'], 0, 110), PHP_EOL;
}
