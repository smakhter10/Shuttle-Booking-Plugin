(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('nsb-booking-form');
        if (!form) return;

        var data = (typeof nsbData !== 'undefined') ? nsbData : {};
        var packages = data.packages || {};
        var paymentSetting = data.paymentOptions || 'both';
        var currency = data.currency || '$';
        var ajaxUrl = data.ajaxUrl || '';
        var nonce = data.nonce || '';
        var timeSlots = Array.isArray(data.timeSlots) && data.timeSlots.length ? data.timeSlots : ['09:00', '11:00', '12:00', '14:30', '17:30'];

        var packageInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="nsb_package_id"]'));
        var payFullLabel = document.getElementById('nsb-pay-full-label');
        var payDepositLabel = document.getElementById('nsb-pay-deposit-label');
        var payFullInput = document.getElementById('nsb-pay-full');
        var payDepositInput = document.getElementById('nsb-pay-deposit');
        var priceSummary = document.getElementById('nsb-price-summary');
        var displayBase = document.getElementById('nsb-display-base-price');
        var displayDue = document.getElementById('nsb-display-due-now');
        var displayRemaining = document.getElementById('nsb-display-remaining');
        var remainingRow = document.getElementById('nsb-remaining-row');
        var summaryName = document.getElementById('nsb-summary-package-name');
        var summaryPrice = document.getElementById('nsb-summary-base-price');
        var summaryDesc = document.getElementById('nsb-summary-description');
        var summaryImage = document.getElementById('nsb-summary-image');
        var pickupDateInput = document.getElementById('nsb_booking_date');
        var pickupTimeInput = document.getElementById('nsb_booking_time');
        var returnDateInput = document.getElementById('nsb_return_date');
        var returnTimeInput = document.getElementById('nsb_return_time');
        var summaryPickupDate = document.getElementById('nsb-summary-pickup-date');
        var summaryPickupTime = document.getElementById('nsb-summary-pickup-time');
        var summaryReturnDate = document.getElementById('nsb-summary-return-date');
        var summaryReturnTime = document.getElementById('nsb-summary-return-time');
        var passengerInput = form.querySelector('[name="nsb_passenger_count"]');
        var seatAvailabilityBox = document.getElementById('nsb-seat-availability');
        var seatTotal = document.getElementById('nsb-seat-total');
        var seatBooked = document.getElementById('nsb-seat-booked');
        var seatAvailable = document.getElementById('nsb-seat-available');
        var seatHelper = document.getElementById('nsb-seat-helper');

        var modal = document.getElementById('nsb-calendar-modal');
        var modalDateLabel = document.getElementById('nsb-modal-date-label');
        var modalTimeLabel = document.getElementById('nsb-modal-time-label');
        var modalTitle = document.getElementById('nsb-calendar-title');
        var calendarGrid = document.getElementById('nsb-calendar-grid');
        var timeSlotList = document.getElementById('nsb-time-slot-list');
        var prevBtn = document.getElementById('nsb-cal-prev');
        var nextBtn = document.getElementById('nsb-cal-next');
        var applyBtn = document.getElementById('nsb-cal-apply');
        var clearDateBtn = document.getElementById('nsb-clear-date');
        var clearTimeBtn = document.getElementById('nsb-clear-time');

        var availabilityCache = {};
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var pickerState = {
            context: 'pickup',
            year: today.getFullYear(),
            month: today.getMonth(),
            selectedDate: '',
            selectedTime: '',
            monthAvailability: null,
            loading: false
        };

        packageInputs.forEach(function (input) {
            input.addEventListener('change', onPackageChange);
        });

        [payFullInput, payDepositInput].forEach(function (input) {
            if (input) input.addEventListener('change', updatePriceSummary);
        });

        [pickupDateInput, pickupTimeInput, returnDateInput, returnTimeInput].forEach(function (input) {
            if (input) input.addEventListener('change', function () { updateTripSummary(); updateSeatAvailabilityDisplay(); });
            if (input) input.addEventListener('input', function () { updateTripSummary(); updateSeatAvailabilityDisplay(); });
        });

        if (passengerInput) {
            passengerInput.addEventListener('input', validatePassengerCapacity);
            passengerInput.addEventListener('change', validatePassengerCapacity);
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-nsb-picker]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                openCalendar(btn.getAttribute('data-nsb-picker') || 'pickup');
            });
        });

        if (prevBtn) prevBtn.addEventListener('click', function () { changeMonth(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { changeMonth(1); });
        if (applyBtn) applyBtn.addEventListener('click', applyCalendarSelection);
        if (clearDateBtn) clearDateBtn.addEventListener('click', function () { pickerState.selectedDate = ''; renderCalendar(); renderTimeSlots(); updateModalLabels(); });
        if (clearTimeBtn) clearTimeBtn.addEventListener('click', function () { pickerState.selectedTime = ''; renderTimeSlots(); updateModalLabels(); });
        Array.prototype.slice.call(document.querySelectorAll('[data-nsb-calendar-close]')).forEach(function (el) {
            el.addEventListener('click', closeCalendar);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeCalendar();
        });

        function onPackageChange() {
            availabilityCache = {};
            var pkgId = getSelectedPackageId();
            if (!pkgId || !packages[pkgId]) {
                hidePriceSummary();
                return;
            }

            var pkg = packages[pkgId];
            updateSummaryCard(pkg);
            updatePaymentOptions(pkg);
            updatePriceSummary();
            updateSeatAvailabilityDisplay();
            if (modal && modal.classList.contains('is-open') && pickerState.context === 'pickup') {
                loadMonthAvailability().then(function () {
                    renderCalendar();
                    renderTimeSlots();
                });
            }
        }

        function updateSummaryCard(pkg) {
            if (summaryName) summaryName.textContent = pkg.name || 'Selected Package';
            if (summaryPrice) summaryPrice.textContent = formatPrice(parseFloat(pkg.base_price) || 0);
            if (summaryDesc) {
                summaryDesc.textContent = pkg.description || 'Reserve this package now, then complete your contact and payment details securely on the checkout page.';
            }
            if (summaryImage) {
                var defaultSrc = summaryImage.getAttribute('data-default-src') || summaryImage.src;
                var nextSrc = pkg.image_url || defaultSrc;
                if (summaryImage.src !== nextSrc) {
                    summaryImage.classList.add('is-changing');
                    window.setTimeout(function () {
                        summaryImage.src = nextSrc;
                        summaryImage.classList.remove('is-changing');
                    }, 120);
                }
            }
        }

        function updatePaymentOptions(pkg) {
            var hasDeposit = (pkg.deposit_type !== 'none' && parseFloat(pkg.deposit_amount) > 0);
            var showDeposit = false;

            if (paymentSetting === 'deposit_only') {
                showDeposit = hasDeposit;
            } else if (paymentSetting === 'both') {
                showDeposit = hasDeposit;
            }

            if (payDepositLabel) payDepositLabel.style.display = showDeposit ? '' : 'none';

            if (!showDeposit && payDepositInput && payDepositInput.checked && payFullInput) {
                payFullInput.checked = true;
            }

            if (paymentSetting === 'deposit_only' && showDeposit) {
                if (payDepositInput) payDepositInput.checked = true;
                if (payFullLabel) payFullLabel.style.display = 'none';
            } else if (paymentSetting === 'deposit_only' && !showDeposit) {
                if (payFullLabel) payFullLabel.style.display = '';
                if (payFullInput) payFullInput.checked = true;
            } else {
                if (payFullLabel) payFullLabel.style.display = '';
            }
        }

        function updatePriceSummary() {
            var pkgId = getSelectedPackageId();
            if (!pkgId || !packages[pkgId]) {
                hidePriceSummary();
                return;
            }

            var pkg = packages[pkgId];
            var basePrice = parseFloat(pkg.base_price) || 0;
            var isDeposit = payDepositInput && payDepositInput.checked;
            var dueNow = basePrice;
            var remaining = 0;

            if (isDeposit && pkg.deposit_type !== 'none' && parseFloat(pkg.deposit_amount) > 0) {
                if (pkg.deposit_type === 'fixed') {
                    dueNow = parseFloat(pkg.deposit_amount) || 0;
                    remaining = basePrice - dueNow;
                } else if (pkg.deposit_type === 'percentage') {
                    dueNow = Math.round((basePrice * parseFloat(pkg.deposit_amount) / 100) * 100) / 100;
                    remaining = Math.round((basePrice - dueNow) * 100) / 100;
                }
            }

            dueNow = Math.max(0, dueNow);
            remaining = Math.max(0, remaining);

            if (displayBase) displayBase.textContent = formatPrice(basePrice);
            if (displayDue) displayDue.textContent = formatPrice(dueNow);
            if (displayRemaining) displayRemaining.textContent = formatPrice(remaining);
            if (remainingRow) remainingRow.style.display = remaining > 0 ? '' : 'none';
            if (priceSummary) priceSummary.style.display = '';
        }

        function updateTripSummary() {
            if (summaryPickupDate) summaryPickupDate.textContent = formatDate(pickupDateInput && pickupDateInput.value) || '—';
            if (summaryPickupTime) summaryPickupTime.textContent = formatTime(pickupTimeInput && pickupTimeInput.value) || '—';
            if (summaryReturnDate) summaryReturnDate.textContent = formatDate(returnDateInput && returnDateInput.value) || '—';
            if (summaryReturnTime) summaryReturnTime.textContent = formatTime(returnTimeInput && returnTimeInput.value) || 'Optional';
            updateTriggerLabels();
        }

        function updateTriggerLabels() {
            setText('nsb_booking_date_label', formatDate(pickupDateInput && pickupDateInput.value, true) || 'Select date');
            setText('nsb_booking_time_label', (pickupTimeInput && pickupTimeInput.value) || 'Select time');
            setText('nsb_return_date_label', formatDate(returnDateInput && returnDateInput.value, true) || 'Select date');
            setText('nsb_return_time_label', (returnTimeInput && returnTimeInput.value) || 'Optional');
        }

        function setText(id, text) {
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function hidePriceSummary() {
            if (priceSummary) priceSummary.style.display = 'none';
        }

        function getSelectedPackageId() {
            var selected = form.querySelector('input[name="nsb_package_id"]:checked');
            return selected ? (parseInt(selected.value, 10) || 0) : 0;
        }

        function formatPrice(amount) {
            return currency + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function formatDate(value, compact) {
            if (!value) return '';
            var parts = value.split('-');
            if (parts.length !== 3) return value;
            var date = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
            if (isNaN(date.getTime())) return value;
            if (compact) {
                return date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }).replace(/,/g, '').replace(/ /g, ' / ');
            }
            return date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function formatTime(value) {
            if (!value) return '';
            var parts = value.split(':');
            if (parts.length < 2) return value;
            var date = new Date();
            date.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0, 0);
            if (isNaN(date.getTime())) return value;
            return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }

        function openCalendar(context) {
            if (!modal) return;
            pickerState.context = context === 'return' ? 'return' : 'pickup';
            pickerState.selectedDate = pickerState.context === 'pickup' ? (pickupDateInput.value || '') : (returnDateInput.value || '');
            pickerState.selectedTime = pickerState.context === 'pickup' ? (pickupTimeInput.value || '') : (returnTimeInput.value || '');

            var baseDate = pickerState.selectedDate ? parseDate(pickerState.selectedDate) : today;
            pickerState.year = baseDate.getFullYear();
            pickerState.month = baseDate.getMonth();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('nsb-calendar-open');
            loadMonthAvailability().then(function () {
                renderCalendar();
                renderTimeSlots();
                updateModalLabels();
            });
        }

        function closeCalendar() {
            if (!modal) return;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('nsb-calendar-open');
        }

        function changeMonth(delta) {
            var d = new Date(pickerState.year, pickerState.month + delta, 1);
            pickerState.year = d.getFullYear();
            pickerState.month = d.getMonth();
            loadMonthAvailability().then(function () {
                renderCalendar();
                renderTimeSlots();
            });
        }

        function loadMonthAvailability() {
            var pkgId = getSelectedPackageId() || 0;
            var key = pkgId + '-' + pickerState.year + '-' + (pickerState.month + 1);
            if (availabilityCache[key]) {
                pickerState.monthAvailability = availabilityCache[key];
                return Promise.resolve();
            }

            if (!ajaxUrl) {
                pickerState.monthAvailability = buildFallbackAvailability();
                return Promise.resolve();
            }

            pickerState.loading = true;
            var body = new URLSearchParams();
            body.append('action', 'nsb_get_availability');
            body.append('nonce', nonce);
            body.append('year', pickerState.year);
            body.append('month', pickerState.month + 1);
            body.append('package_id', pkgId);

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (res) { return res.json(); })
                .then(function (json) {
                    pickerState.loading = false;
                    var availability = json && json.success && json.data ? json.data : buildFallbackAvailability();
                    availabilityCache[key] = availability;
                    pickerState.monthAvailability = availability;
                })
                .catch(function () {
                    pickerState.loading = false;
                    pickerState.monthAvailability = buildFallbackAvailability();
                });
        }

        function buildFallbackAvailability() {
            return { days: {}, slots: timeSlots, limits: { day: 0, slot: 0 } };
        }

        function renderCalendar() {
            if (!calendarGrid) return;
            calendarGrid.innerHTML = '';
            if (modalTitle) {
                modalTitle.textContent = new Date(pickerState.year, pickerState.month, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            }

            var first = new Date(pickerState.year, pickerState.month, 1);
            var daysInMonth = new Date(pickerState.year, pickerState.month + 1, 0).getDate();
            var startOffset = (first.getDay() + 6) % 7; // Monday-first calendar.
            var prevMonthDays = new Date(pickerState.year, pickerState.month, 0).getDate();
            var totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

            for (var i = 0; i < totalCells; i++) {
                var dayNum, cellDate, outside = false;
                if (i < startOffset) {
                    dayNum = prevMonthDays - startOffset + i + 1;
                    cellDate = new Date(pickerState.year, pickerState.month - 1, dayNum);
                    outside = true;
                } else if (i >= startOffset + daysInMonth) {
                    dayNum = i - (startOffset + daysInMonth) + 1;
                    cellDate = new Date(pickerState.year, pickerState.month + 1, dayNum);
                    outside = true;
                } else {
                    dayNum = i - startOffset + 1;
                    cellDate = new Date(pickerState.year, pickerState.month, dayNum);
                }

                var iso = toIsoDate(cellDate);
                var blocked = isDayBlocked(iso, cellDate, outside);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'nsb-calendar-day';
                btn.textContent = dayNum;
                btn.dataset.date = iso;

                if (outside) btn.classList.add('is-outside');
                if (blocked) btn.classList.add('is-blocked');
                if (iso === pickerState.selectedDate) btn.classList.add('is-selected');
                if (toIsoDate(today) === iso) btn.classList.add('is-today');
                if (blocked) btn.disabled = true;

                btn.addEventListener('click', function () {
                    pickerState.selectedDate = this.dataset.date;
                    if (pickerState.context === 'pickup' && !isTimeAvailable(pickerState.selectedDate, pickerState.selectedTime)) {
                        pickerState.selectedTime = '';
                    }
                    renderCalendar();
                    renderTimeSlots();
                    updateModalLabels();
                });

                calendarGrid.appendChild(btn);
            }
        }

        function isDayBlocked(iso, dateObj, outside) {
            if (outside) return true;
            dateObj.setHours(0, 0, 0, 0);
            if (dateObj < today) return true;
            if (pickerState.context !== 'pickup') return false;
            var day = pickerState.monthAvailability && pickerState.monthAvailability.days ? pickerState.monthAvailability.days[iso] : null;
            return !!(day && day.blocked);
        }

        function renderTimeSlots() {
            if (!timeSlotList) return;
            timeSlotList.innerHTML = '';
            var slots = (pickerState.monthAvailability && pickerState.monthAvailability.slots) ? pickerState.monthAvailability.slots : timeSlots;
            slots.forEach(function (slot) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'nsb-time-slot';
                btn.textContent = slotToLabel(slot);
                btn.dataset.time = slot;

                var slotInfo = getSlotInfo(pickerState.selectedDate, slot);
                if (pickerState.context === 'pickup' && slotInfo && slotInfo.availableSeats !== null && typeof slotInfo.availableSeats !== 'undefined') {
                    var small = document.createElement('small');
                    small.textContent = slotInfo.availableSeats + ' seats left';
                    btn.appendChild(small);
                }

                var blocked = pickerState.context === 'pickup' && !isTimeAvailable(pickerState.selectedDate, slot);
                if (!pickerState.selectedDate && pickerState.context === 'pickup') blocked = true;
                if (blocked) {
                    btn.disabled = true;
                    btn.classList.add('is-blocked');
                }
                if (slot === pickerState.selectedTime) btn.classList.add('is-selected');

                btn.addEventListener('click', function () {
                    pickerState.selectedTime = this.dataset.time;
                    renderTimeSlots();
                    updateModalLabels();
                });
                timeSlotList.appendChild(btn);
            });
        }

        function getSlotInfo(date, time) {
            if (!date || !time) return null;
            var day = pickerState.monthAvailability && pickerState.monthAvailability.days ? pickerState.monthAvailability.days[date] : null;
            if (!day || !day.slots || !day.slots[time]) return null;
            return day.slots[time];
        }

        function isTimeAvailable(date, time) {
            if (!date || !time) return false;
            var day = pickerState.monthAvailability && pickerState.monthAvailability.days ? pickerState.monthAvailability.days[date] : null;
            if (!day) return true;
            if (day.blocked) return false;
            return !(day.slots && day.slots[time] && day.slots[time].blocked);
        }

        function updateSeatAvailabilityDisplay() {
            var pkgId = getSelectedPackageId();
            var pkg = pkgId && packages[pkgId] ? packages[pkgId] : null;
            if (!pkg) return;

            var capacity = pkg.max_passengers ? parseInt(pkg.max_passengers, 10) : 0;
            if (passengerInput) {
                if (capacity > 0) {
                    passengerInput.setAttribute('max', capacity);
                } else {
                    passengerInput.setAttribute('max', '99');
                }
            }

            if (!pickupDateInput || !pickupTimeInput || !pickupDateInput.value || !pickupTimeInput.value) {
                if (seatAvailabilityBox) seatAvailabilityBox.style.display = capacity > 0 ? '' : 'none';
                if (seatTotal) seatTotal.textContent = capacity > 0 ? capacity : 'Unlimited';
                if (seatBooked) seatBooked.textContent = '—';
                if (seatAvailable) seatAvailable.textContent = capacity > 0 ? 'Select time' : 'Unlimited';
                validatePassengerCapacity();
                return;
            }

            var date = pickupDateInput.value;
            var time = pickupTimeInput.value;
            var d = parseDate(date);
            pickerState.year = d.getFullYear();
            pickerState.month = d.getMonth();
            loadMonthAvailability().then(function () {
                var info = getSlotInfo(date, time);
                if (seatAvailabilityBox) seatAvailabilityBox.style.display = '';
                if (seatTotal) seatTotal.textContent = capacity > 0 ? capacity : 'Unlimited';
                if (seatBooked) seatBooked.textContent = info ? (info.bookedSeats || 0) : 0;
                if (seatAvailable) {
                    if (info && info.availableSeats !== null && typeof info.availableSeats !== 'undefined') {
                        seatAvailable.textContent = info.availableSeats;
                        if (passengerInput) passengerInput.setAttribute('max', Math.max(1, parseInt(info.availableSeats, 10) || 1));
                    } else {
                        seatAvailable.textContent = capacity > 0 ? capacity : 'Unlimited';
                    }
                }
                validatePassengerCapacity();
            });
        }

        function getCurrentAvailableSeats() {
            if (!pickupDateInput || !pickupTimeInput || !pickupDateInput.value || !pickupTimeInput.value) {
                var pkg = packages[getSelectedPackageId()] || {};
                return pkg.max_passengers ? parseInt(pkg.max_passengers, 10) : null;
            }
            var info = getSlotInfo(pickupDateInput.value, pickupTimeInput.value);
            if (info && info.availableSeats !== null && typeof info.availableSeats !== 'undefined') {
                return parseInt(info.availableSeats, 10);
            }
            var pkg2 = packages[getSelectedPackageId()] || {};
            return pkg2.max_passengers ? parseInt(pkg2.max_passengers, 10) : null;
        }

        function validatePassengerCapacity() {
            if (!passengerInput) return true;
            var available = getCurrentAvailableSeats();
            var requested = parseInt(passengerInput.value, 10) || 0;

            if (seatHelper) {
                if (available !== null && available >= 0) {
                    seatHelper.textContent = available + ' seat' + (available === 1 ? '' : 's') + ' available for the selected package/time. Each passenger counts as one seat.';
                    seatHelper.classList.toggle('is-error', requested > available);
                } else {
                    seatHelper.textContent = 'Unlimited seats available. Each passenger counts as one seat.';
                    seatHelper.classList.remove('is-error');
                }
            }

            return !(available !== null && requested > available);
        }

        function updateModalLabels() {
            if (modalDateLabel) modalDateLabel.textContent = formatDate(pickerState.selectedDate, true) || 'Select date';
            if (modalTimeLabel) modalTimeLabel.textContent = pickerState.selectedTime || '00:00';
            if (applyBtn) {
                var canApply = pickerState.context === 'pickup'
                    ? !!(pickerState.selectedDate && pickerState.selectedTime)
                    : !!pickerState.selectedDate;
                applyBtn.disabled = !canApply;
            }
        }

        function applyCalendarSelection() {
            if (pickerState.context === 'pickup') {
                pickupDateInput.value = pickerState.selectedDate;
                pickupTimeInput.value = pickerState.selectedTime;
            } else {
                returnDateInput.value = pickerState.selectedDate;
                returnTimeInput.value = pickerState.selectedTime;
            }
            updateTripSummary();
            updateSeatAvailabilityDisplay();
            closeCalendar();
        }

        function parseDate(iso) {
            var p = iso.split('-');
            if (p.length !== 3) return new Date(today);
            return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        }

        function toIsoDate(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }

        function slotToLabel(slot) {
            return slot;
        }

        form.addEventListener('submit', function (e) {
            var errors = [];

            if (!getSelectedPackageId()) {
                errors.push('Please select a valid package.');
            }

            if (pickupDateInput) {
                var selectedDate = pickupDateInput.value ? new Date(pickupDateInput.value + 'T00:00:00') : null;
                if (!selectedDate || selectedDate < today) {
                    errors.push('Please select a valid future pickup date.');
                }
            }

            if (pickupTimeInput && !pickupTimeInput.value.trim()) {
                errors.push('Please select a pickup time.');
            }

            if (returnDateInput && returnDateInput.value && pickupDateInput && pickupDateInput.value) {
                var pickupDate = new Date(pickupDateInput.value + 'T00:00:00');
                var returnDate = new Date(returnDateInput.value + 'T00:00:00');
                if (returnDate < pickupDate) {
                    errors.push('Drop-off / return date cannot be earlier than pickup date.');
                }
            }

            var pickupInput = form.querySelector('[name="nsb_pickup_address"]');
            if (pickupInput && !pickupInput.value.trim()) {
                errors.push('Please enter a pickup address.');
            }

            var dropoffInput = form.querySelector('[name="nsb_dropoff_address"]');
            if (dropoffInput && !dropoffInput.value.trim()) {
                errors.push('Please enter a drop-off address.');
            }

            if (passengerInput && parseInt(passengerInput.value, 10) < 1) {
                errors.push('Seat count must be at least 1.');
            }

            if (passengerInput && !validatePassengerCapacity()) {
                var availableSeats = getCurrentAvailableSeats();
                errors.push('Only ' + availableSeats + ' seat' + (availableSeats === 1 ? '' : 's') + ' available for the selected pickup time.');
            }

            if (errors.length > 0) {
                e.preventDefault();
                showErrors(errors);
                window.scrollTo({ top: form.getBoundingClientRect().top + window.pageYOffset - 40, behavior: 'smooth' });
                return false;
            }

            var submitBtn = document.getElementById('nsb-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Redirecting to checkout...';
            }
        });

        function showErrors(errors) {
            var oldBox = document.querySelector('.nsb-form-errors');
            if (oldBox) oldBox.remove();

            var box = document.createElement('div');
            box.className = 'nsb-form-errors';
            box.setAttribute('role', 'alert');

            var ul = document.createElement('ul');
            errors.forEach(function (error) {
                var li = document.createElement('li');
                li.textContent = error;
                ul.appendChild(li);
            });

            box.appendChild(ul);
            form.parentNode.insertBefore(box, form);
        }

        onPackageChange();
        updateTripSummary();
        updateSeatAvailabilityDisplay();
    });
})();
