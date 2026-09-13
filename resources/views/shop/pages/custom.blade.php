@extends('shop.pages._layout')

{{--
    NỘI DUNG DO CỬA HÀNG SỬA — văn bản thuần, KHÔNG phải HTML.

    Mọi thứ đi qua {{ }} nên được escape: gõ "<script>" thì khách thấy
    đúng chữ "<script>", không có gì chạy. Xem PageContentController.

    Hai quy ước duy nhất: dòng bắt đầu bằng "## " là tiêu đề; dòng trống
    tách đoạn. Các dòng liền nhau trong một đoạn giữ nguyên xuống dòng.
--}}
@section('page')
    @foreach(preg_split('/\R{2,}/u', trim($noiDung)) as $doan)
        @php $doan = trim($doan); @endphp
        @continue($doan === '')

        @if(str_starts_with($doan, '## '))
            <h2>{{ trim(substr($doan, 3)) }}</h2>
        @else
            <p>{!! nl2br(e($doan)) !!}</p>
        @endif
    @endforeach
@endsection
