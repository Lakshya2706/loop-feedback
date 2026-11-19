const types = new Set(['image/png', 'image/jpeg', 'image/webp', 'image/gif']);
const extensions = {png:'image/png',jpg:'image/jpeg',jpeg:'image/jpeg',webp:'image/webp',gif:'image/gif'};
export function screenshotFile(transfer) {
  // macOS screenshot thumbnails may arrive as a file item rather than FileList.
  for (const item of Array.from(transfer?.items || [])) {
    if (item.kind === 'file') {
      const file = item.getAsFile();
      if (file) return file;
    }
  }
  return Array.from(transfer?.files || [])[0] || null;
}
export function prepareScreenshot(file) {
  if (!file) throw new Error('No image was received. On Mac, press Control + Shift + Command + 4, then paste here with Command + V.');
  const type = file.type || extensions[file.name?.split('.').pop().toLowerCase()];
  if (!types.has(type)) throw new Error('Choose a PNG, JPG, GIF or WebP image.');
  if (!file.size || file.size > 8 * 1024 * 1024) throw new Error('Choose a non-empty image under 8 MB.');
  return file.type === type ? file : new File([file], file.name || 'screenshot.png', {type});
}
