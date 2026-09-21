@props(['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'canonical' => null])

{{-- Thẻ cho Google và cho link dán lên Zalo / Facebook. Ảnh mặc định là ảnh nền mùa hiện tại. --}}
@php
    $ten = \App\Services\Shop\StoreProfile::name();
    $tieuDe = trim((string) $title) !== '' ? trim($title) . ' - ' . $ten : $ten;
    $moTa = trim((string) $description) !== ''
        ? trim($description)
        : $ten . ' — ' . \App\Services\Shop\StoreProfile::get('site_tagline');
    $anh = trim((string) $image) !== '' ? trim($image) : Vite::asset('resources/images/hero/default-1.jpg');
@endphp

@php $chuan = trim((string) $canonical) !== '' ? trim($canonical) : url()->current(); @endphp
<link rel="canonical" href="{{ $chuan }}">

<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ $ten }}">
<meta property="og:locale" content="vi_VN">
<meta property="og:title" content="{{ $tieuDe }}">
<meta property="og:description" content="{{ $moTa }}">
<meta property="og:url" content="{{ $chuan }}">
<meta property="og:image" content="{{ $anh }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $tieuDe }}">
<meta name="twitter:description" content="{{ $moTa }}">
<meta name="twitter:image" content="{{ $anh }}">
