<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>

    {{-- CSS & JS disajikan sebagai berkas statis dari public/, sehingga
         aplikasi cukup dijalankan dengan `php artisan serve` tanpa build. --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-name">Mini Booking Service</span>
        </a>

        {{-- Diisi dan ditampilkan oleh app.js setelah sesi terverifikasi. --}}
        <div class="topbar-session" id="topbar-session" hidden>
            <span class="topbar-user">Masuk sebagai <strong id="session-name"></strong></span>
            <button type="button" class="btn btn-ghost btn-sm" id="logout-button">Keluar</button>
        </div>
    </div>
</header>

<main class="page">
    @yield('content')
</main>

<footer class="footer">
    Mini Booking Service &middot; Laravel {{ app()->version() }}
</footer>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')

</body>
</html>
