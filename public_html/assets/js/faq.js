document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-faq-item]').forEach((item) => {
    const button = item.querySelector('[data-faq-toggle]');
    const panel = item.querySelector('[data-faq-panel]');
    if (!button || !panel) return;

    button.addEventListener('click', () => {
      const isOpen = item.classList.contains('is-open');
      document.querySelectorAll('[data-faq-item].is-open').forEach((openItem) => {
        if (openItem !== item) {
          openItem.classList.remove('is-open');
          openItem.querySelector('[data-faq-toggle]')?.setAttribute('aria-expanded', 'false');
        }
      });
      item.classList.toggle('is-open', !isOpen);
      button.setAttribute('aria-expanded', String(!isOpen));
    });
  });
});
