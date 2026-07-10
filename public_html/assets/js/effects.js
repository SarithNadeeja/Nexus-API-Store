/**
 * Scroll reveal, magnetic buttons, and navbar scroll effects.
 */
(function () {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initScrollReveal() {
    const elements = document.querySelectorAll('.reveal:not(.is-visible)');
    if (!elements.length) return;

    if (reducedMotion) {
      elements.forEach((el) => el.classList.add('is-visible'));
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '-60px 0px' }
    );

    elements.forEach((el) => observer.observe(el));
  }

  function initMagneticButtons() {
    if (reducedMotion) return;

    document.querySelectorAll('.magnetic-wrap').forEach((wrap) => {
      wrap.addEventListener('mousemove', (event) => {
        const rect = wrap.getBoundingClientRect();
        const x = (event.clientX - rect.left - rect.width / 2) * 0.15;
        const y = (event.clientY - rect.top - rect.height / 2) * 0.15;
        wrap.style.transform = `translate(${x}px, ${y}px)`;
      });

      wrap.addEventListener('mouseleave', () => {
        wrap.style.transform = 'translate(0px, 0px)';
      });
    });
  }

  function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    const onScroll = () => {
      navbar.classList.toggle('navbar-scrolled', window.scrollY > 20);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  function initHeroEntrance() {
    if (reducedMotion) {
      document.querySelectorAll('.hero-enter').forEach((el) => el.classList.add('is-visible'));
      return;
    }

    document.querySelectorAll('.hero-enter').forEach((el, index) => {
      setTimeout(() => el.classList.add('is-visible'), 120 + index * 120);
    });
  }

  function initSmoothAnchors() {
    document.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener('click', (event) => {
        const id = link.getAttribute('href');
        if (!id || id === '#') return;
        const target = document.querySelector(id);
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initScrollReveal();
    initMagneticButtons();
    initNavbarScroll();
    initHeroEntrance();
    initSmoothAnchors();
  });

  window.NexusEffects = { initScrollReveal, initMagneticButtons };
})();
