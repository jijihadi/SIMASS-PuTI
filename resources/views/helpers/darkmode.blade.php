<script>
  // ===== Dark Mode (Instant Theme Switch without staggered transition lag) =====
  function toggleDark() {
    const root = document.documentElement;
    root.classList.add('theme-switching');
    const isDark = root.classList.toggle('dark');
    root.style.colorScheme = isDark ? 'dark' : 'light';
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    // Force style flush then re-enable transitions on next frame
    void root.offsetWidth;
    requestAnimationFrame(() => root.classList.remove('theme-switching'));
    if (window.statsChart) updateChartTheme();
  }

  // Default page is pure white (#FFFFFF / light mode first) per Don Norman & Zander Whitehurst guidelines
  const savedTheme = localStorage.getItem('theme');
  const shouldUseDark = savedTheme === 'dark';
  if (shouldUseDark) {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }
  document.documentElement.style.colorScheme = shouldUseDark ? 'dark' : 'light';
</script>
