<section class="card" id="summary-card" hidden>
    <h1 class="card-title">Ringkasan pesanan</h1>
    <p class="card-hint">Kursi ditahan sementara. Selesaikan sebelum waktunya habis.</p>

    <div class="countdown" id="countdown">
        <span class="countdown-label">Sisa waktu</span>
        <span class="countdown-value" id="countdown-value">05:00</span>
    </div>

    <div class="alert" id="summary-alert" hidden></div>

    <dl class="detail" id="summary-details"></dl>

    <form id="booking-form">
        <div class="field-row">
            <div class="field">
                <label for="passenger_name">Nama penumpang</label>
                <input type="text" id="passenger_name" required>
                <span class="field-error" id="passenger_name-error"></span>
            </div>
            <div class="field">
                <label for="passenger_phone">Nomor telepon</label>
                <input type="text" id="passenger_phone" placeholder="08xxxxxxxxxx" required>
                <span class="field-error" id="passenger_phone-error"></span>
            </div>
        </div>

        {{-- "Kembali" hanya berpindah halaman dan mempertahankan lock;
             "Batalkan" benar-benar melepas kursi. Dipisah supaya perbedaannya
             tidak menimbulkan salah klik. --}}
        <div class="btn-row">
            <button type="submit" class="btn" id="confirm-button">Konfirmasi pesanan</button>
            <button type="button" class="btn btn-ghost" id="back-to-seats">Kembali ke kursi</button>
            <button type="button" class="btn btn-ghost" id="back-to-search-from-summary">Cari jadwal lain</button>
        </div>

        <button type="button" class="btn btn-link" id="cancel-lock-button">Batalkan dan lepas kursi</button>
    </form>
</section>
