document.querySelectorAll('[data-copy]').forEach(button => {
  button.addEventListener('click', async () => {
    const field = document.getElementById(button.dataset.copy);
    const status = document.querySelector('.copy-status');
    try {
      if (!navigator.clipboard || !window.isSecureContext) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText(field.value);
      status.textContent = button.dataset.copy === 'share-url' ? 'Share link copied.' : 'Puzzle JSON copied. Ready to import!';
    } catch {
      field.type = 'text';
      field.classList.remove('sr-only');
      field.focus();
      field.select();
      status.textContent = 'Select and copy the text below, or download the JSON file.';
    }
  });
});
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); });
});
