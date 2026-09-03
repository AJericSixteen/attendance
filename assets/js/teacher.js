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

  let currentSubjectId = null;
  let currentCard = null;
  let currentScanCount = 0;
  let mediaStream = null;
  let scanLoopHandle = null;
  let lastCode = null;
  let lastScanTime = 0;
  let cameraOn = false;
  const SCAN_COOLDOWN_MS = 3000;

  function renderRow(index, record) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${index}</td><td>${record.student_number}</td><td>${record.surname}</td>`;
    tableBody.appendChild(tr);
  }

  function renderTable(records) {
    tableBody.innerHTML = '';
    if (records.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="3" class="text-muted">No attendance scanned yet today.</td></tr>';
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

  function startCamera() {
    cameraError.classList.add('d-none');
    cameraOffMessage.classList.add('d-none');
    video.classList.remove('d-none');
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
      .then((stream) => {
        mediaStream = stream;
        video.srcObject = stream;
        video.setAttribute('playsinline', true);
        video.play();
        scanLoopHandle = requestAnimationFrame(scanFrame);
      })
      .catch(() => {
        cameraError.classList.remove('d-none');
        video.classList.add('d-none');
      });
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
  }

  function setCameraOn(on) {
    cameraOn = on;
    if (on) {
      cameraToggleBtn.classList.add('is-on');
      cameraToggleBtn.setAttribute('aria-label', 'Turn camera off');
      cameraToggleBtn.innerHTML = '<i class="bi bi-camera-video"></i>';
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
      setScanStatus('Camera is off. Turn it on to scan, or add attendance manually.', 'info');
    }
  }

  function scanFrame() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height);

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
          return;
        }
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

  document.querySelectorAll('.subject-card').forEach((card) => {
    card.addEventListener('click', () => openAttendanceModal(card));
    card.addEventListener('keydown', (e) => {
      if (e.target !== card) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openAttendanceModal(card);
      }
    });
  });

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
