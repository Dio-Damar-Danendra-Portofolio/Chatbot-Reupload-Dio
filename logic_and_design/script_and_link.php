<link href="public/css/styles.css" rel="stylesheet">
<script>
document.addEventListener("DOMContentLoaded", () => {
  const localeSelect = document.getElementById("localeSelect");
  const stored = localStorage.getItem("locale");
  const defaultLocale = stored || "en";

  async function loadTranslations(locale) {
    const url = (location.pathname.includes('/public/') ? '../locales/' : 'public/locales/') + locale + '.json';
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error('Failed to load ' + url);
      const json = await res.json();
      return json;
    } catch (err) {
      console.error('i18n load error', err);
      return {};
    }
  }

  async function applyTranslations(locale) {
    const translations = await loadTranslations(locale);
    // text replacements
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (!key) return;
      if (translations[key]) el.textContent = translations[key];
    });
    // placeholder replacements
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      if (!key) return;
      if (translations[key]) el.setAttribute('placeholder', translations[key]);
    });
  }

  // initialize
  if (localeSelect) {
    localeSelect.value = defaultLocale;
    applyTranslations(defaultLocale);
    localeSelect.addEventListener('change', () => {
      const val = localeSelect.value;
      localStorage.setItem('locale', val);
      applyTranslations(val);
    });
  } else {
    // still try to apply for pages without select (login/register maybe)
    applyTranslations(defaultLocale);
  }
});
</script>
<script src="public/js/chat.js" defer></script>