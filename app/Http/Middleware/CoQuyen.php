<?php

namespace App\Http\Middleware;

use App\Enums\Quyen;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn theo KHU VỰC quản trị, không theo vai trò.
 * ============================================================
 * VÌ SAO KHÔNG DÙNG `role:` CHO VIỆC NÀY.
 *
 * `role:admin,staff` viết ra thì giống, nhưng nó khai ở ĐƯỜNG DẪN rằng
 * "ai được vào" — nên thêm một vai trò mới là phải đi sửa hàng chục
 * dòng route, và quên một dòng nghĩa là mở toang hoặc khoá nhầm một khu.
 *
 * `quyen:tai-chinh` khai "khu này là khu gì". Ai vào được thì tra ở
 * UserRole::quyen() — một bảng duy nhất.
 *
 * ============================================================
 * TÊN QUYỀN SAI LÀ LỖI CỦA LẬP TRÌNH VIÊN, PHẢI NỔ.
 *
 * Gõ nhầm `quyen:tai-chinh2` mà middleware lặng lẽ cho qua thì cả khu
 * vực đó mất bảo vệ, và không có gì báo. Nên tên không khớp là ném lỗi
 * ngay — hỏng lúc chạy thử còn hơn mở cửa lúc chạy thật.
 */
class CoQuyen
{
    public function handle(Request $request, Closure $next, string ...$ma): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        foreach ($ma as $m) {
            $quyen = Quyen::tryFrom($m);

            if ($quyen === null) {
                throw new \InvalidArgumentException(
                    'Không có quyền nào tên "' . $m . '". Xem App\Enums\Quyen.',
                );
            }

            /*
             * ĐỦ MỘT LÀ VÀO ĐƯỢC.
             *
             * Vài trang nằm giữa hai khu — ví dụ trang hoàn tiền vừa là
             * việc của người xử lý đơn vừa là việc của người giữ tiền.
             * Đòi đủ cả hai thì người xử lý đơn không mở nổi đơn của
             * chính mình.
             */
            if ($user->duoc($quyen)) {
                return $next($request);
            }
        }

        abort(403, 'Tài khoản của bạn không có quyền vào khu vực này.');
    }
}
