@props([
    'bootstrap' => false,
])

{{-- ĐỔI "auto" THÀNH SÁNG HOẶC TỐI TRƯỚC KHUNG HÌNH ĐẦU TIÊN. --}}
<script>
    (function () {
        var el = document.documentElement;

        if (el.dataset.scheme === 'auto') {
            el.dataset.scheme =
                window.matchMedia &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'toi'
                    : 'sang';

            el.dataset.schemeAuto = '1';
        }

        @if($bootstrap)
            el.setAttribute('data-bs-theme', el.dataset.scheme === 'toi' ? 'dark' : 'light');
        @endif
    })();
</script>
