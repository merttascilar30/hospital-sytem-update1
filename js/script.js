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

    function bindTimeSlotGridClick(timeSlotsContainer, hiddenTimeInput) {
        if (!timeSlotsContainer || !hiddenTimeInput || timeSlotsContainer.dataset.timeSlotClickBound) {
            return;
        }
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

    function clearTimeSelection(timeSlotsContainer, hiddenTimeInput) {
        if (hiddenTimeInput) {
            hiddenTimeInput.value = '';
        }
        if (timeSlotsContainer) {
            timeSlotsContainer.querySelectorAll('button').forEach(function (btn) {
                btn.classList.remove('active');
            });
        }
    }

    function selectTimeSlotIfAvailable(timeSlotsContainer, hiddenTimeInput, timeStr) {
        if (!timeSlotsContainer || !hiddenTimeInput || !timeStr) {
            return false;
        }
        var btn = timeSlotsContainer.querySelector('button[data-time="' + timeStr + '"]');
        if (!btn || btn.disabled) {
            return false;
        }
        timeSlotsContainer.querySelectorAll('button[data-time]').forEach(function (b) {
            b.classList.remove('active');
        });
        btn.classList.add('active');
        hiddenTimeInput.value = timeStr;
        return true;
    }

    function generateTimeSlots(timeSlotsContainer, bookedTimes, selectedDate) {
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

    /**
     * @param {object} options
     * @param {HTMLInputElement|null} options.dateInput
     * @param {HTMLElement|null} options.timeSlotsContainer
     * @param {HTMLInputElement|null} options.hiddenTimeInput
     * @param {function(): string} options.getDoctorId
     * @param {function(): string} [options.getExcludeAppointmentId]
     * @param {boolean} [options.preserveHiddenTime] Keep hidden value across fetch; re-select after render.
     */
    function loadAppointmentTimeSlots(options) {
        var dateInput = options.dateInput;
        var timeSlotsContainer = options.timeSlotsContainer;
        var hiddenTimeInput = options.hiddenTimeInput;
        var getDoctorId = options.getDoctorId;
        var getExcludeAppointmentId = options.getExcludeAppointmentId || function () {
            return '';
        };
        var preserveHiddenTime = !!options.preserveHiddenTime;

        var savedTime = '';
        if (preserveHiddenTime && hiddenTimeInput) {
            savedTime = (hiddenTimeInput.value || '').trim();
        }

        if (!preserveHiddenTime) {
            clearTimeSelection(timeSlotsContainer, hiddenTimeInput);
        } else if (timeSlotsContainer) {
            timeSlotsContainer.querySelectorAll('button').forEach(function (b) {
                b.classList.remove('active');
            });
        }

        var doctorId = getDoctorId ? getDoctorId() : '';
        var dateVal = dateInput ? dateInput.value : '';

        if (!doctorId || !dateVal) {
            if (timeSlotsContainer) {
                timeSlotsContainer.innerHTML = '';
            }
            if (!preserveHiddenTime && hiddenTimeInput) {
                hiddenTimeInput.value = '';
            }
            return;
        }

        var url = 'get_available_slots.php?doctor_id=' + encodeURIComponent(doctorId) +
            '&date=' + encodeURIComponent(dateVal);
        var excludeId = parseInt(getExcludeAppointmentId(), 10);
        if (excludeId > 0) {
            url += '&exclude_appointment_id=' + encodeURIComponent(String(excludeId));
        }

        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load slots');
                }
                return response.json();
            })
            .then(function (bookedTimes) {
                generateTimeSlots(timeSlotsContainer, Array.isArray(bookedTimes) ? bookedTimes : [], dateVal);
                if (preserveHiddenTime && savedTime && hiddenTimeInput) {
                    if (!selectTimeSlotIfAvailable(timeSlotsContainer, hiddenTimeInput, savedTime)) {
                        hiddenTimeInput.value = '';
                    }
                }
            })
            .catch(function () {
                if (timeSlotsContainer) {
                    timeSlotsContainer.innerHTML =
                        '<div class="col-12 text-danger small">Unable to load time slots. Please try again later.</div>';
                }
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

        bindTimeSlotGridClick(timeSlotsContainer, hiddenTimeInput);

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

        function loadSlots(preserveHiddenTime) {
            loadAppointmentTimeSlots({
                dateInput: dateInput,
                timeSlotsContainer: timeSlotsContainer,
                hiddenTimeInput: hiddenTimeInput,
                getDoctorId: function () {
                    return doctorSelect ? doctorSelect.value : '';
                },
                preserveHiddenTime: !!preserveHiddenTime
            });
        }

        departmentSelect.addEventListener('change', function () {
            filterDoctors();
            loadSlots(false);
        });
        doctorSelect.addEventListener('change', function () {
            loadSlots(false);
        });
        filterDoctors();

        if (dateInput) {
            dateInput.addEventListener('change', function () {
                loadSlots(false);
            });
        }

        if (doctorSelect.value && dateInput && dateInput.value) {
            loadSlots(!!(hiddenTimeInput.value && hiddenTimeInput.value.trim()));
        }
    }

    function initEditAppointmentPage() {
        var meta = document.getElementById('edit-appointment-meta');
        var dateInput = document.getElementById('appointment_date');
        var timeSlotsContainer = document.getElementById('time-slots');
        var hiddenTimeInput = document.getElementById('appointment_time');

        if (!meta || !dateInput || !hiddenTimeInput || !timeSlotsContainer) {
            return;
        }

        bindTimeSlotGridClick(timeSlotsContainer, hiddenTimeInput);

        var doctorIdFixed = (meta.getAttribute('data-doctor-id') || '').trim();
        var appointmentIdFixed = (meta.getAttribute('data-appointment-id') || '').trim();

        function loadSlots(preserveHiddenTime) {
            loadAppointmentTimeSlots({
                dateInput: dateInput,
                timeSlotsContainer: timeSlotsContainer,
                hiddenTimeInput: hiddenTimeInput,
                getDoctorId: function () {
                    return doctorIdFixed;
                },
                getExcludeAppointmentId: function () {
                    return appointmentIdFixed;
                },
                preserveHiddenTime: !!preserveHiddenTime
            });
        }

        dateInput.addEventListener('change', function () {
            hiddenTimeInput.value = '';
            loadSlots(false);
        });

        if (doctorIdFixed && dateInput.value) {
            loadSlots(true);
        }
    }

    function initLoginRoleToggle() {
        var roleInput = document.getElementById('login_role');
        var patientFields = document.getElementById('patient-login-fields');
        var staffFields = document.getElementById('staff-login-fields');
        var patientLinks = document.getElementById('patient-extra-links');
        var patientRegister = document.getElementById('patient-register-prompt');
        var staffNote = document.getElementById('staff-register-note');
        var emailInput = document.getElementById('email');
        var usernameInput = document.getElementById('username');
        var tabs = document.querySelectorAll('[data-login-role]');

        if (!roleInput || !tabs.length) {
            return;
        }

        function setRole(role) {
            roleInput.value = role;
            tabs.forEach(function (tab) {
                var isActive = tab.getAttribute('data-login-role') === role;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            var isPatient = role === 'patient';
            if (patientFields) {
                patientFields.classList.toggle('d-none', !isPatient);
            }
            if (staffFields) {
                staffFields.classList.toggle('d-none', isPatient);
            }
            if (patientLinks) {
                patientLinks.classList.toggle('d-none', !isPatient);
            }
            if (patientRegister) {
                patientRegister.classList.toggle('d-none', !isPatient);
            }
            if (staffNote) {
                staffNote.classList.toggle('d-none', isPatient);
            }
            if (emailInput) {
                emailInput.required = isPatient;
            }
            if (usernameInput) {
                usernameInput.required = !isPatient;
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setRole(tab.getAttribute('data-login-role'));
            });
        });

        setRole(roleInput.value || 'patient');
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
        initLoginRoleToggle();
        initBookAppointmentPage();
        initEditAppointmentPage();
        initFormFocusFirstError();
    });
}());
