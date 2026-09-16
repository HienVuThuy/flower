@extends('layouts.app')

@section('title', 'Nguồn ảnh')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Nguồn ảnh']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Ghi công</span>
                <h1 class="text-h2 section-header__title">Nguồn ảnh sử dụng trên website</h1>
                <p class="mb-0">
                    Website này dùng ảnh do người khác chụp, chia sẻ theo giấy phép
                    Creative Commons. Những giấy phép đó cho phép dùng và sửa đổi,
                    nhưng bắt buộc ghi tên tác giả — trang này là phần ghi công đó.
                </p>
            </div>
        </div>

        @if($total === 0)

            <div class="surface-card empty-state">
                <p class="empty-state__title">Chưa có ảnh nào cần ghi công.</p>
                <p class="mb-0">
                    Khi cửa hàng dùng ảnh có giấy phép yêu cầu ghi công, danh sách sẽ hiện ở đây.
                </p>
            </div>

        @else

            <p class="text-caption mb-4">
                Tổng cộng <strong>{{ $total }}</strong> ảnh.
                Bấm vào tên ảnh để xem bản gốc, bấm vào giấy phép để đọc điều khoản.
            </p>

            @foreach($groups as $group)
                <div class="surface-card p-4 mb-4">

                    <h2 class="text-h4 mb-3">{{ $group['label'] }}</h2>

                    <div class="table-responsive">
                        <table class="credits-table">
                            <thead>
                                <tr>
                                    <th>Ảnh</th>
                                    <th>Tác giả</th>
                                    <th>Giấy phép</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['items'] as $item)
                                    <tr>
                                        <td>
                                            @if($item['pageUrl'])
                                                <a href="{{ $item['pageUrl'] }}" target="_blank" rel="noopener nofollow">
                                                    {{ $item['title'] }}
                                                </a>
                                            @else
                                                {{ $item['title'] }}
                                            @endif

                                            @if($item['used'])
                                                <span class="credits-table__use">{{ $item['used'] }}</span>
                                            @endif
                                        </td>

                                        <td>{{ $item['author'] }}</td>

                                        <td class="text-nowrap">
                                            @if($item['licenseUrl'])
                                                <a href="{{ $item['licenseUrl'] }}" target="_blank" rel="noopener nofollow">
                                                    {{ $item['license'] }}
                                                </a>
                                            @else
                                                {{ $item['license'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            @endforeach

            <p class="text-caption mb-0">
                Biểu tượng giao diện lấy từ Bootstrap Icons (giấy phép MIT) và
                game-icons.net (CC BY 3.0). Chi tiết đầy đủ nằm trong tệp
                <code>ASSETS.md</code> của mã nguồn.
            </p>

        @endif

    </div>
</section>

@endsection
