(() => {
  const panel = document.querySelector('#measurement');
  if (!panel) return;
  const { ads, label, ga, pixel } = panel.dataset;
  const preference = document.querySelector('#privacy-choice');
  const key = 'salon-measurement-v1';
  let enabled = false;
  const load = src => {
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = src;
    document.head.append(tag);
  };
  function enable() {
    if (enabled) return;
    enabled = true;
    if (ads || ga) {
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      gtag('js', new Date());
      for (const id of [ads, ga].filter(Boolean)) gtag('config', id);
      load('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ads || ga));
    }
    if (pixel) {
      const fbq = window.fbq = function () {
        if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments);
        else fbq.queue.push(arguments);
      };
      window._fbq = fbq;
      fbq.push = fbq; fbq.loaded = true; fbq.version = '2.0'; fbq.queue = [];
      fbq('init', pixel);
      fbq('track', 'PageView');
      load('https://connect.facebook.net/en_US/fbevents.js');
    }
    document.addEventListener('click', event => {
      if (!event.target.closest('a[href^="tel:"]')) return;
      if (ads && label) gtag('event','conversion',{send_to:ads+'/'+label});
      if (ga) gtag('event','booking_click',{send_to:ga});
      if (pixel) fbq('track','Contact');
    });
  }
  let choice;
  try {choice = localStorage.getItem(key);} catch {}
  if (choice === 'allow') enable();
  if (!choice) panel.hidden = false;
  preference.hidden = false;
  preference.onclick = () => {panel.hidden = false; panel.querySelector('button').focus();};
  for (const button of panel.querySelectorAll('button')) {
    button.onclick = () => {
      const answer = button.value;
      try {localStorage.setItem(key, answer);} catch {}
      panel.hidden = true;
      if (answer === 'allow') enable();
      else if (enabled) location.reload();
      preference.focus();
    };
  }
})();
