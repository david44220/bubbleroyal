(() => {
  const navToggle = document.querySelector('.nav-toggle');
  const primaryNav = document.querySelector('.primary-nav');
  const toast = document.querySelector('#toast');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (navToggle && primaryNav) {
    navToggle.addEventListener('click', () => {
      const isOpen = primaryNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    });

    primaryNav.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        primaryNav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  let toastTimer;
  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3600);
  };

  document.querySelectorAll('[data-toast]').forEach((control) => {
    control.addEventListener('click', (event) => {
      event.preventDefault();
      showToast(control.dataset.toast);
    });
  });

  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !reducedMotion) {
    const observer = new IntersectionObserver((entries, currentObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        currentObserver.unobserve(entry.target);
      });
    }, { threshold: 0.13, rootMargin: '0px 0px -30px' });
    revealItems.forEach((item) => observer.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  }

  const countdown = document.querySelector('#countdown');
  let secondsLeft = (14 * 60 * 60) + (32 * 60) + 18;
  const renderCountdown = () => {
    if (!countdown) return;
    const hours = Math.floor(secondsLeft / 3600);
    const minutes = Math.floor((secondsLeft % 3600) / 60);
    const seconds = secondsLeft % 60;
    countdown.textContent = [hours, minutes, seconds].map((value) => String(value).padStart(2, '0')).join(':');
    secondsLeft = secondsLeft > 0 ? secondsLeft - 1 : (14 * 60 * 60) + (32 * 60) + 18;
  };
  renderCountdown();
  window.setInterval(renderCountdown, 1000);

  const stage = document.querySelector('.hero-stage');
  const pieces = document.querySelectorAll('.stage-piece');
  if (stage && pieces.length && !reducedMotion && window.matchMedia('(pointer: fine)').matches) {
    stage.addEventListener('pointermove', (event) => {
      const rect = stage.getBoundingClientRect();
      const x = ((event.clientX - rect.left) / rect.width - 0.5) * 20;
      const y = ((event.clientY - rect.top) / rect.height - 0.5) * 16;
      pieces.forEach((piece) => {
        const depth = Number(piece.dataset.depth || 0);
        piece.style.setProperty('--piece-x', `${(x * depth).toFixed(2)}px`);
        piece.style.setProperty('--piece-y', `${(y * depth).toFixed(2)}px`);
      });
    });
    stage.addEventListener('pointerleave', () => {
      pieces.forEach((piece) => {
        piece.style.setProperty('--piece-x', '0px');
        piece.style.setProperty('--piece-y', '0px');
      });
    });
  }

  const countTarget = document.querySelector('[data-count]');
  if (countTarget && 'IntersectionObserver' in window && !reducedMotion) {
    const countObserver = new IntersectionObserver((entries, currentObserver) => {
      if (!entries[0].isIntersecting) return;
      const target = Number(countTarget.dataset.count || 0);
      const duration = 900;
      const start = performance.now();
      const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const value = Math.round(target * (1 - Math.pow(1 - progress, 3)));
        countTarget.textContent = `$${value.toLocaleString('en-US')}`;
        if (progress < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
      currentObserver.disconnect();
    }, { threshold: .7 });
    countObserver.observe(countTarget);
  }
})();
