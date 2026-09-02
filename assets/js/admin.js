document.addEventListener('DOMContentLoaded', () => {
  const manageModalEl = document.getElementById('manageTeacherModal');
  const manageModal = new bootstrap.Modal(manageModalEl);

  const nameEl = document.getElementById('manageTeacherName');
  const usernameEl = document.getElementById('manageTeacherUsername');
  const emailEl = document.getElementById('manageTeacherEmail');
  const statusBadge = document.getElementById('manageTeacherStatusBadge');
  const changePasswordTeacherId = document.getElementById('changePasswordTeacherId');
  const activateTeacherId = document.getElementById('activateTeacherId');
  const deactivateTeacherId = document.getElementById('deactivateTeacherId');
  const activateSection = document.getElementById('activateSection');
  const deactivateSection = document.getElementById('deactivateSection');
  const showDeactivateFormBtn = document.getElementById('showDeactivateFormBtn');
  const cancelDeactivateBtn = document.getElementById('cancelDeactivateBtn');
  const deactivateForm = document.getElementById('deactivateForm');
  const adminPasswordInput = document.getElementById('admin_password');

  const deleteZone = document.getElementById('deleteZone');
  const deleteTeacherId = document.getElementById('deleteTeacherId');
  const showDeleteFormBtn = document.getElementById('showDeleteFormBtn');
  const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
  const deleteForm = document.getElementById('deleteForm');
  const deleteAdminPasswordInput = document.getElementById('delete_admin_password');

  function resetDeactivateForm() {
    deactivateForm.classList.add('d-none');
    showDeactivateFormBtn.classList.remove('d-none');
    adminPasswordInput.value = '';
  }

  function resetDeleteForm() {
    deleteForm.classList.add('d-none');
    showDeleteFormBtn.classList.remove('d-none');
    deleteAdminPasswordInput.value = '';
  }

  function openTeacherModal(card) {
    const { teacherId, fullName, username, email, active } = card.dataset;
    const isActive = active === '1';

    nameEl.textContent = fullName;
    usernameEl.textContent = `@${username}`;
    emailEl.textContent = email;
    statusBadge.textContent = isActive ? 'Active' : 'Deactivated';
    statusBadge.className = `status-badge ${isActive ? 'status-badge--active' : 'status-badge--inactive'}`;

    changePasswordTeacherId.value = teacherId;
    activateTeacherId.value = teacherId;
    deactivateTeacherId.value = teacherId;
    deleteTeacherId.value = teacherId;

    activateSection.classList.toggle('d-none', isActive);
    deactivateSection.classList.toggle('d-none', !isActive);
    deleteZone.classList.toggle('d-none', isActive);
    resetDeactivateForm();
    resetDeleteForm();

    manageModal.show();
  }

  document.querySelectorAll('.teacher-card').forEach((card) => {
    card.addEventListener('click', () => openTeacherModal(card));
  });

  showDeactivateFormBtn.addEventListener('click', () => {
    deactivateForm.classList.remove('d-none');
    showDeactivateFormBtn.classList.add('d-none');
    adminPasswordInput.focus();
  });

  cancelDeactivateBtn.addEventListener('click', () => {
    resetDeactivateForm();
  });

  showDeleteFormBtn.addEventListener('click', () => {
    deleteForm.classList.remove('d-none');
    showDeleteFormBtn.classList.add('d-none');
    deleteAdminPasswordInput.focus();
  });

  cancelDeleteBtn.addEventListener('click', () => {
    resetDeleteForm();
  });

  if (window.REOPEN_TEACHER_ID) {
    const card = document.querySelector(`.teacher-card[data-teacher-id="${window.REOPEN_TEACHER_ID}"]`);
    if (card) openTeacherModal(card);
  }
});
