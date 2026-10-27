(() => {
  const KEY = 'dg-scroll-pos:v2';
  const MAX_AGE_MS = 60000;

  const keyForUrl = (value) => {
    const url = new URL(value, window.location.href);
    const entries = Array.from(url.searchParams.entries()).sort(([aKey, aValue], [bKey, bValue]) => {
      if (aKey === bKey) return aValue.localeCompare(bValue);
      return aKey.localeCompare(bKey);
    });
    const query = entries
      .map(([name, entryValue]) => `${encodeURIComponent(name)}=${encodeURIComponent(entryValue)}`)
      .join('&');
    return url.pathname + (query ? `?${query}` : '');
  };

  const clearSaved = () => {
    try { window.sessionStorage.removeItem(KEY); } catch (_) {}
  };

  const peekSaved = () => {
    try {
      const raw = window.sessionStorage.getItem(KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (_) {
      return null;
    }
  };

  const isUsable = (data) => {
    if (!data || typeof data.y !== 'number' || !Number.isFinite(data.y)) return false;
    if (Date.now() - Number(data.t || 0) > MAX_AGE_MS) return false;
    if (data.key === keyForUrl(window.location.href)) return true;
    return Boolean(data.fallbackPath && data.path === window.location.pathname);
  };

  if (!window.location.hash && isUsable(peekSaved()) && 'scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
  }

  const saveFor = (target, fallbackPath = false) => {
    try {
      const url = new URL(target, window.location.href);
      window.sessionStorage.setItem(KEY, JSON.stringify({
        key: keyForUrl(url.href),
        path: url.pathname,
        fallbackPath: Boolean(fallbackPath),
        y: Math.max(0, window.scrollY || window.pageYOffset || 0),
        t: Date.now(),
      }));
    } catch (_) {}
  };

  document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-scroll-restore')) return;
    if (form.target && form.target.toLowerCase() === '_blank') return;

    const method = (form.method || 'get').toLowerCase();
    let actionUrl;
    try {
      actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    } catch (_) {
      return;
    }

    // #akis gibi bilinçli bir çapaya giden GET formunda tarayıcı konumu belirler.
    if (method === 'get' && actionUrl.hash) {
      clearSaved();
      return;
    }

    if (method === 'get') {
      try {
        const destination = new URL(actionUrl.href);
        destination.search = '';
        let formData;
        try {
          formData = event.submitter ? new FormData(form, event.submitter) : new FormData(form);
        } catch (_) {
          formData = new FormData(form);
          if (event.submitter && event.submitter.name) {
            formData.append(event.submitter.name, event.submitter.value || '');
          }
        }
        const params = new URLSearchParams();
        formData.forEach((value, name) => {
          if (typeof value === 'string') params.append(name, value);
        });
        destination.search = params.toString();
        saveFor(destination.href, false);
      } catch (_) {
        saveFor(window.location.href, true);
      }
      return;
    }

    // POST + yönlendirme genellikle aynı sayfaya döner; hedef farklıysa
    // pathname eşleşmeyeceği için kayıt kullanılmaz.
    saveFor(window.location.href, true);
  });

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link) return;
    if (link.target && link.target.toLowerCase() === '_blank') return;
    if (link.hasAttribute('download')) return;
    if (!link.matches('[data-preserve-scroll], .filters a.chip, .admin-filters a.chip, .blog-categories a.chip')) return;

    let url;
    try { url = new URL(link.href, window.location.href); } catch (_) { return; }
    if (url.origin !== window.location.origin || url.hash) return;
    saveFor(url.href, false);
  });

  let pendingY = null;
  const jumpToPending = () => {
    if (pendingY === null) return;
    try {
      window.scrollTo({ top: pendingY, left: 0, behavior: 'instant' });
    } catch (_) {
      window.scrollTo(0, pendingY);
    }
  };

  const restore = () => {
    const data = peekSaved();
    clearSaved();
    if (window.location.hash || !isUsable(data)) return;
    pendingY = Math.max(0, Number(data.y) || 0);
    jumpToPending();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restore, { once: true });
  } else {
    restore();
  }

  window.addEventListener('load', jumpToPending);
  window.addEventListener('pageshow', (event) => {
    // Geri/ileri önbelleğindeki konumu tarayıcı zaten geri yükledi.
    if (event.persisted) {
      pendingY = null;
      clearSaved();
    }
  });
})();
