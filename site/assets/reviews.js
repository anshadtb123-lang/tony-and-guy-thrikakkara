(() => {
  const track = document.querySelector('#review-track');
  if (!track) return;
  const cards = Array.from(track.children);
  const controls = track.closest('section').querySelector('.review-controls');
  const [previous, next] = controls.querySelectorAll('button');
  const range = controls.querySelector('.review-range');
  const slider = controls.querySelector('input');
  slider.min = 0;
  slider.max = 100;
  slider.oninput = () => track.scrollTo({left: Number(slider.value) / 100 * (track.scrollWidth - track.clientWidth), behavior:'instant'});
  const reader = document.querySelector('.review-reader');
  for (const card of cards) {
    const button = card.querySelector('.read-review');
    const quote = card.querySelector('blockquote');
    const observer = new ResizeObserver(() => {button.hidden = quote.scrollHeight <= quote.clientHeight + 1;});
    observer.observe(quote);
    button.onclick = () => {
      reader.querySelector('h3').textContent = card.querySelector('.review-person').firstChild.textContent;
      reader.querySelector('.reader-rating').textContent = card.querySelector('.review-stars').getAttribute('aria-label');
      reader.querySelector('blockquote').textContent = quote.textContent;
      reader.showModal();
    };
  }
  const offset = card => card.getBoundingClientRect().left - cards[0].getBoundingClientRect().left;
  function update() {
    const left = track.scrollLeft, right = left + track.clientWidth;
    const first = cards.findIndex(card => offset(card) + card.offsetWidth > left + 2);
    const last = cards.filter(card => offset(card) + card.offsetWidth <= right + 2).length;
    const from = Math.max(1, first + 1), to = Math.max(from, last);
    range.textContent = (from === to ? 'Review ' + from : 'Reviews ' + from + '–' + to) + ' of ' + cards.length;
    slider.value = Math.round(100 * left / Math.max(1, track.scrollWidth - track.clientWidth));
    slider.setAttribute('aria-valuetext',range.textContent);
    controls.hidden = track.scrollWidth <= track.clientWidth + 2;
    previous.disabled = left <= 2;
    next.disabled = right >= track.scrollWidth - 2;
  }
  function move(direction) {
    const order = direction > 0 ? cards : [...cards].reverse();
    const target = order.find(card => direction * (offset(card) - track.scrollLeft) > 2);
    if (target) track.scrollTo({left: offset(target), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
  }
  previous.onclick = () => move(-1);
  next.onclick = () => move(1);
  track.addEventListener('scroll', update, {passive:true});
  new ResizeObserver(update).observe(track);
  update();
})();
