<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/** Sắp xếp danh sách quản trị theo cột, an toàn với dữ liệu từ URL. */
trait SortsAdminList
{
    protected function applySort(
        Builder $query,
        Request $request,
        array $choPhep,
        callable $macDinh,
    ): void {
        $khoa = (string) $request->query('sap');

        if (! array_key_exists($khoa, $choPhep)) {
            $macDinh($query);

            return;
        }

        $huong = $request->query('huong') === 'giam' ? 'desc' : 'asc';

        $query->orderBy($choPhep[$khoa], $huong);

        $query->orderBy($query->getModel()->getQualifiedKeyName(), $huong);
    }
}
