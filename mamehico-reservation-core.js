// MAMEHICO 予約システム 共通エンジン v2.2.19
// 銀座ランチ（ginza）・ヨシノ系（yoshino）両対応
//
// 更新履歴
// v2.2.19 - 2026-03-12 food_box_selectionsをsend-confirmation/create-checkoutに追加（{food_box_summary}対応）
// v2.2.18 - 2026-03-12 mealCountsをFirestoreのfoodsキーで動的初期化・boxCountを動的計算に変更（A/AB/AC/ABCハードコード除去）
// v2.2.15 - 2026-03-12 loadEventConfig: start_date/end_dateをローカル時間でパース（UTC解釈バグ修正）
// v2.2.14 - 2026-03-12 renderStep2Yoshino: 「飲食セット」セクションラベルを削除
// v2.2.13 - 2026-03-11 リダイレクトURLからvenue/nameを除去（Xserverブロック対策）・sessionStorageで受け渡し
// v2.2.12 - 2026-03-11 venueForDate/venueForStep3: venues配列・文字列両対応
// v2.2.11 - 2026-03-11 saveDirectReservation: yoshino成功ページにname・venueを追加
// v2.2.10 - 2026-03-11 renderStep3: yoshino確認画面に会場行を追加
// v2.2.9 - 2026-03-11 renderStep2Yoshino: hasFoods=falseのとき飲食セットUIを非表示・
//                      人数入力UIを表示。renderStep3/saveDirectReservation/startStripeCheckout:
//                      hasFoods=falseのとき入場料（price×count）で金額計算。
// v2.2.8 - 2026-03-11 venue送信追加・yoshino food文字列修正（mealCountsから生成）
// v2.2.7 - 2026-03-09 renderStep2冒頭に入力値退避処理追加
// v2.2.6 - 2026-03-09 日付別タイトル（titles）優先取得・完了画面に演目名表示
// v2.2.5 - 2026-03-09 hasFoods/hasCoins判定修正・slotDocId修正・titles/venues表示対応
// v2.2.2 - 2026-03-09 yoshino: 飲食セット必須バリデーションを撤廃
// v2.2.1 - 2026-03-09 yoshino対応・銀座・ヨシノ共通エンジン化
// v2.0.0 - 銀座ランチ専用版（旧）
(function() {
    'use strict';

    var cfg = window.MAMEHICO_CONFIG;
    if (!cfg) return;

    var MODE = cfg.mode || 'ginza';
    var DAYS_JP = ['\u65e5','\u6708','\u706b','\u6c34','\u6728','\u91d1','\u571f'];

    var state = {
        step: 1,
        currentYear: 0, currentMonth: 0,
        selectedDate: null, selectedSlot: null,
        count: 2, name: '', email: '', phone: '',
        paymentMethod: 'card',
        selectedCoin: 0,
        selectedFood: { key: 'none', label: '\u306a\u3057', value: 0 },
        mealCounts: {},
        foodBoxSelections: [],
        coinCounts: { 3000: 0, 5000: 0, 10000: 0 },
        slotAvailability: {},
        openDates: {}, closedDates: {}, specialDates: {},
        slotOverrides: {},
        dateTitles: {}, dateVenues: {}, dateCharges: {},
        loading: false, error: '',
        EC: null
    };

    var db = null;
    var root = null;

    function today() { var d = new Date(); d.setHours(0,0,0,0); return d; }

    function padDate(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth()+1).padStart(2,'0') + '-' +
            String(d.getDate()).padStart(2,'0');
    }

    function initFirestore() {
        return import('https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js').then(function(mod) {
            var existing = mod.getApps().find(function(a) { return a.name === 'mamehico-res'; });
            var app = existing || mod.initializeApp(cfg.firebase, 'mamehico-res');
            return import('https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js').then(function(fsMod) {
                db = fsMod.getFirestore(app);
            });
        });
    }

    function fsGet(col, id) {
        return import('https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js').then(function(mod) {
            return mod.getDoc(mod.doc(db, col, id)).then(function(snap) {
                return { exists: snap.exists(), data: snap.exists() ? snap.data() : null };
            });
        });
    }

    function fsSet(col, id, data) {
        return import('https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js').then(function(mod) {
            return mod.setDoc(mod.doc(db, col, id), data);
        });
    }

    function fsUpdate(col, id, field, val) {
        return import('https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js').then(function(mod) {
            var upd = {}; upd[field] = mod.increment(val);
            return mod.updateDoc(mod.doc(db, col, id), upd);
        });
    }

    function loadEventConfig() {
        if (MODE === 'ginza') {
            state.EC = {
                title: '\u9280\u5ea7\u30e9\u30f3\u30c1\u4e88\u7d04',
                subtitle: 'MAMEHICO GINZA',
                price: 6000, capacity: 10, duration: '120\u5206\u30fb\u5165\u66ff\u5236',
                slots: [
                    { time: '11:30', end: '13:30', key: '1130' },
                    { time: '12:30', end: '14:30', key: '1230' },
                    { time: '13:30', end: '15:30', key: '1330' },
                    { time: '14:30', end: '16:30', key: '1430' }
                ],
                availableDow: [2,3,4,5],
                startDate: new Date(2026, 2, 17), endDate: new Date(2026, 6, 24),
                maxMonth: { year: 2026, month: 7 },
                allowCash: true, allowCard: true,
                hasFoods: false, hasCoins: false, foods: [], coins: [],
                successUrl: '/ginza-lunch-success/', cancelUrl: '/ginza-lunch/',
                tel: '03-6263-0820'
            };
            return Promise.resolve();
        }
        return fsGet('yoshino_events', cfg.eventId).then(function(r) {
            if (!r.exists || !r.data) { state.error = '\u30a4\u30d9\u30f3\u30c8\u60c5\u5831\u306e\u8aad\u307f\u8fbc\u307f\u306b\u5931\u6557\u3057\u307e\u3057\u305f\u3002'; return; }
            var d = r.data;
            var foods = [];
            if (d.foods) { d.foods.forEach(function(f) { foods.push({ key: f.key || f.label, label: f.label, desc: f.desc || '', value: Number(f.price || f.value || 0) }); }); }
            // foods のキーで mealCounts を動的初期化
            state.mealCounts = {};
            foods.forEach(function(f) { state.mealCounts[f.key] = 0; });
            var coinValues = [];
            if (d.coins) { d.coins.forEach(function(c) { coinValues.push(Number(c)); }); }
            var startDate = d.start_date ? new Date(d.start_date + 'T00:00:00') : new Date();
            var endDate = d.end_date ? new Date(d.end_date + 'T00:00:00') : new Date(2099, 11, 31);
            var maxMonth = { year: endDate.getFullYear(), month: endDate.getMonth() + 2 };
            if (maxMonth.month > 12) { maxMonth.month = 1; maxMonth.year++; }
            state.EC = {
                title: d.title || '\u30a4\u30d9\u30f3\u30c8\u4e88\u7d04',
                subtitle: d.subtitle || 'MAMEHICO',
                foodNote: d.food_note || '', coinNote: d.coin_note || '',
                venue: d.venue || '', price: d.price || 0, capacity: d.capacity || 45,
                duration: d.duration || '', slots: d.slots || [], availableDow: [],
                startDate: startDate, endDate: endDate, maxMonth: maxMonth,
                eventId: cfg.eventId,
                allowCash: d.allow_cash !== false, allowCard: d.allow_card !== false,
                hasFoods: !!(d.foods && d.foods.length),
                hasCoins: !!(d.coins && d.coins.length),
                foods: foods, foodBoxes: d.food_boxes || [], coinValues: coinValues,
                successUrl: d.success_url || '/yoshino-success/',
                cancelUrl: d.cancel_url || '/', tel: d.tel || '03-6263-0820'
            };
        });
    }

    function fetchDates(year, month) {
        var prefix = year + String(month).padStart(2,'0');
        if (MODE === 'yoshino') {
            return fsGet('yoshino_open_dates', cfg.eventId + '_' + prefix).then(function(r) {
                if (r.exists && r.data) {
                    if (r.data.dates) { r.data.dates.forEach(function(d) { state.openDates[d] = true; }); }
                    if (r.data.slot_overrides) { Object.keys(r.data.slot_overrides).forEach(function(d) { state.slotOverrides[d] = r.data.slot_overrides[d]; }); }
                    if (r.data.titles) { Object.keys(r.data.titles).forEach(function(d) { state.dateTitles[d] = r.data.titles[d]; }); }
                    if (r.data.venues) { Object.keys(r.data.venues).forEach(function(d) { state.dateVenues[d] = r.data.venues[d]; }); }
                    if (r.data.charges) { Object.keys(r.data.charges).forEach(function(d) { state.dateCharges[d] = r.data.charges[d]; }); }
                }
            }).catch(function(){});
        }
        return Promise.all([
            fsGet('ginza_closed_dates', prefix).then(function(r) { if (r.exists && r.data && r.data.dates) { r.data.dates.forEach(function(d) { state.closedDates[d] = true; }); } }).catch(function(){}),
            fsGet('ginza_special_dates', prefix).then(function(r) { if (r.exists && r.data && r.data.dates) { r.data.dates.forEach(function(d) { state.specialDates[d] = true; }); } }).catch(function(){})
        ]);
    }

    function isDateAvailable(dateObj, dateStr) {
        var EC = state.EC;
        if (MODE === 'yoshino') return !!state.openDates[dateStr];
        if (state.closedDates[dateStr]) return false;
        if (state.specialDates[dateStr]) return true;
        return EC.availableDow.indexOf(dateObj.getDay()) !== -1;
    }

    function fetchAvailability(year, month) {
        return fetchDates(year, month).then(function() {
            var EC = state.EC;
            var daysInMonth = new Date(year, month, 0).getDate();
            var promises = [];
            for (var d = 1; d <= daysInMonth; d++) {
                var date = new Date(year, month-1, d);
                var dateStr = padDate(date);
                if (!isDateAvailable(date, dateStr) || date < today()) continue;
                (function(ds) {
                    EC.slots.forEach(function(slot) {
                        var stateKey = ds + '_' + slot.key;
                        if (state.slotAvailability[stateKey] !== undefined) return;
                        var col = MODE === 'yoshino' ? 'yoshino_slots' : 'ginza_slots';
                        var docId = MODE === 'yoshino' ? (ds + '_' + slot.key) : stateKey;
                        promises.push(fsGet(col, docId).then(function(r) {
                            state.slotAvailability[stateKey] = r.exists ? r.data : { booked: 0, capacity: EC.capacity };
                        }).catch(function() { state.slotAvailability[stateKey] = { booked: 0, capacity: EC.capacity }; }));
                    });
                })(dateStr);
            }
            return Promise.all(promises);
        });
    }

    function getAvail(dateStr, slotKey) {
        var EC = state.EC;
        var a = state.slotAvailability[dateStr + '_' + slotKey];
        if (!a) return { remaining: EC.capacity };
        return { booked: a.booked || 0, capacity: a.capacity || EC.capacity, remaining: Math.max(0, (a.capacity || EC.capacity) - (a.booked || 0)) };
    }

    function render() {
        if (!root) root = document.getElementById('mamehico-reservation-root');
        if (!root) return;
        if (state.step === 1) renderStep1();
        else if (state.step === 2) renderStep2();
        else if (state.step === 3) renderStep3();
    }

    function renderStep1() {
        var EC = state.EC;
        var now = today();
        if (!state.currentYear) {
            var sy = EC.startDate.getFullYear(), sm = EC.startDate.getMonth()+1;
            var ny = now.getFullYear(), nm = now.getMonth()+1;
            if (sy < ny || (sy === ny && sm < nm)) { state.currentYear = ny; state.currentMonth = nm; }
            else { state.currentYear = sy; state.currentMonth = sm; }
        }
        var slotsHtml = EC.slots.map(function(s) { return '<button class="res-slot-btn" disabled><span class="slot-time">'+s.time+' \u2014 '+s.end+'</span><span class="slot-avail">\u65e5\u4ed8\u3092\u9078\u629e\u3057\u3066\u304f\u3060\u3055\u3044</span></button>'; }).join('');
        root.innerHTML =
            '<div class="res-title">'+EC.title+'</div><div class="res-subtitle">'+EC.subtitle+'</div>' +
            '<div class="res-steps"><div class="res-step active"></div><div class="res-step"></div><div class="res-step"></div></div>' +
            '<span class="res-section-label">\u65e5\u4ed8</span>' +
            '<div class="res-month-nav"><button class="res-nav-btn" id="prev-month">\uff1c</button><span class="month-label">'+state.currentYear+'\u5e74 '+state.currentMonth+'\u6708</span><button class="res-nav-btn" id="next-month">\uff1e</button></div>' +
            '<div class="res-date-grid" id="date-grid"><div style="grid-column:1/-1" class="res-loading-text">\u8aad\u307f\u8fbc\u307f\u4e2d...</div></div>' +
            '<hr class="res-divider"><span class="res-section-label">\u6642\u9593\u5e2f</span>' +
            '<div class="res-slots" id="slot-grid">'+slotsHtml+'</div>' +
            (state.error ? '<div class="res-error">'+state.error+'</div>' : '');
        var maxMD = new Date(EC.maxMonth.year, EC.maxMonth.month-1, 1);
        var nowMD = new Date(now.getFullYear(), now.getMonth(), 1);
        var startMD = new Date(EC.startDate.getFullYear(), EC.startDate.getMonth(), 1);
        var minMD = startMD > nowMD ? startMD : nowMD;
        document.getElementById('prev-month').addEventListener('click', function() {
            var prev = new Date(state.currentYear, state.currentMonth-2, 1);
            if (prev < minMD) return;
            state.currentYear = prev.getFullYear(); state.currentMonth = prev.getMonth()+1;
            state.selectedDate = null; state.selectedSlot = null;
            state.openDates = {}; state.slotOverrides = {}; state.dateTitles = {}; state.dateVenues = {}; state.dateCharges = {};
            renderStep1();
        });
        document.getElementById('next-month').addEventListener('click', function() {
            var next = new Date(state.currentYear, state.currentMonth, 1);
            if (next > maxMD) return;
            state.currentYear = next.getFullYear(); state.currentMonth = next.getMonth()+1;
            state.selectedDate = null; state.selectedSlot = null;
            state.openDates = {}; state.slotOverrides = {}; state.dateTitles = {}; state.dateVenues = {}; state.dateCharges = {};
            renderStep1();
        });
        if (new Date(state.currentYear, state.currentMonth-1, 1) <= minMD) document.getElementById('prev-month').disabled = true;
        if (new Date(state.currentYear, state.currentMonth, 1) > maxMD) document.getElementById('next-month').disabled = true;
        fetchAvailability(state.currentYear, state.currentMonth).then(function() { renderCalendar(); renderSlots(); });
    }

    function renderCalendar() {
        var grid = document.getElementById('date-grid');
        if (!grid) return;
        var EC = state.EC; var now = today();
        var year = state.currentYear; var month = state.currentMonth;
        var daysInMonth = new Date(year, month, 0).getDate();
        var startOffset = (new Date(year, month-1, 1).getDay() + 6) % 7;
        var html = ['\u6708','\u706b','\u6c34','\u6728','\u91d1','\u571f','\u65e5'].map(function(n) { return '<div class="res-date-header">'+n+'</div>'; }).join('');
        for (var i = 0; i < startOffset; i++) html += '<button class="res-date-btn empty" disabled></button>';
        for (var d = 1; d <= daysInMonth; d++) {
            var date = new Date(year, month-1, d); var dateStr = padDate(date);
            var available = isDateAvailable(date, dateStr) && date >= EC.startDate && date <= EC.endDate && date >= now;
            var isSelected = state.selectedDate === dateStr;
            var cls = 'res-date-btn' + (isSelected ? ' active' : '');
            var titleHtml = (MODE === 'yoshino' && state.dateTitles[dateStr]) ? '<span class="day-event-title">'+state.dateTitles[dateStr]+'</span>' : '';
            var venueHtml = (MODE === 'yoshino' && state.dateVenues[dateStr]) ? '<span class="day-event-venue">'+state.dateVenues[dateStr]+'</span>' : '';
            html += '<button class="'+cls+'" '+(!available ? 'disabled' : 'data-date="'+dateStr+'"')+'><span class="day-num">'+d+'</span>'+titleHtml+venueHtml+'</button>';
        }
        grid.innerHTML = html;
        grid.querySelectorAll('[data-date]').forEach(function(btn) {
            btn.addEventListener('click', function() { state.selectedDate = btn.getAttribute('data-date'); state.selectedSlot = null; renderCalendar(); renderSlots(); });
        });
    }

    function renderSlots() {
        var grid = document.getElementById('slot-grid');
        if (!grid) return;
        var EC = state.EC;
        if (!state.selectedDate) {
            grid.innerHTML = EC.slots.map(function(s) { return '<button class="res-slot-btn" disabled><span class="slot-time">'+s.time+' \u2014 '+s.end+'</span><span class="slot-avail">\u65e5\u4ed8\u3092\u9078\u629e\u3057\u3066\u304f\u3060\u3055\u3044</span></button>'; }).join('');
            return;
        }
        var dow = new Date(state.selectedDate).getDay();
        var allowedSlots = state.slotOverrides[state.selectedDate] || null;
        grid.innerHTML = EC.slots.map(function(s) {
            if (allowedSlots && allowedSlots.indexOf(s.key) === -1) return '';
            if (s.weekdayOnly && (dow === 0 || dow === 6)) return '';
            if (s.weekendOnly && dow !== 0 && dow !== 6) return '';
            var a = getAvail(state.selectedDate, s.key);
            var isFull = a.remaining === 0; var isLow = a.remaining > 0 && a.remaining <= 3;
            var isSelected = state.selectedSlot === s.key;
            var availText = isFull ? '<span class="slot-avail full">\u6e80\u5e2d</span>' : isLow ? '<span class="slot-avail low">\u6b8b\u308a'+a.remaining+'\u5e2d</span>' : '<span class="slot-avail">\u6b8b\u308a'+a.remaining+'\u5e2d</span>';
            var cls = 'res-slot-btn' + (isSelected ? ' active' : '');
            return '<button class="'+cls+'" data-slot="'+s.key+'" '+(isFull ? 'disabled' : '')+'><span class="slot-time">'+s.time+' \u2014 '+s.end+'</span>'+availText+'</button>';
        }).join('');
        grid.querySelectorAll('[data-slot]:not(:disabled)').forEach(function(btn) {
            btn.addEventListener('click', function() {
                state.selectedSlot = btn.getAttribute('data-slot');
                renderSlots();
                if (state.selectedDate && state.selectedSlot) { setTimeout(function() { state.step = 2; render(); }, 150); }
            });
        });
    }

    function buildPaymentHtml(EC) {
        if (EC.allowCash && EC.allowCard) {
            return '<hr class="res-divider"><span class="res-section-label">\u304a\u652f\u6255\u3044\u65b9\u6cd5</span><div class="res-payment-options">' +
                '<button class="res-payment-btn'+(state.paymentMethod==='card'?' active':'')+'" data-payment="card"><span class="payment-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span><span class="payment-label">\u30af\u30ec\u30b8\u30c3\u30c8\u30ab\u30fc\u30c9</span><span class="payment-note">\u4eca\u3059\u3050\u30aa\u30f3\u30e9\u30a4\u30f3\u3067</span></button>' +
                '<button class="res-payment-btn'+(state.paymentMethod==='cash'?' active':'')+'" data-payment="cash"><span class="payment-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span><span class="payment-label">\u5e97\u982d\u3067\u304a\u652f\u6255\u3044</span><span class="payment-note">\u5f53\u65e5\u73fe\u91d1\u30fb\u30ab\u30fc\u30c9\u53ef</span></button>' +
                '</div>';
        }
        if (EC.allowCash) { state.paymentMethod = 'cash'; } else { state.paymentMethod = 'card'; }
        return '';
    }

    function bindPaymentBtns() {
        root.querySelectorAll('[data-payment]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                state.paymentMethod = btn.getAttribute('data-payment');
                root.querySelectorAll('[data-payment]').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });
    }

    function renderStep2Ginza(EC, maxCount) {
        var coinsHtml = '';
        if (EC.hasCoins && EC.coins && EC.coins.length > 1) {
            coinsHtml = '<hr class="res-divider"><span class="res-section-label">\u304a\u3046\u3048\u3093\u30b3\u30a4\u30f3\uff08\u4efb\u610f\uff09</span><div class="res-slots">'+
                EC.coins.map(function(c) { return '<button class="res-slot-btn'+(state.selectedCoin===c.value?' active':'')+'" data-coin="'+c.value+'"><span class="slot-time">'+c.label+'</span></button>'; }).join('')+'</div>';
        }
        var foodsHtml = '';
        if (EC.hasFoods && EC.foods && EC.foods.length > 0) {
            foodsHtml = '<hr class="res-divider"><span class="res-section-label">\u9996\u4e8b\u30bb\u30c3\u30c8\uff08\u4efb\u610f\u30fb1\u540d\u3042\u305f\u308a\uff09</span><div class="res-slots">'+
                EC.foods.map(function(f) {
                    var isSelected = state.selectedFood && state.selectedFood.key === f.key;
                    var priceLabel = f.value > 0 ? '\u00a5'+f.value.toLocaleString()+'/\u540d' : '';
                    return '<button class="res-slot-btn'+(isSelected?' active':'')+'" data-food="'+f.key+'"><span class="slot-time">'+f.label+'</span>'+(priceLabel ? '<span class="slot-avail">'+priceLabel+'</span>' : '')+'</button>';
                }).join('')+'</div>';
        }
        return '<hr class="res-divider"><span class="res-section-label">\u4eba\u6570</span>' +
            '<div class="res-count-row"><button class="res-count-btn" id="count-down">\uff0d</button><span class="res-count-display" id="count-display">'+state.count+'</span><span class="res-count-label">\u540d</span><button class="res-count-btn" id="count-up">\uff0b</button><span style="font-size:.7rem;color:var(--res-text-sub);margin-left:8px">\u6700\u5927 '+maxCount+' \u540d</span></div>' +
            coinsHtml + foodsHtml;
    }

    function renderStep2Yoshino(EC, maxCount) {
        var SETS = EC.foods || [];
        var FOOD_BOXES = EC.foodBoxes || [];
        var COIN_VALS = EC.coinValues || [];
        var total = 0;
        Object.keys(state.mealCounts).forEach(function(k){ total += (state.mealCounts[k]||0); });
        var boxCount = 0;
        if (EC.foodBoxes && EC.foodBoxes.length > 0) {
            Object.keys(state.mealCounts).forEach(function(k) { boxCount += (state.mealCounts[k] || 0); });
        }
        while (state.foodBoxSelections.length < boxCount) state.foodBoxSelections.push('');
        while (state.foodBoxSelections.length > boxCount) state.foodBoxSelections.pop();

        var countHtml = '';
        if (!EC.hasFoods) {
            countHtml = '<hr class="res-divider"><span class="res-section-label">\u4eba\u6570</span>' +
                '<div class="res-count-row"><button class="res-count-btn" id="count-down">\uff0d</button><span class="res-count-display" id="count-display">'+state.count+'</span><span class="res-count-label">\u540d</span><button class="res-count-btn" id="count-up">\uff0b</button><span style="font-size:.7rem;color:var(--res-text-sub);margin-left:8px">\u6700\u5927 '+maxCount+' \u540d</span></div>';
        }

        var setsHtml = '';
        if (EC.hasFoods && SETS.length > 0) {
            // [v2.2.14] 「飲食セット」ラベルを削除
            var foodNoteHtml = EC.foodNote ? '<p style="font-size:.78rem;color:var(--res-text-sub);margin:4px 0 10px;line-height:1.6">'+EC.foodNote+'</p>' : '';
            setsHtml = '<hr class="res-divider">'+foodNoteHtml+'<div id="yoshino-sets">' +
                SETS.map(function(s) {
                    var cnt = state.mealCounts[s.key] || 0;
                    return '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--res-border)"><div style="flex:1;padding-right:8px;min-width:0"><div style="font-size:.92rem;font-weight:500">'+s.label+'\u3000<span style="font-weight:400;color:var(--res-text-sub);font-size:.8rem">\u00a5'+s.value.toLocaleString()+'</span></div>'+(s.desc ? '<div style="font-size:.75rem;color:var(--res-text-sub);line-height:1.5;margin-top:2px">'+s.desc+'</div>' : '')+'</div><div style="display:flex;align-items:center;gap:10px"><button class="res-count-btn meal-down" data-set="'+s.key+'" '+(cnt<=0?'disabled':'')+'>\uff0d</button><span style="min-width:24px;text-align:center;font-size:1rem" id="meal-cnt-'+s.key+'">'+cnt+'</span><button class="res-count-btn meal-up" data-set="'+s.key+'" '+(total>=maxCount?'disabled':'')+'>\uff0b</button></div></div>';
                }).join('') +
                '<div style="display:flex;justify-content:space-between;padding:10px 0;font-size:.9rem"><span style="color:var(--res-text-sub)">\u5408\u8a08</span><span id="meal-total-display" style="font-weight:500">'+total+' \u540d</span></div></div>';
        }

        var boxHtml = '';
        if (EC.hasFoods && FOOD_BOXES.length > 0 && boxCount > 0) {
            var boxItems = '';
            for (var i = 0; i < boxCount; i++) {
                var selected = state.foodBoxSelections[i] || '';
                boxItems += '<div style="margin-bottom:8px"><div style="font-size:.8rem;color:var(--res-text-sub);margin-bottom:4px">'+(i+1)+'\u4eba\u76ee\u306e\u304a\u98df\u4e8b</div><div style="display:flex;gap:6px;flex-wrap:wrap">'+
                    FOOD_BOXES.map(function(fb) { return '<button class="res-date-btn foodbox-btn'+(selected===fb?' active':'')+'" data-box-idx="'+i+'" data-box="'+fb+'" style="padding:6px 12px;font-size:.85rem;min-height:0">'+fb+'</button>'; }).join('')+'</div></div>';
            }
            boxHtml = '<hr class="res-divider"><span class="res-section-label">\u304a\u98df\u4e8b\u306e\u7a2e\u985e\u3092\u9078\u3093\u3067\u304f\u3060\u3055\u3044</span><div id="yoshino-boxes">'+boxItems+'</div>';
        }

        var coinHtml = '';
        if (EC.hasCoins && COIN_VALS.length > 0) {
            var coinNoteHtml = EC.coinNote ? '<p style="font-size:.78rem;color:var(--res-text-sub);margin:4px 0 12px;line-height:1.6">'+EC.coinNote+'</p>' : '';
            coinHtml = '<hr class="res-divider"><span class="res-section-label">\u5fdc\u63f4\u30b3\u30a4\u30f3\uff08\u4efb\u610f\u30fb\u4f55\u679a\u3067\u3082\uff09</span>'+coinNoteHtml+'<div id="yoshino-coins">' +
                COIN_VALS.map(function(cv) {
                    var cnt = state.coinCounts[cv] || 0;
                    return '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--res-border)"><div style="font-size:.95rem;font-weight:500">\u00a5'+cv.toLocaleString()+' \u30b3\u30a4\u30f3</div><div style="display:flex;align-items:center;gap:10px"><button class="res-count-btn coin-down" data-cv="'+cv+'" '+(cnt<=0?'disabled':'')+'>\uff0d</button><span style="min-width:24px;text-align:center;font-size:1rem" id="coin-cnt-'+cv+'">'+cnt+'</span><button class="res-count-btn coin-up" data-cv="'+cv+'">\uff0b</button></div></div>';
                }).join('')+'</div>';
        }
        return countHtml + setsHtml + boxHtml + coinHtml;
    }

    function bindStep2YoshinoEvents(EC, maxCount) {
        if (!EC.hasFoods) {
            function updateCount() {
                var cd = document.getElementById('count-display');
                var db2 = document.getElementById('count-down');
                var ub = document.getElementById('count-up');
                if (cd) cd.textContent = state.count;
                if (db2) db2.disabled = state.count <= 1;
                if (ub) ub.disabled = state.count >= maxCount;
            }
            var dBtn = document.getElementById('count-down');
            var uBtn = document.getElementById('count-up');
            if (dBtn) dBtn.addEventListener('click', function() { if(state.count>1){ state.count--; updateCount(); } });
            if (uBtn) uBtn.addEventListener('click', function() { if(state.count<maxCount){ state.count++; updateCount(); } });
            updateCount();
        }
        root.querySelectorAll('.meal-down,.meal-up').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var key = btn.getAttribute('data-set');
                var total = 0;
                Object.keys(state.mealCounts).forEach(function(k){ total += (state.mealCounts[k]||0); });
                if (btn.classList.contains('meal-down')) { if ((state.mealCounts[key]||0) > 0) state.mealCounts[key]--; }
                else { if (total < maxCount) state.mealCounts[key] = (state.mealCounts[key]||0) + 1; }
                var newTotal = 0;
                Object.keys(state.mealCounts).forEach(function(k){ newTotal += (state.mealCounts[k]||0); });
                state.count = newTotal;
                renderStep2();
            });
        });
        root.querySelectorAll('.foodbox-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { var idx = parseInt(btn.getAttribute('data-box-idx')); state.foodBoxSelections[idx] = btn.getAttribute('data-box'); renderStep2(); });
        });
        root.querySelectorAll('.coin-down,.coin-up').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cv = parseInt(btn.getAttribute('data-cv'));
                if (btn.classList.contains('coin-down')) { if ((state.coinCounts[cv]||0) > 0) state.coinCounts[cv]--; }
                else { state.coinCounts[cv] = (state.coinCounts[cv]||0) + 1; }
                renderStep2();
            });
        });
    }

    function renderStep2() {
        var nameEl = document.getElementById('res-name');
        var emailEl = document.getElementById('res-email');
        var phoneEl = document.getElementById('res-phone');
        if (nameEl && nameEl.value.trim()) state.name = nameEl.value.trim();
        if (emailEl && emailEl.value.trim()) state.email = emailEl.value.trim();
        if (phoneEl && phoneEl.value.trim()) state.phone = phoneEl.value.trim();
        var EC = state.EC;
        var a = getAvail(state.selectedDate, state.selectedSlot);
        var maxCount = Math.min(a.remaining, EC.capacity);
        var bodyHtml = MODE === 'yoshino' ? renderStep2Yoshino(EC, maxCount) : renderStep2Ginza(EC, maxCount);
        var paymentHtml = buildPaymentHtml(EC);
        root.innerHTML =
            '<div class="res-title">'+EC.title+'</div><div class="res-subtitle">'+EC.subtitle+'</div>' +
            '<div class="res-steps"><div class="res-step done"></div><div class="res-step active"></div><div class="res-step"></div></div>' +
            '<button class="res-back-btn" id="back-btn">\u2190 \u65e5\u4ed8\u30fb\u6642\u9593\u3092\u5909\u66f4</button>' +
            '<span class="res-section-label">\u304a\u540d\u524d\u30fb\u9023\u7d61\u5148</span>' +
            '<div class="res-field"><label>\u304a\u540d\u524d</label><input type="text" id="res-name" placeholder="\u5c71\u7530 \u592a\u90ce" value="'+state.name+'"></div>' +
            '<div class="res-field"><label>\u30e1\u30fc\u30eb\u30a2\u30c9\u30ec\u30b9</label><input type="email" id="res-email" placeholder="example@email.com" value="'+state.email+'"></div>' +
            '<div class="res-field"><label>\u96fb\u8a71\u756a\u53f7\uff08\u4efb\u610f\uff09</label><input type="tel" id="res-phone" placeholder="090-0000-0000" value="'+state.phone+'"></div>' +
            bodyHtml + paymentHtml +
            (state.error ? '<div class="res-error">'+state.error+'</div>' : '') +
            '<hr class="res-divider"><button class="res-submit-btn" id="next-btn">\u78ba\u8a8d\u753b\u9762\u3078</button>';
        document.getElementById('back-btn').addEventListener('click', function() { state.step=1; state.error=''; render(); });
        if (MODE === 'ginza') {
            function updateCountG() {
                document.getElementById('count-display').textContent = state.count;
                document.getElementById('count-down').disabled = state.count <= 1;
                document.getElementById('count-up').disabled = state.count >= maxCount;
            }
            document.getElementById('count-down').addEventListener('click', function() { if(state.count>1){ state.count--; updateCountG(); } });
            document.getElementById('count-up').addEventListener('click', function() { if(state.count<maxCount){ state.count++; updateCountG(); } });
            updateCountG();
            root.querySelectorAll('[data-coin]').forEach(function(btn) {
                btn.addEventListener('click', function() { state.selectedCoin = parseInt(btn.getAttribute('data-coin')); root.querySelectorAll('[data-coin]').forEach(function(b) { b.classList.remove('active'); }); btn.classList.add('active'); });
            });
            root.querySelectorAll('[data-food]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var key = btn.getAttribute('data-food'); state.selectedFood = null;
                    EC.foods.forEach(function(f) { if (f.key === key) state.selectedFood = f; });
                    if (!state.selectedFood) state.selectedFood = { key: 'none', label: '\u306a\u3057', value: 0 };
                    root.querySelectorAll('[data-food]').forEach(function(b) { b.classList.remove('active'); }); btn.classList.add('active');
                });
            });
        } else {
            bindStep2YoshinoEvents(EC, maxCount);
        }
        bindPaymentBtns();
        document.getElementById('next-btn').addEventListener('click', function() {
            state.name = document.getElementById('res-name').value.trim();
            state.email = document.getElementById('res-email').value.trim();
            state.phone = (document.getElementById('res-phone') || {value:''}).value.trim();
            if (!state.name) { state.error='\u304a\u540d\u524d\u3092\u5165\u529b\u3057\u3066\u304f\u3060\u3055\u3044'; renderStep2(); return; }
            if (!state.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email)) { state.error='\u30e1\u30fc\u30eb\u30a2\u30c9\u30ec\u30b9\u3092\u6b63\u3057\u304f\u5165\u529b\u3057\u3066\u304f\u3060\u3055\u3044'; renderStep2(); return; }
            if (MODE === 'yoshino') {
                var EC2 = state.EC;
                if (EC2.hasFoods) {
                    var total = 0;
                    Object.keys(state.mealCounts).forEach(function(k){ total += (state.mealCounts[k]||0); });
                    if (total > 0) state.count = total;
                    var boxCount = 0;
                    if (EC2.foodBoxes && EC2.foodBoxes.length > 0) {
                        Object.keys(state.mealCounts).forEach(function(k) { boxCount += (state.mealCounts[k] || 0); });
                        for (var i = 0; i < boxCount; i++) {
                            if (!state.foodBoxSelections[i]) { state.error = (i+1)+'\u4eba\u76ee\u306e\u304a\u98df\u4e8b\u3092\u9078\u629e\u3057\u3066\u304f\u3060\u3055\u3044'; renderStep2(); return; }
                        }
                    }
                }
            }
            state.error=''; state.step=3; render();
        });
    }

    function renderStep3() {
        var EC = state.EC;
        var slotInfo = null;
        EC.slots.forEach(function(s) { if (s.key === state.selectedSlot) slotInfo = s; });
        var parts = state.selectedDate.split('-');
        var m = parseInt(parts[1]); var d = parseInt(parts[2]);
        var dow = DAYS_JP[new Date(state.selectedDate).getDay()];
        var isCash = state.paymentMethod === 'cash';
        var seatTotal, coinTotal, foodTotal, subtotal, tax, grand, isFree;
        var rows = [
            '<div class="res-summary-row"><span>\u65e5\u4ed8</span><span class="val">'+m+'\u6708'+d+'\u65e5\uff08'+dow+'\uff09</span></div>',
            '<div class="res-summary-row"><span>\u6642\u9593</span><span class="val">'+(slotInfo ? slotInfo.time+' \u2014 '+slotInfo.end : '')+(EC.duration ? '\u3000'+EC.duration : '')+'</span></div>',
            '<div class="res-summary-row"><span>\u304a\u540d\u524d</span><span class="val">'+state.name+' \u69d8</span></div>',
            '<div class="res-summary-row"><span>\u30e1\u30fc\u30eb</span><span class="val">'+state.email+'</span></div>'
        ];
        if (state.phone) rows.push('<div class="res-summary-row"><span>\u96fb\u8a71</span><span class="val">'+state.phone+'</span></div>');
        if (MODE === 'yoshino') {
            var _rawVenueS3 = state.dateVenues && state.dateVenues[state.selectedDate];
            var venueForStep3 = (Array.isArray(_rawVenueS3) ? _rawVenueS3[0] : _rawVenueS3) || EC.venue || '';
            if (venueForStep3) rows.push('<div class="res-summary-row"><span>\u4f1a\u5834</span><span class="val">'+venueForStep3+'</span></div>');

            var SETS = EC.foods || [];
            foodTotal = 0;
            var mealRows = [];
            SETS.forEach(function(s) { var cnt = state.mealCounts[s.key] || 0; if (cnt > 0) { foodTotal += s.value * cnt; mealRows.push(s.label+' \u00d7 '+cnt+'\u540d \u00a5'+(s.value*cnt).toLocaleString()); } });
            rows.push('<div class="res-summary-row"><span>\u4eba\u6570</span><span class="val">'+state.count+' \u540d</span></div>');
            mealRows.forEach(function(r) { rows.push('<div class="res-summary-row"><span style="font-size:.8rem;color:var(--res-text-sub);padding-left:8px">'+r+'</span><span></span></div>'); });
            var boxCount = 0;
            if (EC.foodBoxes && EC.foodBoxes.length > 0) {
                Object.keys(state.mealCounts).forEach(function(k) { boxCount += (state.mealCounts[k] || 0); });
            }
            if (boxCount > 0 && state.foodBoxSelections.length > 0) { rows.push('<div class="res-summary-row"><span>\u304a\u98df\u4e8b</span><span class="val">'+state.foodBoxSelections.join('\u30fb')+'</span></div>'); }
            coinTotal = 0; var coinRows = [];
            (EC.coinValues || []).forEach(function(cv) { var cnt = state.coinCounts[cv] || 0; if (cnt > 0) { coinTotal += cv * cnt; coinRows.push('\u00a5'+cv.toLocaleString()+' \u00d7 '+cnt+'\u679a'); } });
            if (coinTotal > 0) {
                rows.push('<div class="res-summary-row"><span>\u5fdc\u63f4\u30b3\u30a4\u30f3</span><span class="val">\u00a5'+coinTotal.toLocaleString()+'</span></div>');
                coinRows.forEach(function(r) { rows.push('<div class="res-summary-row"><span style="font-size:.8rem;color:var(--res-text-sub);padding-left:8px">'+r+'</span><span></span></div>'); });
            }
            seatTotal = EC.hasFoods ? 0 : (EC.price * state.count);
            subtotal = foodTotal + coinTotal + seatTotal;
            tax = Math.floor(subtotal * 0.1); grand = subtotal + tax; isFree = grand === 0;
            if (seatTotal > 0) { rows.push('<div class="res-summary-row"><span>\u5165\u5834\u6599</span><span class="val">\u00a5'+EC.price.toLocaleString()+' \u00d7 '+state.count+'\u540d</span></div>'); }
            rows.push('<div class="res-summary-row"><span>\u304a\u652f\u6255\u3044</span><span class="val">'+(isCash ? '\u5e97\u982d\u3067\u304a\u652f\u6255\u3044' : '\u30af\u30ec\u30b8\u30c3\u30c8\u30ab\u30fc\u30c9')+'</span></div>');
            if (!isFree) { rows.push('<div class="res-summary-row"><span>\u6d88\u8cbb\u7a0e\uff0810%\uff09</span><span class="val">\u00a5'+tax.toLocaleString()+'</span></div>'); rows.push('<div class="res-summary-row total"><span>\u5408\u8a08</span><span class="val">\u00a5'+grand.toLocaleString()+'</span></div>'); }
            else { rows.push('<div class="res-summary-row total"><span>\u5408\u8a08</span><span class="val">\u7121\u6599</span></div>'); }
        } else {
            seatTotal = EC.price * state.count; coinTotal = state.selectedCoin || 0;
            foodTotal = state.selectedFood ? state.selectedFood.value * state.count : 0;
            subtotal = seatTotal + coinTotal + foodTotal; tax = Math.floor(subtotal * 0.1); grand = subtotal + tax; isFree = grand === 0;
            rows.push('<div class="res-summary-row"><span>\u4eba\u6570</span><span class="val">'+state.count+' \u540d</span></div>');
            if (!isFree) {
                rows.push('<div class="res-summary-row"><span>\u304a\u652f\u6255\u3044</span><span class="val">'+(isCash ? '\u5e97\u982d\u3067\u304a\u652f\u6255\u3044' : '\u30af\u30ec\u30b8\u30c3\u30c8\u30ab\u30fc\u30c9')+'</span></div>');
                if (EC.price > 0) rows.push('<div class="res-summary-row"><span>\u5165\u5834\u6599</span><span class="val">\u00a5'+EC.price.toLocaleString()+' \u00d7 '+state.count+'\u540d</span></div>');
                if (coinTotal > 0) rows.push('<div class="res-summary-row"><span>\u304a\u3046\u3048\u3093\u30b3\u30a4\u30f3</span><span class="val">\u00a5'+coinTotal.toLocaleString()+'</span></div>');
                if (foodTotal > 0) rows.push('<div class="res-summary-row"><span>'+state.selectedFood.label+'</span><span class="val">\u00a5'+state.selectedFood.value.toLocaleString()+' \u00d7 '+state.count+'\u540d</span></div>');
                rows.push('<div class="res-summary-row"><span>\u6d88\u8cbb\u7a0e\uff0810%\uff09</span><span class="val">\u00a5'+tax.toLocaleString()+'</span></div>');
                rows.push('<div class="res-summary-row total"><span>\u5408\u8a08</span><span class="val">\u00a5'+grand.toLocaleString()+'</span></div>');
            } else { rows.push('<div class="res-summary-row total"><span>\u5165\u5834\u6599</span><span class="val">\u7121\u6599</span></div>'); }
        }
        var btnLabel = isFree || isCash ? '\u4e88\u7d04\u3092\u78ba\u5b9a\u3059\u308b' : '\u30ab\u30fc\u30c9\u3067\u652f\u6255\u3046';
        var btnCls = 'res-submit-btn' + (isCash && !isFree ? ' cash' : '');
        root.innerHTML =
            '<div class="res-title">'+EC.title+'</div><div class="res-subtitle">'+EC.subtitle+'</div>' +
            '<div class="res-steps"><div class="res-step done"></div><div class="res-step done"></div><div class="res-step active"></div></div>' +
            '<button class="res-back-btn" id="back-btn">\u2190 \u5185\u5bb9\u3092\u5909\u66f4</button>' +
            '<span class="res-section-label">\u4e88\u7d04\u5185\u5bb9\u306e\u78ba\u8a8d</span>' +
            '<div class="res-summary">'+rows.join('')+'</div>' +
            (state.error ? '<div class="res-error">'+state.error+'</div>' : '') +
            '<button class="'+btnCls+'" id="pay-btn">'+(state.loading ? '\u51e6\u7406\u4e2d...' : btnLabel)+'</button>' +
            '<p class="res-note">'+(isFree || isCash ? '\u3054\u6765\u5834\u5f53\u65e5\u306b\u304a\u8d8a\u3057\u304f\u3060\u3055\u3044\u3002<br>\u30ad\u30e3\u30f3\u30bb\u30eb\u306f\u304a\u96fb\u8a71\u306b\u3066\u3002TEL: '+EC.tel : 'Stripe\u306e\u5b89\u5168\u306a\u30da\u30fc\u30b8\u3067\u6c7a\u6e08\u3057\u307e\u3059\u3002<br>\u5b8c\u4e86\u5f8c\u3001\u78ba\u8a8d\u30e1\u30fc\u30eb\u3092\u304a\u9001\u308a\u3057\u307e\u3059\u3002')+'</p>';
        document.getElementById('back-btn').addEventListener('click', function() { state.step=2; state.error=''; render(); });
        document.getElementById('pay-btn').addEventListener('click', function() {
            if (state.loading) return;
            state.loading=true; state.error=''; renderStep3();
            if (isFree) saveDirectReservation(slotInfo, 'free');
            else if (isCash) saveDirectReservation(slotInfo, 'cash');
            else startStripeCheckout(slotInfo);
        });
    }

    function getEffectiveTitle() {
        var EC = state.EC;
        return (state.selectedDate && state.dateTitles[state.selectedDate]) ? state.dateTitles[state.selectedDate] : (EC.title || '');
    }

    function saveDirectReservation(slotInfo, paymentMethod) {
        var EC = state.EC;
        var effectiveTitle = getEffectiveTitle();
        var reservationId = paymentMethod.toUpperCase() + '_' + Date.now() + '_' + Math.random().toString(36).substr(2,6).toUpperCase();
        var col = MODE === 'yoshino' ? 'yoshino_reservations' : 'ginza_reservations';
        var slotCol = MODE === 'yoshino' ? 'yoshino_slots' : 'ginza_slots';
        var slotDocId = state.selectedDate + '_' + slotInfo.key;
        var seatTotal, coinTotal, foodTotal, total;
        var saveData = {
            date: state.selectedDate, slot: slotInfo.time, slot_end: slotInfo.end, count: state.count,
            name: state.name, email: state.email, phone: state.phone,
            payment_method: paymentMethod, status: 'confirmed',
            event_id: MODE === 'yoshino' ? EC.eventId : 'ginza-lunch',
            event_title: effectiveTitle, created_at: new Date().toISOString()
        };
        if (MODE === 'yoshino') {
            var mealSummary = {};
            (EC.foods || []).forEach(function(s) { var cnt = state.mealCounts[s.key] || 0; if (cnt > 0) mealSummary[s.key] = cnt; });
            coinTotal = 0; var coinSummary = {};
            (EC.coinValues || []).forEach(function(cv) { var cnt = state.coinCounts[cv] || 0; if (cnt > 0) { coinSummary[cv] = cnt; coinTotal += cv * cnt; } });
            foodTotal = 0;
            (EC.foods || []).forEach(function(s) { foodTotal += s.value * (state.mealCounts[s.key] || 0); });
            seatTotal = EC.hasFoods ? 0 : (EC.price * state.count);
            total = foodTotal + coinTotal + seatTotal;
            saveData.meal_counts = mealSummary; saveData.food_box_selections = state.foodBoxSelections;
            saveData.coin_counts = coinSummary; saveData.coin_total = coinTotal;
            saveData.food_total = foodTotal; saveData.seat_total = seatTotal;
        } else {
            seatTotal = EC.price * state.count; coinTotal = state.selectedCoin || 0;
            foodTotal = state.selectedFood ? state.selectedFood.value * state.count : 0;
            total = seatTotal + coinTotal + foodTotal;
            saveData.coin = coinTotal; saveData.food_label = state.selectedFood ? state.selectedFood.label : '\u306a\u3057';
            saveData.food_price = state.selectedFood ? state.selectedFood.value : 0;
        }
        fsSet(col, reservationId, saveData).then(function() {
            return fsGet(slotCol, slotDocId).then(function(r) {
                if (r.exists) return fsUpdate(slotCol, slotDocId, 'booked', state.count);
                return fsSet(slotCol, slotDocId, { booked: state.count, capacity: EC.capacity, date: state.selectedDate, slot: slotInfo.time, event_id: MODE === 'yoshino' ? EC.eventId : 'ginza-lunch' });
            });
        }).then(function() {
            var _rawVenue = state.dateVenues && state.dateVenues[state.selectedDate];
            var venueForDate = (Array.isArray(_rawVenue) ? _rawVenue[0] : _rawVenue) || EC.venue || '';
            var foodStr = (function() {
                if (MODE !== 'yoshino') return state.selectedFood ? state.selectedFood.label : '\u306a\u3057';
                var parts = [];
                (EC.foods || []).forEach(function(s) { var cnt = state.mealCounts[s.key] || 0; if (cnt > 0) parts.push(s.label+'\u00d7'+cnt); });
                return parts.length > 0 ? parts.join(' / ') : '\u306a\u3057';
            })();
            // [v2.2.19] food_box_selectionsをsend-confirmationに追加
            var payload = {
                mode: MODE, event_id: MODE === 'yoshino' ? EC.eventId : 'ginza-lunch',
                event_title: effectiveTitle, venue: venueForDate,
                name: state.name, email: state.email, phone: state.phone,
                date: state.selectedDate, slot: slotInfo.time, count: state.count,
                payment: paymentMethod, total: total, tax: Math.floor(total * 0.1), grand: Math.floor(total * 1.1),
                coin: coinTotal, food: foodStr
            };
            if (MODE === 'yoshino') {
                payload.food_box_selections = state.foodBoxSelections;
            }
            return fetch(cfg.apiBase + '/send-confirmation', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
        }).then(function() {
            var venueForRedirect = (state.dateVenues && state.dateVenues[state.selectedDate]) || EC.venue || '';
            if (MODE === 'yoshino') {
                try {
                    sessionStorage.setItem('yoshino_success_name', state.name);
                    sessionStorage.setItem('yoshino_success_venue', venueForRedirect);
                } catch(e) {}
            }
            var params = 'type='+paymentMethod+'&event_id='+encodeURIComponent(MODE === 'yoshino' ? EC.eventId : 'ginza-lunch')+'&date='+state.selectedDate+'&slot='+encodeURIComponent(slotInfo.time)+'&slot_end='+encodeURIComponent(slotInfo.end)+'&count='+state.count;
            window.location.href = EC.successUrl + '?' + params;
        }).catch(function() { state.error = '\u4e88\u7d04\u306e\u4fdd\u5b58\u306b\u5931\u6557\u3057\u307e\u3057\u305f\u3002\u3082\u3046\u4e00\u5ea6\u304a\u8a66\u3057\u304f\u3060\u3055\u3044\u3002'; state.loading=false; renderStep3(); });
    }

    function startStripeCheckout(slotInfo) {
        var EC = state.EC;
        var effectiveTitle = getEffectiveTitle();
        var seatPrice = EC.hasFoods ? 0 : EC.price;
        // [v2.2.19] food_box_selectionsをJSON文字列でcreate-checkoutに追加
        fetch(cfg.apiBase + '/create-checkout', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                mode: MODE, event_id: MODE === 'yoshino' ? EC.eventId : 'ginza-lunch',
                event_title: effectiveTitle, date: state.selectedDate, slot: slotInfo.time,
                slot_end: slotInfo.end, count: state.count,
                name: state.name, email: state.email, phone: state.phone,
                seat_price: seatPrice, coin: state.selectedCoin || 0,
                food_label: state.selectedFood ? state.selectedFood.label : '\u306a\u3057',
                food_price: state.selectedFood ? state.selectedFood.value : 0,
                food_box_selections: JSON.stringify(MODE === 'yoshino' ? (state.foodBoxSelections || []) : [])
            })
        }).then(function(r) {
            return r.json().then(function(data) { if (!r.ok) throw new Error(data.message || '\u4e88\u7d04\u51e6\u7406\u306b\u5931\u6557\u3057\u307e\u3057\u305f'); return data; });
        }).then(function(data) { window.location.href = data.url; })
        .catch(function(e) { state.error = e.message; state.loading=false; renderStep3(); });
    }

    initFirestore().then(function() { return loadEventConfig(); }).then(function() {
        root = document.getElementById('mamehico-reservation-root');
        if (root) render();
    });

})();