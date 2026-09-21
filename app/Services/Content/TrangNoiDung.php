<?php

namespace App\Services\Content;

use App\Services\Shop\Money;
use App\Services\Shop\StoreProfile;
use Illuminate\Support\HtmlString;

/** Nội dung các trang giới thiệu / chính sách: văn bản → HTML an toàn. */
class TrangNoiDung
{
    public function banVietSan(string $slug): string
    {
        $tep = resource_path('content/trang/' . basename($slug) . '.txt');

        return is_file($tep) ? $this->chuanHoa((string) file_get_contents($tep)) : '';
    }

    public function chuanHoa(string $van): string
    {
        return trim(str_replace("\r\n", "\n", $van));
    }

    public function html(string $van): HtmlString
    {
        return new HtmlString($this->khoi($this->chuanHoa($van), true));
    }

    private function khoi(string $van, bool $coMoDau): string
    {
        $ra = [];
        $daCoMoDau = ! $coMoDau;

        foreach (preg_split('/\n\s*\n/u', $van) as $doan) {
            $dong = array_values(array_filter(array_map('rtrim', explode("\n", trim($doan))), fn ($d) => $d !== ''));

            if ($dong === []) {
                continue;
            }

            $dau = $dong[0];

            if (str_starts_with($dau, '### ') || str_starts_with($dau, '## ')) {
                $cap = str_starts_with($dau, '### ') ? 'h3' : 'h2';
                $ra[] = '<' . $cap . '>' . $this->dong(substr($dau, $cap === 'h3' ? 4 : 3)) . '</' . $cap . '>';

                if (count($dong) > 1) {
                    $ra[] = $this->khoi(implode("\n", array_slice($dong, 1)), false);
                }

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_starts_with($d, '> ') || $d === '>')) {
                $ben = implode("\n", array_map(fn ($d) => ltrim(substr($d, 1)), $dong));
                $ra[] = '<div class="static-page__notice">' . $this->khoi($ben, false) . '</div>';

                continue;
            }

            if (count($dong) === 1 && trim($dau) === '{bang_phi_giao_hang}') {
                $ra[] = $this->bangPhiGiao();

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_starts_with($d, '- '))) {
                $ra[] = '<ul>' . implode('', array_map(fn ($d) => '<li>' . $this->dong(substr($d, 2)) . '</li>', $dong)) . '</ul>';

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => preg_match('/^\d+\.\s/u', $d) === 1)) {
                $ra[] = '<ol>' . implode('', array_map(fn ($d) => '<li>' . $this->dong(preg_replace('/^\d+\.\s+/u', '', $d)) . '</li>', $dong)) . '</ol>';

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_starts_with(ltrim($d), '|'))) {
                $ra[] = $this->bang($dong);

                continue;
            }

            if ($this->moiDong($dong, fn ($d) => str_contains($d, '::'))) {
                $ra[] = '<dl class="static-page__facts">' . implode('', array_map(function ($d) {
                    [$nhan, $noiDung] = array_map('trim', explode('::', $d, 2));

                    return '<div><dt>' . $this->dong($nhan) . '</dt><dd>' . $this->dong($noiDung) . '</dd></div>';
                }, $dong)) . '</dl>';

                continue;
            }

            $lop = $daCoMoDau ? '' : ' class="lead"';
            $daCoMoDau = true;
            $ra[] = '<p' . $lop . '>' . $this->dong(implode(' ', array_map('trim', $dong))) . '</p>';
        }

        return implode("\n", $ra);
    }

    private function moiDong(array $dong, callable $dieuKien): bool
    {
        foreach ($dong as $d) {
            if (! $dieuKien($d)) {
                return false;
            }
        }

        return true;
    }

    private function bang(array $dong): string
    {
        $hang = [];

        foreach ($dong as $d) {
            $o = array_map('trim', explode('|', trim(trim($d), '|')));

            if ($this->moiDong($o, fn ($x) => preg_match('/^:?-{2,}:?$/', $x) === 1)) {
                continue;
            }

            $hang[] = $o;
        }

        if ($hang === []) {
            return '';
        }

        $dau = array_shift($hang);

        return '<table class="static-page__table"><thead><tr>'
            . implode('', array_map(fn ($o) => '<th>' . $this->dong($o) . '</th>', $dau))
            . '</tr></thead><tbody>'
            . implode('', array_map(fn ($h) => '<tr>' . implode('', array_map(fn ($o) => '<td>' . $this->dong($o) . '</td>', $h)) . '</tr>', $hang))
            . '</tbody></table>';
    }

    private function bangPhiGiao(): string
    {
        $dong = ['| Khu vực | Phí giao |'];

        foreach ((array) config('shipping.zones', []) as $khoa => $zone) {
            $phi = \App\Services\Shop\ThamSoKinhDoanh::giaTri("shipping.zones.{$khoa}.fee") ?? 0;
            $dong[] = '| ' . str_replace('|', '/', (string) ($zone['label'] ?? '')) . ' | ' . Money::format((string) $phi) . ' |';
        }

        return $this->bang($dong);
    }

    private function dong(string $chu): string
    {
        $chu = e($chu);

        $chu = strtr($chu, [
            '{ten_cua_hang}' => e(StoreProfile::name()),
            '{hotline}' => e((string) StoreProfile::hotline()),
            '{email}' => e((string) StoreProfile::email()),
            '{dia_chi}' => e((string) StoreProfile::address()),
            '{mien_phi_giao_tu}' => e(Money::format((string) \App\Services\Shop\ThamSoKinhDoanh::giaTri('shipping.free_from'))),
        ]);

        $chu = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) {
            $dich = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('#^(/(?!/)|https?://|mailto:)#i', $dich) !== 1) {
                return $m[0];
            }

            return '<a href="' . e($dich) . '">' . $m[1] . '</a>';
        }, $chu);

        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $chu);
    }
}
