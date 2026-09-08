document.addEventListener('DOMContentLoaded', () => {
  const attendanceModalEl = document.getElementById('attendanceModal');
  const attendanceModal   = new bootstrap.Modal(attendanceModalEl);

  const manageSubjectModalEl = document.getElementById('manageSubjectModal');
  const manageSubjectModal   = new bootstrap.Modal(manageSubjectModalEl);

  const video          = document.getElementById('cameraVideo');
  const canvas          = document.getElementById('cameraCanvas');
  const cameraError      = document.getElementById('cameraError');
  const cameraOffMessage = document.getElementById('cameraOffMessage');
  const cameraToggleBtn  = document.getElementById('cameraToggleBtn');
  const torchBtn         = document.getElementById('torchToggleBtn');
  const cameraSourceCaret = document.getElementById('cameraSourceCaret');
  const cameraSourceMenu  = document.getElementById('cameraSourceMenu');
  const cameraColumn     = document.getElementById('cameraColumn');
  const scanStatus      = document.getElementById('scanStatus');
  const tableBody        = document.getElementById('attendanceTableBody');
  const exportBtn        = document.getElementById('exportBtn');
  const exportDateFilter = document.getElementById('exportDateFilter');
  const exportDateGroup  = document.getElementById('exportDateGroup');
  const modalTitle       = document.getElementById('attendanceModalTitle');
  const manualEntryBtn   = document.getElementById('manualEntryBtn');
  const manualEntrySection = document.getElementById('manualEntrySection');
  const manualStudentNumber = document.getElementById('manualStudentNumber');
  const manualSurname    = document.getElementById('manualSurname');
  const manualSaveBtn    = document.getElementById('manualSaveBtn');
  const inactiveSubjectNotice = document.getElementById('inactiveSubjectNotice');

  const manageSubjectStatusBadge = document.getElementById('manageSubjectStatusBadge');
  const editSubjectId   = document.getElementById('editSubjectId');
  const editSubjectCode = document.getElementById('editSubjectCode');
  const editSubjectName = document.getElementById('editSubjectName');
  const editSection     = document.getElementById('editSection');
  const activateSubjectSection   = document.getElementById('activateSubjectSection');
  const activateSubjectId        = document.getElementById('activateSubjectId');
  const deactivateSubjectSection = document.getElementById('deactivateSubjectSection');
  const deactivateSubjectId      = document.getElementById('deactivateSubjectId');
  const deleteSubjectZone = document.getElementById('deleteSubjectZone');
  const deleteSubjectId   = document.getElementById('deleteSubjectId');

  const cameraTip        = document.getElementById('cameraTip');
  const DEFAULT_CAMERA_TIP = cameraTip.textContent;

  let currentSubjectId = null;
  let currentCard = null;
  let currentScanCount = 0;
  let mediaStream = null;
  let scanLoopHandle = null;
  let lastCode = null;
  let lastScanTime = 0;
  let cameraOn = false;
  let torchTrack = null;
  let torchOn = false;
  const SCAN_COOLDOWN_MS = 3000;
  const canvasCtx = canvas.getContext('2d', { willReadFrequently: true });

  let dragCard = null;
  let dragPlaceholder = null;
  let dragPointerId = null;
  let dragOffsetX = 0;
  let dragOffsetY = 0;
  let dragSuppressClick = false;
  let longPressTimer = null;
  let pressStartX = 0;
  let pressStartY = 0;
  const LONG_PRESS_MS = 380;
  const PRESS_MOVE_CANCEL_PX = 10;

  function renumberRows() {
    tableBody.querySelectorAll('tr[data-attendance-id]').forEach((tr, i) => {
      tr.querySelector('[data-row-index]').textContent = i + 1;
    });
    currentScanCount = tableBody.querySelectorAll('tr[data-attendance-id]').length;
    updateCardCount(currentScanCount);
  }

  function removeRow(attendanceId) {
    fetch('actions/delete_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ attendance_id: attendanceId }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          setScanStatus(data.message || 'Could not remove that record.', 'error');
          return;
        }
        const tr = tableBody.querySelector(`tr[data-attendance-id="${attendanceId}"]`);
        if (tr) tr.remove();
        if (!tableBody.querySelector('tr[data-attendance-id]')) {
          tableBody.innerHTML = '<tr><td colspan="4" class="text-muted">No attendance scanned yet today.</td></tr>';
          currentScanCount = 0;
          updateCardCount(0);
        } else {
          renumberRows();
        }
      })
      .catch(() => {
        setScanStatus('Network error while removing record.', 'error');
      });
  }

  function renderRow(index, record) {
    const tr = document.createElement('tr');
    tr.dataset.attendanceId = record.id;
    tr.innerHTML = `<td data-row-index>${index}</td><td>${record.student_number}</td><td>${record.surname}</td>
      <td class="text-end">
        <button type="button" class="btn btn-outline-danger btn-sm remove-attendance-btn" title="Remove this entry">
          <i class="bi bi-trash"></i>
        </button>
      </td>`;
    tr.querySelector('.remove-attendance-btn').addEventListener('click', () => {
      if (confirm(`Remove ${record.surname} (${record.student_number}) from today's attendance?`)) {
        removeRow(record.id);
      }
    });
    tableBody.appendChild(tr);
  }

  function renderTable(records) {
    tableBody.innerHTML = '';
    if (records.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="4" class="text-muted">No attendance scanned yet today.</td></tr>';
      return;
    }
    records.forEach((record, i) => renderRow(i + 1, record));
  }

  function setScanStatus(message, type) {
    scanStatus.textContent = message;
    scanStatus.className = `scan-status scan-status--${type}`;
  }

  function updateCardCount(count) {
    if (!currentCard) return;
    const stat = currentCard.querySelector('[data-scan-stat]');
    if (stat) stat.textContent = `${count} Present Today`;
  }

  function startCamera(deviceId) {
    cameraError.classList.add('d-none');
    cameraOffMessage.classList.add('d-none');
    video.classList.remove('d-none');

    const videoConstraints = { width: { ideal: 1920 }, height: { ideal: 1080 } };
    if (deviceId) {
      videoConstraints.deviceId = { exact: deviceId };
    } else {
      // No rear camera on a laptop, so just prefer the highest-res facing option;
      // this is only a hint and won't fail if the device doesn't have one.
      videoConstraints.facingMode = 'user';
    }

    navigator.mediaDevices.getUserMedia({ video: videoConstraints, audio: false })
      .then((stream) => {
        mediaStream = stream;
        video.srcObject = stream;
        video.setAttribute('playsinline', true);
        video.play();
        applyAutoFocus(stream);
        updateTorchAvailability(stream);
        scanLoopHandle = requestAnimationFrame(scanFrame);
        if (!deviceId) populateCameraList(stream);
      })
      .catch(() => {
        cameraError.classList.remove('d-none');
        video.classList.add('d-none');
      });
  }

  function isLikelyInfraredCamera(label) {
    return /\bir\b|infrared/i.test(label || '');
  }

  function populateCameraList(activeStream) {
    navigator.mediaDevices.enumerateDevices()
      .then((devices) => {
        const cameras = devices.filter((d) => d.kind === 'videoinput');
        cameraSourceMenu.innerHTML = '';

        if (cameras.length <= 1) {
          cameraSourceCaret.classList.add('d-none');
          return;
        }

        const activeTrack = activeStream.getVideoTracks()[0];
        const activeSettings = activeTrack && typeof activeTrack.getSettings === 'function' ? activeTrack.getSettings() : {};
        const activeId = activeSettings.deviceId || '';
        const activeCam = cameras.find((c) => c.deviceId === activeId);

        // Windows laptops often expose a Windows Hello infrared camera alongside the
        // regular webcam; the browser sometimes auto-picks it, and IR sensors read
        // printed QR codes poorly. Switch to a non-IR camera when that happens.
        let preferredId = activeId;
        if (!activeCam || isLikelyInfraredCamera(activeCam.label)) {
          const alt = cameras.find((c) => !isLikelyInfraredCamera(c.label));
          if (alt) preferredId = alt.deviceId;
        }
        preferredId = preferredId || cameras[0].deviceId;

        cameras.forEach((cam, i) => {
          const li = document.createElement('li');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'dropdown-item' + (cam.deviceId === preferredId ? ' active' : '');
          btn.innerHTML = `<i class="bi bi-check-lg"></i><span>${cam.label || `Camera ${i + 1}`}</span>`;
          btn.addEventListener('click', () => {
            if (btn.classList.contains('active')) return;
            cameraSourceMenu.querySelectorAll('.dropdown-item').forEach((el) => el.classList.remove('active'));
            btn.classList.add('active');
            stopCamera();
            startCamera(cam.deviceId);
          });
          li.appendChild(btn);
          cameraSourceMenu.appendChild(li);
        });

        cameraSourceCaret.classList.remove('d-none');

        if (preferredId !== activeId) {
          stopCamera();
          startCamera(preferredId);
        }
      })
      .catch(() => {});
  }

  function applyAutoFocus(stream) {
    const track = stream.getVideoTracks()[0];
    if (!track || typeof track.getCapabilities !== 'function') return;
    try {
      const capabilities = track.getCapabilities();
      if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
        track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
      }
    } catch (e) {
      // Focus control isn't supported on this browser/device; ignore.
    }
  }

  function updateTorchAvailability(stream) {
    const track = stream.getVideoTracks()[0];
    torchTrack = null;
    torchOn = false;
    torchBtn.classList.add('d-none');
    torchBtn.classList.remove('is-on');
    if (!track || typeof track.getCapabilities !== 'function') return;
    try {
      const capabilities = track.getCapabilities();
      if (capabilities.torch) {
        torchTrack = track;
        torchBtn.classList.remove('d-none');
      }
    } catch (e) {
      // Torch control isn't supported on this browser/device; ignore.
    }
  }

  function setTorch(on) {
    if (!torchTrack) return;
    torchTrack.applyConstraints({ advanced: [{ torch: on }] })
      .then(() => {
        torchOn = on;
        torchBtn.classList.toggle('is-on', on);
      })
      .catch(() => {});
  }

  function stopCamera() {
    if (scanLoopHandle) {
      cancelAnimationFrame(scanLoopHandle);
      scanLoopHandle = null;
    }
    if (mediaStream) {
      mediaStream.getTracks().forEach((track) => track.stop());
      mediaStream = null;
    }
    video.srcObject = null;
    lastCode = null;
    lastScanTime = 0;
    torchTrack = null;
    torchOn = false;
    torchBtn.classList.add('d-none');
    torchBtn.classList.remove('is-on');
  }

  function setCameraOn(on) {
    cameraOn = on;
    if (on) {
      cameraToggleBtn.classList.add('is-on');
      cameraToggleBtn.setAttribute('aria-label', 'Turn camera off');
      cameraToggleBtn.innerHTML = '<i class="bi bi-camera-video"></i>';
      hideQrFormatMonitor();
      startCamera();
      setScanStatus('Point a student\'s QR code at the camera.', 'info');
    } else {
      cameraToggleBtn.classList.remove('is-on');
      cameraToggleBtn.setAttribute('aria-label', 'Turn camera on');
      cameraToggleBtn.innerHTML = '<i class="bi bi-camera-video-off"></i>';
      stopCamera();
      video.classList.add('d-none');
      cameraError.classList.add('d-none');
      cameraOffMessage.classList.remove('d-none');
      cameraSourceCaret.classList.add('d-none');
      setScanStatus('Camera is off. Turn it on to scan, or add attendance manually.', 'info');
    }
  }

  function scanFrame() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvasCtx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = canvasCtx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });

      if (code && code.data) {
        const now = Date.now();
        const isSameRecentCode = code.data === lastCode && (now - lastScanTime) < SCAN_COOLDOWN_MS;
        if (!isSameRecentCode) {
          lastCode = code.data;
          lastScanTime = now;
          handleScan(code.data);
        }
      }
    }
    scanLoopHandle = requestAnimationFrame(scanFrame);
  }

  function hideQrFormatMonitor() {
    cameraTip.textContent = DEFAULT_CAMERA_TIP;
    cameraTip.classList.remove('camera-tip--error');
  }

  function showQrFormatMonitor(rawText) {
    cameraTip.textContent = `QR code read: "${rawText || '(empty)'}" — expected format is StudentNumber_SURNAME (e.g. 06-2026-123456_DELACRUZ).`;
    cameraTip.classList.add('camera-tip--error');
  }

  function handleScan(qrText) {
    setScanStatus(`Scanning: ${qrText}...`, 'info');

    fetch('actions/scan_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ subject_id: currentSubjectId, qr_text: qrText }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) {
          setScanStatus(data.message || 'Could not read that QR code.', 'error');
          if (data.raw_qr_text !== undefined) {
            showQrFormatMonitor(data.raw_qr_text);
          }
          return;
        }
        hideQrFormatMonitor();
        if (data.duplicate) {
          setScanStatus(`${data.record.surname} is already marked present today.`, 'warning');
          return;
        }
        const emptyRow = tableBody.querySelector('td[colspan]');
        if (emptyRow) tableBody.innerHTML = '';
        currentScanCount += 1;
        renderRow(currentScanCount, data.record);
        updateCardCount(currentScanCount);
        setScanStatus(`${data.record.surname} marked present.`, 'success');
      })
      .catch(() => {
        setScanStatus('Network error while saving scan.', 'error');
      });
  }

  function updateExportHref() {
    let href = `actions/export_attendance.php?subject_id=${currentSubjectId}`;
    if (exportDateFilter.value) {
      href += `&date=${exportDateFilter.value}`;
    }
    exportBtn.href = href;
  }

  function openAttendanceModal(card) {
    const subjectId = card.dataset.subjectId;
    const isActive = card.dataset.active === '1';

    currentSubjectId = subjectId;
    currentCard = card;
    currentScanCount = 0;
    modalTitle.textContent = 'Loading...';
    tableBody.innerHTML = '<tr><td colspan="3" class="text-muted">Loading...</td></tr>';
    exportDateFilter.value = '';
    updateExportHref();

    inactiveSubjectNotice.classList.toggle('d-none', isActive);
    cameraColumn.classList.toggle('d-none', !isActive);
    manualEntryBtn.classList.toggle('d-none', !isActive);

    attendanceModal.show();

    fetch(`actions/get_attendance.php?id=${subjectId}`)
      .then((res) => res.json())
      .then((data) => {
        if (!data.success) return;
        modalTitle.textContent = `${data.subject.subject_code} - ${data.subject.subject_name} (${data.subject.section})`;
        currentScanCount = data.records.length;
        renderTable(data.records);
      });
  }

  function openManageSubjectModal(card) {
    const { subjectId, subjectCode, subjectName, section, active } = card.dataset;
    const isActive = active === '1';

    editSubjectId.value = subjectId;
    editSubjectCode.value = subjectCode;
    editSubjectName.value = subjectName;
    editSection.value = section;

    manageSubjectStatusBadge.textContent = isActive ? 'Active' : 'Deactivated';
    manageSubjectStatusBadge.className = `status-badge ${isActive ? 'status-badge--active' : 'status-badge--inactive'}`;

    activateSubjectId.value = subjectId;
    deactivateSubjectId.value = subjectId;
    deleteSubjectId.value = subjectId;

    activateSubjectSection.classList.toggle('d-none', isActive);
    deactivateSubjectSection.classList.toggle('d-none', !isActive);
    deleteSubjectZone.classList.toggle('d-none', isActive);

    manageSubjectModal.show();
  }

  function initCardReordering() {
    const cardGrid = document.querySelector('.card-grid');
    if (!cardGrid) return;

    function startCardDrag(card, e) {
      dragCard = card;
      dragPointerId = e.pointerId;
      try { card.setPointerCapture(dragPointerId); } catch (err) { /* not supported, ignore */ }

      const rect = card.getBoundingClientRect();
      dragOffsetX = e.clientX - rect.left;
      dragOffsetY = e.clientY - rect.top;

      dragPlaceholder = document.createElement('div');
      dragPlaceholder.className = 'subject-card-placeholder';
      dragPlaceholder.style.width = `${rect.width}px`;
      dragPlaceholder.style.height = `${rect.height}px`;
      card.parentNode.insertBefore(dragPlaceholder, card);

      card.classList.add('is-dragging');
      card.style.width = `${rect.width}px`;
      card.style.left = `${rect.left}px`;
      card.style.top = `${rect.top}px`;
      document.body.appendChild(card);
      cardGrid.classList.add('is-reordering');

      if (navigator.vibrate) {
        try { navigator.vibrate(12); } catch (err) { /* not supported, ignore */ }
      }
    }

    function moveCardDrag(e) {
      dragCard.style.left = `${e.clientX - dragOffsetX}px`;
      dragCard.style.top = `${e.clientY - dragOffsetY}px`;

      dragCard.style.visibility = 'hidden';
      const target = document.elementFromPoint(e.clientX, e.clientY);
      dragCard.style.visibility = '';
      if (!target) return;

      const overCard = target.closest('.subject-card');
      if (overCard && overCard !== dragCard) {
        const overRect = overCard.getBoundingClientRect();
        const isAfter = e.clientY > overRect.top + overRect.height / 2;
        overCard.parentNode.insertBefore(dragPlaceholder, isAfter ? overCard.nextSibling : overCard);
      } else if (target === cardGrid) {
        cardGrid.appendChild(dragPlaceholder);
      }
    }

    function saveCardOrder() {
      const order = Array.from(cardGrid.querySelectorAll('.subject-card')).map((c) => c.dataset.subjectId);
      fetch('actions/reorder_subjects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order }),
      }).catch(() => {});
    }

    function finishCardDrag() {
      const card = dragCard;
      dragPlaceholder.parentNode.insertBefore(card, dragPlaceholder);
      dragPlaceholder.remove();
      dragPlaceholder = null;

      card.classList.remove('is-dragging');
      card.style.position = '';
      card.style.left = '';
      card.style.top = '';
      card.style.width = '';
      cardGrid.classList.remove('is-reordering');

      dragCard = null;
      dragPointerId = null;

      saveCardOrder();
      setTimeout(() => { dragSuppressClick = false; }, 0);
    }

    cardGrid.querySelectorAll('.subject-card').forEach((card) => {
      card.addEventListener('pointerdown', (e) => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        if (e.target.closest('[data-manage-subject]')) return;

        pressStartX = e.clientX;
        pressStartY = e.clientY;
        dragSuppressClick = false;
        clearTimeout(longPressTimer);
        longPressTimer = setTimeout(() => startCardDrag(card, e), LONG_PRESS_MS);
      });
    });

    document.addEventListener('pointermove', (e) => {
      if (dragCard) {
        if (e.pointerId !== dragPointerId) return;
        dragSuppressClick = true;
        moveCardDrag(e);
        return;
      }
      if (longPressTimer && (Math.abs(e.clientX - pressStartX) > PRESS_MOVE_CANCEL_PX || Math.abs(e.clientY - pressStartY) > PRESS_MOVE_CANCEL_PX)) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
      }
    });

    document.addEventListener('pointerup', (e) => {
      clearTimeout(longPressTimer);
      longPressTimer = null;
      if (dragCard && e.pointerId === dragPointerId) finishCardDrag();
    });

    document.addEventListener('pointercancel', (e) => {
      clearTimeout(longPressTimer);
      longPressTimer = null;
      if (dragCard && e.pointerId === dragPointerId) finishCardDrag();
    });
  }

  document.querySelectorAll('.subject-card').forEach((card) => {
    card.addEventListener('click', () => {
      if (dragSuppressClick) {
        dragSuppressClick = false;
        return;
      }
      openAttendanceModal(card);
    });
    card.addEventListener('keydown', (e) => {
      if (e.target !== card) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openAttendanceModal(card);
      }
    });
  });

  initCardReordering();

  document.querySelectorAll('[data-manage-subject]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openManageSubjectModal(btn.closest('.subject-card'));
    });
  });

  exportDateFilter.addEventListener('change', updateExportHref);

  exportDateGroup.addEventListener('click', (e) => {
    if (e.target === exportDateFilter) return;
    if (typeof exportDateFilter.showPicker === 'function') {
      exportDateFilter.showPicker();
    } else {
      exportDateFilter.focus();
    }
  });

  cameraToggleBtn.addEventListener('click', () => {
    setCameraOn(!cameraOn);
  });

  torchBtn.addEventListener('click', () => {
    setTorch(!torchOn);
  });

  manualEntryBtn.addEventListener('click', () => {
    manualEntrySection.classList.toggle('d-none');
    if (!manualEntrySection.classList.contains('d-none')) {
      manualStudentNumber.focus();
    }
  });

  function formatStudentNumber(value) {
    const digits = value.replace(/\D/g, '').slice(0, 12);
    if (digits.length > 6) {
      return `${digits.slice(0, 2)}-${digits.slice(2, 6)}-${digits.slice(6)}`;
    }
    if (digits.length > 2) {
      return `${digits.slice(0, 2)}-${digits.slice(2)}`;
    }
    return digits;
  }

  manualStudentNumber.addEventListener('input', () => {
    manualStudentNumber.value = formatStudentNumber(manualStudentNumber.value);
  });

  manualSurname.addEventListener('input', () => {
    manualSurname.value = manualSurname.value.toUpperCase();
  });

  manualSaveBtn.addEventListener('click', () => {
    const studentNumber = manualStudentNumber.value.trim();
    const surname = manualSurname.value.trim().toUpperCase();

    if (!studentNumber || !surname) {
      setScanStatus('Please enter both Student Number and Surname.', 'error');
      return;
    }

    handleScan(`${studentNumber}_${surname}`);
    manualStudentNumber.value = '';
    manualSurname.value = '';
    manualStudentNumber.focus();
  });

  attendanceModalEl.addEventListener('shown.bs.modal', () => {
    manualEntrySection.classList.add('d-none');
    hideQrFormatMonitor();
    setCameraOn(false);
  });

  attendanceModalEl.addEventListener('hidden.bs.modal', () => {
    stopCamera();
  });

  if (window.REOPEN_SUBJECT_ID) {
    const card = document.querySelector(`.subject-card[data-subject-id="${window.REOPEN_SUBJECT_ID}"]`);
    if (card) openManageSubjectModal(card);
  }
});
