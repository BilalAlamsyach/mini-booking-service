/* =========================================================================
   Mini Booking Service — frontend
   Alur: login → cari jadwal → pilih kursi → ringkasan → konfirmasi → hasil.
   JavaScript biasa, tanpa framework maupun build step.
   ========================================================================= */

(function () {
    'use strict';

    var API = '/api';
    var STORAGE_TOKEN = 'mbs_token';
    var STORAGE_USER = 'mbs_user';

    var VIEW_LOGIN = 'login-card';
    var VIEW_SEARCH = 'search-card';
    var VIEW_SEATS = 'seats-card';
    var VIEW_SUMMARY = 'summary-card';
    var VIEW_RESULT = 'result-card';

    var VIEWS = [VIEW_LOGIN, VIEW_SEARCH, VIEW_SEATS, VIEW_SUMMARY, VIEW_RESULT];

    var state = {
        user: null,
        view: VIEW_LOGIN,
        schedule: null,       // jadwal yang sedang dilihat
        seats: [],
        selected: [],
        lock: null,           // lock aktif dari POST /seat-locks
        lockSchedule: null,   // jadwal milik lock — bisa beda dari `schedule`
                              // kalau pengguna menengok jadwal lain
        lockExpired: false,
        countdownTimer: null,
        maxSeats: 6,
    };

    var el = {};

    [
        'topbar-session', 'session-name', 'logout-button',
        'pending-bar', 'pending-seats', 'pending-remaining', 'pending-continue',
        'login-card', 'login-form', 'login-alert', 'login-button', 'email', 'password',
        'email-error', 'password-error',
        'search-card', 'search-form', 'search-alert', 'search-button', 'route', 'date',
        'schedule-results',
        'seats-card', 'seats-alert', 'seats-subtitle', 'seat-map', 'selection-summary',
        'lock-button', 'refresh-seats', 'back-to-search',
        'summary-card', 'summary-alert', 'summary-details', 'booking-form', 'countdown',
        'countdown-value', 'confirm-button', 'cancel-lock-button', 'back-to-seats',
        'back-to-search-from-summary',
        'passenger_name', 'passenger_phone', 'passenger_name-error', 'passenger_phone-error',
        'result-card', 'result-icon', 'result-title', 'result-message', 'result-details',
        'result-restart',
    ].forEach(function (id) {
        el[id] = document.getElementById(id);
    });

    /* ------------------------------------------------------------- helpers */

    var rupiah = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    var tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    function formatTanggal(iso) {
        var parts = iso.split('-');
        return tanggalPanjang.format(new Date(parts[0], parts[1] - 1, parts[2]));
    }

    function getToken() {
        return localStorage.getItem(STORAGE_TOKEN);
    }

    function saveSession(token, user) {
        localStorage.setItem(STORAGE_TOKEN, token);
        localStorage.setItem(STORAGE_USER, JSON.stringify(user));
        state.user = user;
    }

    function clearSession() {
        localStorage.removeItem(STORAGE_TOKEN);
        localStorage.removeItem(STORAGE_USER);
        state.user = null;
    }

    function showAlert(node, type, message) {
        node.className = 'alert alert-' + type;
        node.textContent = message;
        node.hidden = false;
    }

    function hideAlert(node) {
        node.hidden = true;
        node.textContent = '';
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function mmss(seconds) {
        return pad(Math.floor(seconds / 60)) + ':' + pad(seconds % 60);
    }

    function row(label, value, modifier) {
        return '<dt>' + label + '</dt>' +
            '<dd' + (modifier ? ' class="' + modifier + '"' : '') + '>' + value + '</dd>';
    }

    function seatNumbersOfLock() {
        return state.lock.seats.map(function (seat) { return seat.seat_number; }).join(', ');
    }

    /* ------------------------------------------------------------- api call */

    /**
     * Pembungkus fetch: menyisipkan header Authorization bila ada token dan
     * menormalkan hasilnya menjadi { ok, status, data }.
     */
    async function api(method, path, body) {
        var headers = { Accept: 'application/json' };

        if (body) {
            headers['Content-Type'] = 'application/json';
        }

        var token = getToken();
        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        var response;
        try {
            response = await fetch(API + path, {
                method: method,
                headers: headers,
                body: body ? JSON.stringify(body) : undefined,
            });
        } catch (networkError) {
            return { ok: false, status: 0, data: { message: 'Tidak dapat terhubung ke server.' } };
        }

        var text = await response.text();
        var data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            data = { message: 'Terjadi kesalahan pada server.' };
        }

        return { ok: response.ok, status: response.status, data: data };
    }

    function handleUnauthenticated() {
        clearSession();
        resetFlow();
        showView(VIEW_LOGIN);
        showAlert(el['login-alert'], 'error', 'Sesi Anda sudah berakhir. Silakan masuk kembali.');
    }

    /* ----------------------------------------------------------- navigation */

    function showView(view) {
        state.view = view;

        VIEWS.forEach(function (name) {
            el[name].hidden = name !== view;
        });

        var loggedIn = Boolean(state.user) && view !== VIEW_LOGIN;
        el['topbar-session'].hidden = !loggedIn;

        if (state.user) {
            el['session-name'].textContent = state.user.name;
        }

        updatePendingBar();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Bar pesanan tertunda hanya relevan saat pengguna sedang menjelajah
     * (cari jadwal / pilih kursi) sambil masih menahan kursi.
     */
    function updatePendingBar() {
        var relevant = Boolean(state.lock) &&
            !state.lockExpired &&
            (state.view === VIEW_SEARCH || state.view === VIEW_SEATS);

        el['pending-bar'].hidden = !relevant;

        if (relevant) {
            el['pending-seats'].textContent =
                'Kursi ' + seatNumbersOfLock() + ' · ' +
                state.lockSchedule.route.origin + ' ke ' + state.lockSchedule.route.destination +
                ' · ' + state.lockSchedule.departure_time;
        }
    }

    function clearLock() {
        stopCountdown();
        state.lock = null;
        state.lockSchedule = null;
        state.lockExpired = false;
        updatePendingBar();
    }

    /** Reset penuh — dipakai saat logout dan setelah alur pemesanan selesai. */
    function resetFlow() {
        clearLock();
        state.schedule = null;
        state.seats = [];
        state.selected = [];
    }

    /* ---------------------------------------------------------------- LOGIN */

    el['login-form'].addEventListener('submit', async function (event) {
        event.preventDefault();
        hideAlert(el['login-alert']);
        el['email-error'].textContent = '';
        el['password-error'].textContent = '';

        el['login-button'].disabled = true;
        el['login-button'].textContent = 'Memproses…';

        var result = await api('POST', '/auth/login', {
            email: el.email.value,
            password: el.password.value,
            device_name: 'web',
        });

        el['login-button'].disabled = false;
        el['login-button'].textContent = 'Masuk';

        if (result.ok) {
            saveSession(result.data.token, result.data.user);
            el.password.value = '';
            el.passenger_name.value = result.data.user.name;
            resetFlow();
            await enterSearch();
            return;
        }

        if (result.status === 422 && result.data.errors) {
            if (result.data.errors.email) el['email-error'].textContent = result.data.errors.email[0];
            if (result.data.errors.password) el['password-error'].textContent = result.data.errors.password[0];
            return;
        }

        if (result.status === 429) {
            showAlert(el['login-alert'], 'warning', 'Terlalu banyak percobaan. Coba lagi sebentar lagi.');
            return;
        }

        showAlert(el['login-alert'], 'error', result.data.message || 'Gagal masuk.');
    });

    el['logout-button'].addEventListener('click', async function () {
        el['logout-button'].disabled = true;
        await api('POST', '/auth/logout');
        el['logout-button'].disabled = false;

        clearSession();
        resetFlow();
        showView(VIEW_LOGIN);
        showAlert(el['login-alert'], 'success', 'Anda telah keluar.');
    });

    document.querySelectorAll('[data-fill-email]').forEach(function (button) {
        button.addEventListener('click', function () {
            el.email.value = button.dataset.fillEmail;
            el.password.value = button.dataset.fillPassword;
            el.password.focus();
        });
    });

    /* --------------------------------------------------------- CARI JADWAL */

    /**
     * Berpindah ke pencarian tanpa menyentuh lock yang sedang aktif, supaya
     * pengguna bisa menengok jadwal lain lalu kembali melanjutkan pesanan.
     */
    async function enterSearch() {
        hideAlert(el['search-alert']);
        el['schedule-results'].innerHTML = '';
        state.schedule = null;
        state.seats = [];
        state.selected = [];
        showView(VIEW_SEARCH);

        if (el.route.options.length === 0) {
            await loadRoutes();
        }

        if (!el.date.value) {
            var besok = new Date();
            besok.setDate(besok.getDate() + 1);
            el.date.value = besok.getFullYear() + '-' +
                pad(besok.getMonth() + 1) + '-' + pad(besok.getDate());
        }
    }

    async function loadRoutes() {
        var result = await api('GET', '/routes');

        if (!result.ok) {
            showAlert(el['search-alert'], 'error', 'Gagal memuat daftar rute.');
            return;
        }

        el.route.innerHTML = '<option value="">Semua rute</option>';

        result.data.data.forEach(function (route) {
            var option = document.createElement('option');
            option.value = route.id;
            option.textContent = route.label;
            el.route.appendChild(option);
        });
    }

    el['search-form'].addEventListener('submit', async function (event) {
        event.preventDefault();
        hideAlert(el['search-alert']);

        el['search-button'].disabled = true;
        el['search-button'].textContent = 'Mencari…';

        var query = 'date=' + encodeURIComponent(el.date.value);
        if (el.route.value) {
            query += '&route_id=' + encodeURIComponent(el.route.value);
        }

        var result = await api('GET', '/schedules?' + query);

        el['search-button'].disabled = false;
        el['search-button'].textContent = 'Cari';

        if (!result.ok) {
            var message = result.status === 422 && result.data.errors && result.data.errors.date
                ? result.data.errors.date[0]
                : (result.data.message || 'Pencarian gagal.');
            showAlert(el['search-alert'], 'error', message);
            return;
        }

        renderSchedules(result.data.data);
    });

    function renderSchedules(schedules) {
        el['schedule-results'].innerHTML = '';

        if (schedules.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.textContent = 'Tidak ada keberangkatan pada tanggal ini.';
            el['schedule-results'].appendChild(empty);
            return;
        }

        var list = document.createElement('div');
        list.className = 'schedule-list';

        schedules.forEach(function (schedule) {
            var full = schedule.available_seats === 0;

            var item = document.createElement('div');
            item.className = 'schedule' + (full ? ' is-full' : '');

            var info = document.createElement('div');
            info.innerHTML =
                '<div class="schedule-time">' + schedule.departure_time + ' – ' + schedule.arrival_time + '</div>' +
                '<div class="schedule-route">' + schedule.route.origin + ' ke ' + schedule.route.destination + '</div>' +
                '<div class="schedule-meta">' + schedule.route.operator.name +
                ' · ' + schedule.vehicle_code +
                ' · ' + schedule.available_seats + ' dari ' + schedule.total_seats + ' kursi tersedia</div>';

            var side = document.createElement('div');
            side.className = 'schedule-side';
            side.innerHTML = '<span class="schedule-price">' + rupiah.format(schedule.price) + '</span>';

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm';
            button.textContent = full ? 'Penuh' : 'Pilih';
            button.disabled = full;
            button.addEventListener('click', function () {
                openSeatMap(schedule);
            });

            side.appendChild(button);
            item.appendChild(info);
            item.appendChild(side);
            list.appendChild(item);
        });

        el['schedule-results'].appendChild(list);
    }

    /* ---------------------------------------------------------- PILIH KURSI */

    async function openSeatMap(schedule) {
        state.schedule = schedule;
        state.selected = [];
        hideAlert(el['seats-alert']);

        el['seats-subtitle'].textContent =
            schedule.route.origin + ' ke ' + schedule.route.destination +
            ' · ' + formatTanggal(schedule.departure_date) +
            ' · ' + schedule.departure_time;

        showView(VIEW_SEATS);
        await loadSeats();
    }

    async function loadSeats() {
        var result = await api('GET', '/schedules/' + state.schedule.id + '/seats');

        if (!result.ok) {
            showAlert(el['seats-alert'], 'error', result.data.message || 'Gagal memuat kursi.');
            return;
        }

        state.seats = result.data.data.seats;

        // Kursi yang sempat dipilih tapi kini sudah tidak tersedia dilepas.
        state.selected = state.selected.filter(function (id) {
            return state.seats.some(function (seat) {
                return seat.id === id && seat.status === 'available';
            });
        });

        renderSeatMap();
    }

    function renderSeatMap() {
        el['seat-map'].innerHTML = '';

        state.seats.forEach(function (seat) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'seat';
            button.innerHTML = seat.seat_number + '<small>' + seat.seat_class + '</small>';

            if (seat.status === 'booked') {
                button.classList.add('is-booked');
                button.disabled = true;
                button.title = 'Kursi sudah terisi';
            } else if (seat.status === 'locked') {
                button.classList.add('is-locked');
                button.disabled = true;
                button.title = 'Sedang dipilih penumpang lain sampai ' +
                    new Date(seat.locked_until).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            } else if (seat.status === 'locked_by_you') {
                button.classList.add('is-mine');
                button.disabled = true;
                button.title = 'Anda sedang menahan kursi ini';
            } else if (state.selected.indexOf(seat.id) !== -1) {
                button.classList.add('is-selected');
            }

            button.addEventListener('click', function () {
                toggleSeat(seat);
            });

            el['seat-map'].appendChild(button);
        });

        updateSelectionSummary();
    }

    function toggleSeat(seat) {
        var index = state.selected.indexOf(seat.id);

        if (index === -1) {
            if (state.selected.length >= state.maxSeats) {
                showAlert(el['seats-alert'], 'warning', 'Maksimum ' + state.maxSeats + ' kursi per pemesanan.');
                return;
            }
            state.selected.push(seat.id);
        } else {
            state.selected.splice(index, 1);
        }

        hideAlert(el['seats-alert']);
        renderSeatMap();
    }

    function updateSelectionSummary() {
        var count = state.selected.length;

        if (count === 0) {
            el['selection-summary'].textContent = 'Belum ada kursi dipilih.';
            el['lock-button'].disabled = true;
            el['lock-button'].textContent = 'Kunci kursi';
            return;
        }

        var numbers = state.seats
            .filter(function (seat) { return state.selected.indexOf(seat.id) !== -1; })
            .map(function (seat) { return seat.seat_number; });

        el['selection-summary'].innerHTML =
            '<strong>' + numbers.join(', ') + '</strong> · ' +
            rupiah.format(state.schedule.price * count);

        el['lock-button'].disabled = false;
        el['lock-button'].textContent = 'Lanjut · ' + count + ' kursi';
    }

    el['refresh-seats'].addEventListener('click', loadSeats);
    el['back-to-search'].addEventListener('click', enterSearch);

    el['lock-button'].addEventListener('click', async function () {
        hideAlert(el['seats-alert']);
        el['lock-button'].disabled = true;
        el['lock-button'].textContent = 'Menahan kursi…';

        var result = await api('POST', '/seat-locks', {
            schedule_id: state.schedule.id,
            seat_ids: state.selected,
        });

        if (result.ok) {
            state.lock = result.data.data;
            state.lockSchedule = state.schedule;
            state.lockExpired = false;
            openSummary();
            return;
        }

        updateSelectionSummary();

        if (result.status === 401) {
            handleUnauthenticated();
            return;
        }

        if (result.status === 409) {
            showAlert(el['seats-alert'], 'error', result.data.message);
            // Ada yang mendahului — muat ulang agar peta kursi kembali akurat.
            await loadSeats();
            return;
        }

        showAlert(el['seats-alert'], 'error', result.data.message || 'Gagal menahan kursi.');
    });

    /* ------------------------------------------------------------- RINGKASAN */

    function openSummary() {
        hideAlert(el['summary-alert']);
        el['confirm-button'].disabled = false;

        var schedule = state.lockSchedule;
        var total = schedule.price * state.lock.seats.length;

        el['summary-details'].innerHTML =
            row('Operator', schedule.route.operator.name) +
            row('Rute', schedule.route.origin + ' ke ' + schedule.route.destination) +
            row('Tanggal', formatTanggal(schedule.departure_date)) +
            row('Keberangkatan', schedule.departure_time + ' – ' + schedule.arrival_time) +
            row('Armada', schedule.vehicle_code) +
            row('Kursi', seatNumbersOfLock()) +
            row('Harga per kursi', rupiah.format(schedule.price)) +
            row('Total', rupiah.format(total), 'is-total');

        if (!el.passenger_name.value && state.user) {
            el.passenger_name.value = state.user.name;
        }

        showView(VIEW_SUMMARY);
        startCountdown(state.lock.expires_at);
    }

    /**
     * Satu pencacah untuk seluruh aplikasi: tetap berjalan walau pengguna
     * berpindah ke halaman kursi atau pencarian, sehingga sisa waktu di bar
     * pesanan tertunda selalu akurat.
     */
    function startCountdown(expiresAt) {
        stopCountdown();

        var deadline = new Date(expiresAt).getTime();

        function tick() {
            var msLeft = deadline - Date.now();

            // Dibulatkan ke atas: selama masih ada sisa milidetik, tampilan
            // belum boleh 00:00. Dengan pembulatan ke bawah, angka nol muncul
            // hampir satu detik lebih awal daripada kedaluwarsa sebenarnya di
            // server.
            var remaining = Math.max(0, Math.ceil(msLeft / 1000));
            var teks = mmss(remaining);

            el['countdown-value'].textContent = teks;
            el['pending-remaining'].textContent = teks;
            el.countdown.classList.toggle('is-urgent', remaining <= 60);

            if (msLeft <= 0) {
                handleLockExpired();
            }
        }

        tick();
        state.countdownTimer = setInterval(tick, 1000);
    }

    function stopCountdown() {
        if (state.countdownTimer) {
            clearInterval(state.countdownTimer);
            state.countdownTimer = null;
        }
    }

    function handleLockExpired() {
        stopCountdown();
        state.lockExpired = true;
        updatePendingBar();

        if (state.view === VIEW_SUMMARY) {
            // Lock sengaja tidak dibuang: tombol konfirmasi tetap aktif supaya
            // penolakan 410 dari server benar-benar terlihat, bukan
            // disembunyikan oleh klien.
            showAlert(
                el['summary-alert'],
                'error',
                'Waktu habis. Kursi sudah dilepas dan pesanan tidak dapat dilanjutkan.'
            );
            return;
        }

        var view = state.view;
        var pesan = 'Waktu tahan kursi habis. Kursi sudah dilepas dan bisa diambil penumpang lain.';

        clearLock();

        if (view === VIEW_SEATS) {
            showAlert(el['seats-alert'], 'warning', pesan);

            // Jam browser dan jam server bisa berbeda sepersekian detik.
            // Jeda singkat ini mencegah peta kursi dimuat ulang tepat sebelum
            // server menganggap lock kedaluwarsa, yang akan membuat kursi
            // keliru tampil masih tertahan.
            setTimeout(function () {
                if (state.view === VIEW_SEATS && state.schedule) {
                    loadSeats();
                }
            }, 1200);
        } else if (view === VIEW_SEARCH) {
            showAlert(el['search-alert'], 'warning', pesan);
        }
    }

    /* --------------------------- perpindahan yang mempertahankan lock ------ */

    el['pending-continue'].addEventListener('click', function () {
        openSummary();
    });

    el['back-to-seats'].addEventListener('click', async function () {
        if (!state.lockSchedule) {
            await enterSearch();
            return;
        }

        // Kembali ke jadwal milik lock, bukan jadwal terakhir yang dilihat.
        await openSeatMap(state.lockSchedule);
    });

    el['back-to-search-from-summary'].addEventListener('click', enterSearch);

    el['cancel-lock-button'].addEventListener('click', async function () {
        el['cancel-lock-button'].disabled = true;

        var schedule = state.lockSchedule;
        await api('DELETE', '/seat-locks/' + state.lock.lock_token);

        el['cancel-lock-button'].disabled = false;
        clearLock();

        if (schedule) {
            await openSeatMap(schedule);
        } else {
            await enterSearch();
        }
    });

    /* ------------------------------------------------------------ KONFIRMASI */

    el['booking-form'].addEventListener('submit', async function (event) {
        event.preventDefault();
        hideAlert(el['summary-alert']);
        el['passenger_name-error'].textContent = '';
        el['passenger_phone-error'].textContent = '';

        el['confirm-button'].disabled = true;
        el['confirm-button'].textContent = 'Memproses…';

        var result = await api('POST', '/bookings', {
            lock_token: state.lock.lock_token,
            passenger_name: el.passenger_name.value,
            passenger_phone: el.passenger_phone.value,
        });

        el['confirm-button'].disabled = false;
        el['confirm-button'].textContent = 'Konfirmasi pesanan';

        if (result.ok) {
            clearLock();
            showSuccess(result.data.data);
            return;
        }

        if (result.status === 422 && result.data.errors) {
            if (result.data.errors.passenger_name) {
                el['passenger_name-error'].textContent = result.data.errors.passenger_name[0];
            }
            if (result.data.errors.passenger_phone) {
                el['passenger_phone-error'].textContent = result.data.errors.passenger_phone[0];
            }
            return;
        }

        if (result.status === 401) {
            handleUnauthenticated();
            return;
        }

        clearLock();
        showFailure(result.status, result.data);
    });

    /* ----------------------------------------------------------------- HASIL */

    function showSuccess(booking) {
        el['result-icon'].className = 'result-icon is-success';
        el['result-icon'].textContent = '✓';
        el['result-title'].textContent = 'Pesanan dikonfirmasi';
        el['result-message'].textContent = 'Simpan kode pemesanan di bawah ini.';

        el['result-details'].innerHTML =
            row('Kode pemesanan', booking.booking_code, 'is-code') +
            row('Rute', booking.schedule.route.origin + ' ke ' + booking.schedule.route.destination) +
            row('Tanggal', formatTanggal(booking.schedule.departure_date)) +
            row('Keberangkatan', booking.schedule.departure_time) +
            row('Kursi', booking.seats.map(function (s) { return s.seat_number; }).join(', ')) +
            row('Penumpang', booking.passenger_name) +
            row('Telepon', booking.passenger_phone, 'is-plain') +
            row('Total', rupiah.format(booking.total_price), 'is-total');

        el['result-restart'].textContent = 'Pesan perjalanan lain';
        showView(VIEW_RESULT);
    }

    /**
     * Tiap penyebab kegagalan dibedakan agar pengguna tahu langkah berikutnya.
     */
    function showFailure(status, data) {
        var presets = {
            409: {
                icon: '!',
                tone: 'is-error',
                title: 'Kursi sudah diambil',
                hint: 'Penumpang lain lebih dulu memilih kursi tersebut. Silakan pilih kursi lain.',
            },
            410: {
                icon: '⏱',
                tone: 'is-warning',
                title: 'Waktu habis',
                hint: 'Kursi hanya ditahan selama 5 menit dan kini sudah dilepas. Silakan pilih ulang.',
            },
            404: {
                icon: '!',
                tone: 'is-error',
                title: 'Pesanan tidak ditemukan',
                hint: 'Kursi yang Anda tahan sudah dilepas. Silakan mulai dari pencarian jadwal.',
            },
        };

        var preset = presets[status] || {
            icon: '!',
            tone: 'is-error',
            title: 'Pesanan gagal',
            hint: 'Silakan coba lagi beberapa saat lagi.',
        };

        el['result-icon'].className = 'result-icon ' + preset.tone;
        el['result-icon'].textContent = preset.icon;
        el['result-title'].textContent = preset.title;
        el['result-message'].textContent = data.message || preset.hint;

        var details = row('Langkah berikutnya', preset.hint, 'is-plain');

        if (data.unavailable_seats && data.unavailable_seats.length) {
            details = row('Kursi bermasalah', data.unavailable_seats.join(', ')) + details;
        }

        el['result-details'].innerHTML = details;
        el['result-restart'].textContent = 'Kembali ke pencarian';

        showView(VIEW_RESULT);
    }

    el['result-restart'].addEventListener('click', enterSearch);

    /* ------------------------------------------------------------- bootstrap */

    (async function init() {
        var stored;
        try {
            stored = JSON.parse(localStorage.getItem(STORAGE_USER));
        } catch (e) {
            stored = null;
        }

        if (!getToken() || !stored) {
            showView(VIEW_LOGIN);
            return;
        }

        // Token tersimpan divalidasi ke server sebelum dipercaya.
        var result = await api('GET', '/auth/me');

        if (result.ok) {
            saveSession(getToken(), result.data.user);
            el.passenger_name.value = result.data.user.name;
            await enterSearch();
            return;
        }

        clearSession();
        showView(VIEW_LOGIN);
        showAlert(el['login-alert'], 'warning', 'Sesi sebelumnya sudah berakhir. Silakan masuk kembali.');
    })();
})();
