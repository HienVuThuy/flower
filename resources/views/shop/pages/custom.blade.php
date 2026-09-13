@extends('shop.pages._layout')

{{--
    NỘI DUNG TRANG — bản viết sẵn hoặc bản cửa hàng đã sửa, cùng một định dạng.

    Dựng HTML ở App\Services\Content\TrangNoiDung: escape toàn bộ trước, rồi
    mới áp vài quy ước (tiêu đề, danh sách, bảng, liên kết an toàn). Không
    có đường nào để HTML gõ trong ô soạn chạy trên trang này.
--}}
@section('page')
    {{ app(\App\Services\Content\TrangNoiDung::class)->html($noiDung) }}
@endsection
