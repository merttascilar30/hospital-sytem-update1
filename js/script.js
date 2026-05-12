/**
 * Hospital Appointment System — client-side behaviour.
 */
(function () {
    'use strict';

    function initCancelConfirmForms() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            var msg = form.getAttribute('data-confirm');
            if (!msg) {
                return;
            }
            if (form.getAttribute('data-confirmed') === '1') {
                return;
            }
            event.preventDefault();
            if (window.confirm(msg)) {
                form.setAttribute('data-confirmed', '1');
                form.submit();
            }
        });
    }

    function initPasswordToggle() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-password-toggle');
                var input = document.getElementById(targetId);
                if (!input) {
                    return;
                }
                var isHidden = input.getAttribute('type') === 'password';
                input.setAttribute('type', isHidden ? 'text' : 'password');
                btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                var labelShow = btn.getAttribute('data-label-show') || 'Show';
                var labelHide = btn.getAttribute('data-label-hide') || 'Hide';
                btn.textContent = isHidden ? labelHide : labelShow;
            });
        });
    }

    function initBookAppointmentPage() {
        var departmentSelect = document.getElementById('department_id');
        var doctorSelect = document.getElementById('doctor_id');
        var dateInput = document.getElementById('appointment_date');
        var timeSlotsContainer = document.getElementById('time-slots');
        var hiddenTimeInput = document.getElementById('appointment_time');

        if (!departmentSelect || !doctorSelect || !hiddenTimeInput) {
            return;
        }

        // One delegated listener: avoids `var` loop closure where every handler saw the last slot (e.g. 16:45).
        if (timeSlotsContainer && !timeSlotsContainer.dataset.timeSlotClickBound) {
            timeSlotsContainer.dataset.timeSlotClickBound = '1';
            timeSlotsContainer.addEventListener('click', function (e) {
                var btn = e.target.closest('button[data-time]');
                if (!btn || btn.disabled) {
                    return;
                }
                var selected = btn.getAttribute('data-time');
                if (!selected) {
                    selected = (btn.textContent || '').trim();
                }
                timeSlotsContainer.querySelectorAll('button[data-time]').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                hiddenTimeInput.value = selected;
            });
        }

        function filterDoctors() {
            var selectedDept = departmentSelect.value;
            var options = doctorSelect.querySelectorAll('option[data-department]');
            options.forEach(function (option) {
                if (!selectedDept || option.getAttribute('data-department') === selectedDept) {
                    option.hidden = false;
                } else {
                    option.hidden = true;
                }
            });
        }

        function clearTimeSelection() {
            hiddenTimeInput.value = '';
            if (timeSlotsContainer) {
                timeSlotsContainer.querySelectorAll('button').forEach(function (btn) {
                    btn.classList.remove('active');
                });
            }
        }

        function generateTimeSlots(bookedTimes, selectedDate) {
            if (!timeSlotsContainer) {
                return;
            }
            timeSlotsContainer.innerHTML = '';

            var startMinutes = 8 * 60;
            var endMinutes = 17 * 60;

            var now = new Date();
            var todayStr = now.toISOString().slice(0, 10);

            for (var m = startMinutes; m < endMinutes; m += 15) {
                var hour = String(Math.floor(m / 60)).padStart(2, '0');
                var minute = String(m % 60).padStart(2, '0');
                var timeStr = hour + ':' + minute;

                var col = document.createElement('div');
                col.className = 'col-4 col-sm-3 col-md-3';

                var slotBtn = document.createElement('button');
                slotBtn.type = 'button';
                slotBtn.className = 'btn btn-sm w-100';
                slotBtn.setAttribute('data-time', timeStr);
                slotBtn.textContent = timeStr;

                var isBooked = bookedTimes.indexOf(timeStr) !== -1;

                var isInPast = false;
                if (selectedDate === todayStr) {
                    var slotDateTime = new Date(selectedDate + 'T' + timeStr + ':00');
                    if (slotDateTime <= now) {
                        isInPast = true;
                    }
                }

                if (isBooked) {
                    slotBtn.classList.add('btn-danger');
                    slotBtn.disabled = true;
                } else if (isInPast) {
                    slotBtn.classList.add('btn-outline-secondary');
                    slotBtn.disabled = true;
                } else {
                    slotBtn.classList.add('btn-success');
                }

                col.appendChild(slotBtn);
                timeSlotsContainer.appendChild(col);
            }
        }

        function loadAvailableSlots() {
            clearTimeSelection();

            var doctorId = doctorSelect ? doctorSelect.value : '';
            var dateVal = dateInput ? dateInput.value : '';

            if (!doctorId || !dateVal) {
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML = '';
                }
                return;
            }

            var url = 'get_available_slots.php?doctor_id=' + encodeURIComponent(doctorId) +
                '&date=' + encodeURIComponent(dateVal);

            fetch(url)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Failed to load slots');
                    }
                    return response.json();
                })
                .then(function (bookedTimes) {
                    generateTimeSlots(Array.isArray(bookedTimes) ? bookedTimes : [], dateVal);
                })
                .catch(function () {
                    if (timeSlotsContainer) {
                        timeSlotsContainer.innerHTML =
                            '<div class="col-12 text-danger small">Unable to load time slots. Please try again later.</div>';
                    }
                });
        }

        departmentSelect.addEventListener('change', function () {
            filterDoctors();
            loadAvailableSlots();
        });
        doctorSelect.addEventListener('change', loadAvailableSlots);
        filterDoctors();

        if (dateInput) {
            dateInput.addEventListener('change', loadAvailableSlots);
        }

        if (doctorSelect.value && dateInput && dateInput.value) {
            loadAvailableSlots();
        }
    }

    function initFormFocusFirstError() {
        var firstInvalid = document.querySelector('.is-invalid');
        if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.focus();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCancelConfirmForms();
        initPasswordToggle();
        initBookAppointmentPage();
        initFormFocusFirstError();
    });
}());
