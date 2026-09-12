(function () {
  'use strict';

  const STORAGE_KEY = 'pv_theme';
  const html = document.documentElement;

  /* ── Apply saved theme on page load ── */
  const saved = localStorage.getItem(STORAGE_KEY) || 'dark';
  applyTheme(saved, false);

  /* ── Toggle button wiring (runs after DOM ready) ── */
  document.addEventListener('DOMContentLoaded', function () {
    wireToggle('themeToggle');
    wireToggle('themeToggleMobile');
    wireToggle('themeToggleUser');

    /* Mobile sidebar */
    const mobileBtn = document.getElementById('mobileSidebarBtn');
    const sidebar   = document.getElementById('adminSidebar');
    if (mobileBtn && sidebar) {
      mobileBtn.addEventListener('click', function () {
        sidebar.classList.toggle('show');
      });
    }

    /* Admin sidebar collapse on desktop */
    const colBtn = document.getElementById('sidebarCollapseBtn');
    if (colBtn && sidebar) {
      let collapsed = false;
      colBtn.addEventListener('click', function () {
        collapsed = !collapsed;
        sidebar.classList.toggle('sidebar-collapsed', collapsed);
        colBtn.querySelector('i').className = collapsed
          ? 'bi bi-layout-sidebar' : 'bi bi-layout-sidebar-reverse';
      });
    }
  });

  function wireToggle(id) {
    const btn = document.getElementById(id);
    if (!btn) return;
    updateIcon(btn, html.getAttribute('data-bs-theme') || 'dark');
    btn.addEventListener('click', function () {
      const current = html.getAttribute('data-bs-theme') || 'dark';
      const next    = current === 'dark' ? 'light' : 'dark';
      applyTheme(next, true);
      updateIcon(btn, next);
      /* sync all other toggle buttons */
      ['themeToggle','themeToggleMobile','themeToggleUser'].forEach(function(tid){
        const b = document.getElementById(tid);
        if (b && b !== btn) updateIcon(b, next);
      });
    });
  }

  function applyTheme(theme, save) {
    html.setAttribute('data-bs-theme', theme);
    if (save) localStorage.setItem(STORAGE_KEY, theme);
  }

  function updateIcon(btn, theme) {
    const i = btn.querySelector('i');
    if (!i) return;
    i.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    btn.title    = theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
  }
})();
