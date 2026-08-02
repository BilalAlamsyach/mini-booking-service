{{--
    Bar pesanan tertunda.

    Muncul di halaman pencarian dan pemilihan kursi selama pengguna masih
    menahan kursi, supaya ia bisa menengok peta kursi lalu kembali melanjutkan
    pesanan tanpa kehilangan lock.
--}}
<div class="pending" id="pending-bar" hidden>
    <div class="pending-main">
        <span class="pending-label">Kursi sedang Anda tahan</span>
        <span class="pending-seats" id="pending-seats"></span>
    </div>
    <div class="pending-side">
        <span class="pending-timer" id="pending-remaining">05:00</span>
        <button type="button" class="btn btn-sm" id="pending-continue">Lanjutkan pesanan</button>
    </div>
</div>
