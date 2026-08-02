<section class="card" id="search-card" hidden>
    <h1 class="card-title">Cari perjalanan</h1>
    <p class="card-hint">Pilih rute dan tanggal keberangkatan.</p>

    <div class="alert" id="search-alert" hidden></div>

    <form id="search-form">
        <div class="field-row">
            <div class="field">
                <label for="route">Rute</label>
                <select id="route" name="route_id"></select>
            </div>
            <div class="field">
                <label for="date">Tanggal</label>
                <input type="date" id="date" name="date" required>
            </div>
        </div>
        <button type="submit" class="btn" id="search-button">Cari</button>
    </form>

    <div id="schedule-results"></div>
</section>
