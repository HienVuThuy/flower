<?php

namespace App\Services\Auth;

/**
 * Tài khoản này chưa được phép xoá, và thông điệp nói rõ vì sao.
 *
 * Tách riêng khỏi các ngoại lệ khác để controller bắt đúng loại: một lỗi
 * cơ sở dữ liệu và một lời từ chối có lý do là hai chuyện khác nhau, và
 * chỉ loại thứ hai mới được hiện nguyên văn cho người dùng đọc.
 */
class AccountDeletionException extends \RuntimeException
{
}
