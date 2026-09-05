<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Ghi nhật ký thao tác quản trị.
 * ============================================================
 * NƠI DUY NHẤT được ghi vào bảng activity_logs, và không có hàm nào để
 * sửa hay xoá. Đó là chủ ý: một nhật ký mà người bị ghi xoá được thì
 * không dùng để truy trách nhiệm, mà truy trách nhiệm là toàn bộ lý do
 * nó tồn tại.
 *
 * GHI TƯỜNG MINH TẠI CHỖ GỌI, KHÔNG DÙNG MODEL OBSERVER.
 *
 * Observer bắt được mọi lần save() nên nghe thì tiện hơn, nhưng nó chỉ
 * biết "bản ghi X vừa đổi", không biết VIỆC gì vừa xảy ra. Cùng một
 * lệnh save() lên Order có thể là xác nhận đơn, là huỷ đơn, hay là sửa
 * ghi chú — ba việc mà người đọc nhật ký cần phân biệt. Observer còn
 * ghi cả những lần ghi do hệ thống tự làm (đặt hàng, dựng dữ liệu mẫu),
 * làm loãng nhật ký tới mức không ai đọc nữa.
 *
 * KHÔNG BAO GIỜ LÀM HỎNG THAO TÁC CHÍNH. Ghi nhật ký hỏng thì nuốt lỗi
 * và ghi ra log hệ thống: admin vừa huỷ một đơn thành công, không được
 * để họ thấy trang lỗi và bấm huỷ lần nữa chỉ vì một dòng nhật ký.
 */
class ActivityLogger
{
    /**
     * @param  string  $action  mã việc dạng `đối-tượng.việc`
     * @param  string  $description  câu mô tả cho người đọc, viết sẵn
     * @param  Model|null  $subject  bản ghi bị tác động, nếu có
     * @param  array<string, mixed>  $properties  chi tiết trước/sau
     */
    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        try {
            $nguoiLam = Auth::user();

            ActivityLog::create([
                'user_id' => $nguoiLam?->id,
                // Chụp tên ngay lúc ghi: xoá tài khoản thì user_id thành
                // NULL và dòng nhật ký mất hết ý nghĩa nếu không có tên.
                'actor_name' => $nguoiLam?->name,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description, 0, 500),
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Không ghi được nhật ký thao tác.', [
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ghi một lần đổi giá trị, kèm giá trị trước và sau.
     *
     * Tách riêng vì đây là dạng hay dùng nhất, và vì phần "trước → sau"
     * là thứ người đọc nhật ký cần nhất. Để mỗi nơi gọi tự ghép câu thì
     * mỗi nơi ghép một kiểu, và nhật ký đọc như do năm người viết.
     */
    public function logChange(
        string $action,
        Model $subject,
        string $doiTuong,
        string $truoc,
        string $sau,
        array $properties = [],
    ): void {
        $this->log(
            $action,
            sprintf('%s: %s → %s', $doiTuong, $truoc, $sau),
            $subject,
            array_merge(['truoc' => $truoc, 'sau' => $sau], $properties),
        );
    }
}
