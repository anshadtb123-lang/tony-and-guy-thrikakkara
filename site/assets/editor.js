document.addEventListener('change', event => {
  const input = event.target;
  if (input.type !== 'file') return;
  const figure = input.closest('fieldset').querySelector('.previews figure:last-child');
  const image = figure.querySelector('img');
  if (image.dataset.preview) URL.revokeObjectURL(image.dataset.preview);
  figure.hidden = true;
  if (!input.files.length) return;
  image.onload = () => { figure.hidden = false; };
  image.onerror = () => { figure.hidden = true; };
  image.dataset.preview = URL.createObjectURL(input.files[0]);
  image.src = image.dataset.preview;
});
