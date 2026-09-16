<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\InventoryReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trang Tồn kho. */
class InventoryController extends Controller
{
    private const KY = [
        '7' => '7 ngày qua',
        '30' => '30 ngày qua',
        '90' => '90 ngày qua',
    ];

    public function index(Request $request, InventoryReport $report): View
    {
        $ky = (string) $request->query('ky', '30');

        if (! array_key_exists($ky, self::KY)) {
            $ky = '30';
        }

        $report->trongVong((int) $ky);

        $nguong = (float) $request->query('nguong', 14);
        $nguong = max(1, min(90, $nguong));

        return view('admin.inventory.index', [
            'ky' => $ky,
            'cacKy' => self::KY,
            'nguong' => $nguong,

            'tongQuan' => $report->tongQuan(),
            'sapHet' => $report->sapHet($nguong),
            'daHet' => $report->daHet(),
            'chetVon' => $report->chetVon(),
        ]);
    }
}
