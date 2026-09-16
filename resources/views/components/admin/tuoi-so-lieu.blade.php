{{-- Số liệu trên trang này tính lúc nào, và làm mới nó. --}}
<div class="tuoi-so-lieu" data-tuoi-so-lieu>

    <span class="tuoi-so-lieu__moc">
        Số liệu lúc
        <x-site.time :at="now()" format="H:i" />
        <span class="tuoi-so-lieu__truoc" data-tuoi-truoc hidden></span>
    </span>

    <a data-admin-link href="{{ request()->fullUrl() }}" class="btn btn-sm btn-outline-admin">Làm mới</a>

    <label class="tuoi-so-lieu__tu-dong">
        <input type="checkbox" class="form-check-input" data-tuoi-tu-dong>
        <span>Tự làm mới</span>
    </label>

</div>
