<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dữ liệu lập phiếu nhập kho.
 *
 * Phần kiểm tra NẶNG NHẤT không nằm ở đây mà ở controller: chuỗi
 * "id:idQuyCach" phải tra lại trong cơ sở dữ liệu chứ không chỉ kiểm
 * định dạng. Ở đây chỉ chặn những thứ chặn được bằng luật.
 */
class StockReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /*
             * CHỌN TỪ DANH SÁCH, không gõ tay nữa.
             *
             * Ô chữ tự do làm mất đúng thứ đáng giá nhất của sổ thu mua:
             * so sánh. "Vựa Hoa Tươi" và "vựa hoa tuoi" là hai nơi khác
             * nhau với máy, nên "mua ở đâu rẻ hơn" không trả lời được.
             *
             * Vẫn cho để trống: có lần mua lẻ ngoài chợ không thuộc mối
             * nào, và bắt khai một nhà cung cấp giả chỉ để qua được biểu
             * mẫu thì còn tệ hơn.
             */
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'note' => ['nullable', 'string', 'max:500'],

            /*
             * NGÀY NHẬP KHÔNG ĐƯỢC Ở TƯƠNG LAI.
             *
             * Hàng chưa về mà đã ghi ngày mai là làm báo cáo nhập hàng
             * theo tháng sai, và không ai phát hiện. Ngày trong quá khứ
             * thì HỢP LỆ: hàng về thứ Bảy, thứ Hai mới ngồi nhập máy.
             */
            'received_at' => ['required', 'date', 'before_or_equal:today'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.mat_hang' => ['nullable', 'string', 'max:40'],

            /*
             * CHO PHÉP SỐ ÂM — có chủ ý.
             *
             * Phiếu điều chỉnh là cách sửa một lần nhập nhầm: phiếu đã
             * ghi sổ không sửa được, nên phải có đường trừ bớt ra. Chặn
             * số âm thì cách duy nhất còn lại là sửa tay cột tồn kho —
             * đúng thứ tính năng này sinh ra để thay thế.
             */
            'items.*.quantity' => ['nullable', 'integer', 'between:-10000,10000'],

            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'nhà cung cấp',
            'received_at' => 'ngày nhập',
            'items' => 'dòng hàng',
        ];
    }

    public function messages(): array
    {
        return [
            'received_at.before_or_equal' => 'Ngày nhập không thể ở tương lai.',
            'items.required' => 'Phiếu phải có ít nhất một dòng hàng.',
        ];
    }
}
