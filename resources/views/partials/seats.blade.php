<section class="card" id="seats-card" hidden>
    <div class="card-head">
        <div>
            <h1 class="card-title">Pilih kursi</h1>
            <p class="card-hint" id="seats-subtitle"></p>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" id="back-to-search">Ganti jadwal</button>
    </div>

    <div class="alert" id="seats-alert" hidden></div>

    <ul class="legend">
        <li><span class="swatch seat-available"></span>Tersedia</li>
        <li><span class="swatch seat-selected"></span>Dipilih</li>
        <li><span class="swatch seat-mine"></span>Anda kunci</li>
        <li><span class="swatch seat-locked"></span>Dikunci orang lain</li>
        <li><span class="swatch seat-booked"></span>Terisi</li>
    </ul>

    <div class="deck">
        <div class="deck-front">Depan</div>
        <div class="seat-map" id="seat-map"></div>
    </div>

    <div class="seat-actions">
        <span class="selection" id="selection-summary"></span>
        <div class="btn-row">
            <button type="button" class="btn btn-ghost" id="refresh-seats">Muat ulang</button>
            <button type="button" class="btn" id="lock-button" disabled>Kunci kursi</button>
        </div>
    </div>
</section>
