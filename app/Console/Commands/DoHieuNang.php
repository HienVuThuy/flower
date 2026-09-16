<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Đo xem trang nào chậm, và chậm vì cái gì. */
class DoHieuNang extends Command
{
    protected $signature = 'do:hieu-nang
                            {--lan=3 : Số lần chạy mỗi trang, lấy trung vị}
                            {--chi-tiet : In ra các truy vấn chậm nhất}';

    protected $description = 'Đo thời gian dựng trang và số truy vấn của các trang chính';

    public function handle(): int
    {
        $this->chanDoanMoiTruong();

        $lan = max(1, (int) $this->option('lan'));
        $trang = $this->duongDan();

        $this->info('Đo '.count($trang).' trang, mỗi trang '.$lan.' lần (lấy trung vị).');
        $this->newLine();

        $bang = [];
        $chamNhat = [];

        foreach ($trang as $ten => $url) {
            $ketQua = [];
            $truyVan = [];

            for ($i = 0; $i < $lan; $i++) {
                $do = $this->doMotTrang($url);

                if ($do === null) {
                    continue;
                }

                $ketQua[] = $do;
                $truyVan = $do['truy_van'];
            }

            if ($ketQua === []) {
                $bang[] = [$ten, '—', '—', '—', '—', 'lỗi'];

                continue;
            }

            $thoiGian = $this->trungVi(array_column($ketQua, 'ms'));
            $msSql = $this->trungVi(array_column($ketQua, 'ms_sql'));
            $soTruyVan = $ketQua[0]['so_truy_van'];
            $kb = round($ketQua[0]['bytes'] / 1024);

            $bang[] = [
                $ten,
                number_format($thoiGian, 0).' ms',
                number_format($msSql, 0).' ms',
                $soTruyVan,
                $kb.' KB',
                $ketQua[0]['ma'],
            ];

            foreach ($truyVan as $tv) {
                $chamNhat[] = ['trang' => $ten] + $tv;
            }
        }

        $this->table(
            ['Trang', 'Tổng', 'Trong đó SQL', 'Số truy vấn', 'HTML', 'Mã'],
            $bang,
        );

        if ($this->option('chi-tiet')) {
            $this->inTruyVanCham($chamNhat);
        }

        $this->newLine();
        $this->line('Cột "Tổng" KHÔNG gồm thời gian tải ảnh/CSS/JS — xem phần Network của trình duyệt.');

        return self::SUCCESS;
    }

    private function duongDan(): array
    {
        $sp = Product::query()->where('status', 'active')->first();
        $spCoQuyCach = Product::whereHas('variants', fn ($q) => $q->where('is_active', true))->first();
        $dm = Category::query()->first();
        $don = Order::query()->first();

        return array_filter([
            'Trang chủ' => '/',
            'Danh sách sản phẩm' => '/san-pham',
            'Danh sách + lọc' => '/san-pham?sort=price_asc&danh_muc='.($dm?->slug ?? ''),
            'Chi tiết sản phẩm' => $sp ? '/san-pham/'.$sp->slug : null,
            'Chi tiết (có quy cách)' => $spCoQuyCach ? '/san-pham/'.$spCoQuyCach->slug : null,
            'Danh mục' => $dm ? '/danh-muc/'.$dm->slug : null,
            'Chọn cây (tư vấn)' => '/chon-cay',
            'Giỏ hàng' => '/gio-hang',
            'Admin — Tổng quan' => '/admin/dashboard',
            'Admin — Sản phẩm' => '/admin/products',
            'Admin — Đơn hàng' => '/admin/orders',
            'Admin — Phân tích' => '/admin/phan-tich',
            'Admin — Chi tiết đơn' => $don ? '/admin/orders/'.$don->getRouteKey() : null,
        ]);
    }

    private function doMotTrang(string $url): ?array
    {
        $truyVan = [];

        DB::flushQueryLog();
        DB::listen(function ($q) use (&$truyVan) {
            $truyVan[] = ['sql' => $q->sql, 'ms' => $q->time];
        });

        $admin = User::where('role', UserRole::Admin->value)->first();

        if ($admin) {
            Auth::login($admin);
        }

        $batDau = microtime(true);

        try {
            $res = app()->handle(Request::create($url, 'GET'));
        } catch (\Throwable $e) {
            $this->warn('  '.$url.' → '.mb_substr($e->getMessage(), 0, 80));

            return null;
        }

        $ms = (microtime(true) - $batDau) * 1000;

        usort($truyVan, fn ($a, $b) => $b['ms'] <=> $a['ms']);

        return [
            'ms' => $ms,
            'ms_sql' => array_sum(array_column($truyVan, 'ms')),
            'so_truy_van' => count($truyVan),
            'bytes' => strlen($res->getContent()),
            'ma' => $res->getStatusCode(),
            'truy_van' => array_slice($truyVan, 0, 3),
        ];
    }

    private function inTruyVanCham(array $ds): void
    {
        usort($ds, fn ($a, $b) => $b['ms'] <=> $a['ms']);

        $this->newLine();
        $this->info('Năm truy vấn chậm nhất:');

        foreach (array_slice($ds, 0, 5) as $tv) {
            $this->newLine();
            $this->line(sprintf('  [%s] %.1f ms', $tv['trang'], $tv['ms']));
            $this->line('  '.mb_substr(preg_replace('/\s+/', ' ', $tv['sql']), 0, 220));
        }
    }


    private function chanDoanMoiTruong(): void
    {
        $muc = [];

        if (! extension_loaded('Zend OPcache')) {
            $muc[] = ['OPcache', 'CHƯA CÀI', 'php.ini: bỏ ; ở dòng zend_extension=opcache'];
        } elseif (! filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            $muc[] = ['OPcache', 'CHƯA BẬT', 'php.ini: opcache.enable=1 — đo được: TTFB 781ms → 138ms'];
        } else {
            $tt = @opcache_get_status(false);

            $muc[] = ['OPcache', 'bật', $tt && ($tt['opcache_enabled'] ?? false)
                ? sprintf('%d tệp trong đệm (tiến trình này)', $tt['opcache_statistics']['num_cached_scripts'] ?? 0)
                : 'bật cho máy chủ web (dòng lệnh cố ý không dùng)'];
        }

        foreach (['config' => 'config:cache', 'routes' => 'route:cache'] as $tep => $lenh) {
            $muc[] = file_exists(base_path("bootstrap/cache/{$tep}.php"))
                ? [ucfirst($tep).' cache', 'có', '—']
                : [ucfirst($tep).' cache', 'chưa', "chạy thật thì: php artisan {$lenh}"];
        }

        $manifest = storage_path('app/public/'.\App\Services\Media\ResponsiveImage::MANIFEST);

        $muc[] = file_exists($manifest)
            ? ['Ảnh WebP', 'đã sinh', sprintf('%d ảnh', count((array) json_decode((string) file_get_contents($manifest), true)))]
            : ['Ảnh WebP', 'CHƯA SINH', 'chạy: php artisan anh:toi-uu'];

        if (config('app.debug')) {
            $muc[] = ['APP_DEBUG', 'true', 'chạy thật phải để false — bật thì mọi truy vấn đều bị ghi lại'];
        }

        $this->table(['Hạng mục', 'Trạng thái', 'Ghi chú'], $muc);
        $this->newLine();
    }
    private function trungVi(array $so): float
    {
        sort($so);
        $n = count($so);

        return $n === 0 ? 0.0 : (float) $so[intdiv($n, 2)];
    }
}
