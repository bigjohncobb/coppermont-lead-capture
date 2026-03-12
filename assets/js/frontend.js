(function () {
  const bar = document.querySelector('[data-cmlc-infobar]');
  const cfg = window.cmlcConfig || {};
  if (!bar && !document.querySelector('[data-cmlc-form]')) return;

  const campaignId = Number(cfg.campaignId || (bar && bar.dataset.campaignId) || 0);
  const storageKey = `cmlcState:${campaignId || 'default'}`;
  const form = document.querySelector('[data-cmlc-form]');
  const closeBtn = bar ? bar.querySelector('[data-cmlc-close]') : null;
  const status = bar ? bar.querySelector('[data-cmlc-status]') : null;

  if (!cfg.ajaxUrl || !cfg.nonce || !campaignId) return;

  const loadState = () => {
    try {
      return JSON.parse(localStorage.getItem(storageKey)) || { views: 0, dismissedUntil: 0 };
    } catch (e) {
      return { views: 0, dismissedUntil: 0 };
    }
  };

  const saveState = (state) => localStorage.setItem(storageKey, JSON.stringify(state));

  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

  const shouldShow = () => {
    const state = loadState();
    if (!cfg.enableMobile && isMobile()) return false;
    if ((state.views || 0) >= (cfg.maxViews || 3)) return false;
    if ((state.dismissedUntil || 0) > Date.now()) return false;
    return true;
  };

  const postAjax = (action, payload = {}) => {
    const body = new URLSearchParams({ action, nonce: cfg.nonce, campaign_id: String(campaignId), ...payload });
    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    }).then((res) => res.json());
  };

  const show = () => {
    if (!bar || bar.classList.contains('is-visible') || !shouldShow()) return;

    bar.classList.add('is-visible');
    bar.setAttribute('aria-hidden', 'false');

    const state = loadState();
    state.views = (state.views || 0) + 1;
    saveState(state);

    postAjax('cmlc_track_impression').catch(() => null);
  };

  const dismiss = () => {
    const state = loadState();
    const cooldownMs = (cfg.cooldownHours || 24) * 60 * 60 * 1000;
    state.dismissedUntil = Date.now() + cooldownMs;
    saveState(state);
    if (bar) {
      bar.classList.remove('is-visible');
      bar.setAttribute('aria-hidden', 'true');
    }
  };

  const triggerByScroll = () => {
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight <= 0 ? 100 : (scrollTop / docHeight) * 100;
    if (pct >= (cfg.scrollPercent || 40)) {
      show();
      window.removeEventListener('scroll', triggerByScroll);
    }
  };

  if (cfg.enableExitIntent) {
    document.addEventListener('mouseout', (event) => {
      if (event.clientY <= 0) show();
    });
  }

  if (bar) {
    window.addEventListener('scroll', triggerByScroll, { passive: true });
    setTimeout(show, (cfg.timeDelay || 8) * 1000);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', dismiss);
  }

  if (form) {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const email = form.querySelector('input[name="email"]').value;

      postAjax('cmlc_submit_email', { email })
        .then((data) => {
          if (data && data.success) {
            if (status) status.textContent = data.data.message || 'Subscribed';
            dismiss();
          } else {
            if (status) status.textContent = (data && data.data && data.data.message) || 'Unable to submit.';
          }
        })
        .catch(() => {
          if (status) status.textContent = 'Network error. Please try again.';
        });
    });
  }
})();
