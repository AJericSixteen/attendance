(function () {
  function applyToggleIcon(theme) {
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
    applyToggleIcon(current);

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        try {
          localStorage.setItem('attendance-theme', next);
        } catch (e) {
          /* localStorage unavailable */
        }
        applyToggleIcon(next);
      });
    });
  });
})();
