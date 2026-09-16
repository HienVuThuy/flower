@extends('shop.pages._layout')

{{-- NỘI DUNG TRANG — bản viết sẵn hoặc bản cửa hàng đã sửa, cùng một định dạng. --}}
@section('page')
    {{ app(\App\Services\Content\TrangNoiDung::class)->html($noiDung) }}
@endsection
