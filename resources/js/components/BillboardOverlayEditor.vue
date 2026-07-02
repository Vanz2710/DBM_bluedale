<template>
  <Teleport to="body">
    <div v-if="open" class="overlay-editor-backdrop">
      <div class="overlay-editor-modal">
        <div class="overlay-editor-head">
          <div>
            <p class="overlay-editor-title">Billboard Overlay Editor</p>
            <p class="overlay-editor-sub">Drag to position the billboard. Drag corners to resize. Upload your artwork to replace the placeholder.</p>
          </div>
          <button type="button" class="conf-close" @click="handleClose">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="overlay-canvas-wrap">
          <canvas
            ref="overlayCanvas"
            width="620" height="414"
            class="overlay-canvas"
            @mousedown="onCanvasMouseDown"
            @mousemove="onCanvasMouseMove"
            @mouseup="onCanvasMouseUp"
            @mouseleave="onCanvasMouseLeave"
          ></canvas>
        </div>
        <div class="overlay-editor-foot">
          <label class="btn-upload" style="cursor:pointer;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload Artwork
            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="onArtworkFileSelected">
          </label>
          <button v-if="overlayArtDataUrl" type="button" class="btn-clear" @click="overlayArtDataUrl = null; drawOverlayCanvas()">Reset to Yellow</button>
          <div style="flex:1;"></div>
          <button type="button" class="btn-dark" @click="handleClose">Cancel</button>
          <button type="button" class="btn-add" @click="applyBillboardComposite">Apply to Photo</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  product: { type: Object, default: null },
});
const emit = defineEmits(['close', 'apply']);

const overlayCanvas = ref(null);
const overlayOvl = ref({ x: 0.28, y: 0.08, w: 0.16, h: 0.42 });
const overlayArtDataUrl = ref(null);
const overlayDragState = ref(null);
const overlayBgImage = ref(null);
const overlayArtImage = ref(null);
const overlayHovered = ref(false);

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  overlayOvl.value = { x: 0.28, y: 0.08, w: 0.16, h: 0.42 };
  overlayArtDataUrl.value = null;
  overlayArtImage.value = null;
  overlayHovered.value = false;
  overlayDragState.value = null;
  overlayBgImage.value = null;

  // Pre-load background image once — reused on every redraw (no flicker)
  const src = props.product?.site_photo_url;
  if (src) {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload  = () => { overlayBgImage.value = img;  nextTick(_redraw); };
    img.onerror = () => { overlayBgImage.value = null; nextTick(_redraw); };
    img.src = src;
  } else {
    nextTick(_redraw);
  }
});

function handleClose() {
  overlayArtDataUrl.value = null;
  overlayArtImage.value = null;
  overlayBgImage.value = null;
  overlayDragState.value = null;
  overlayHovered.value = false;
  emit('close');
}

// Synchronous full redraw — uses only cached images, never starts new loads
function _redraw() {
  const canvas = overlayCanvas.value;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  ctx.clearRect(0, 0, W, H);
  if (overlayBgImage.value) {
    ctx.drawImage(overlayBgImage.value, 0, 0, W, H);
  } else {
    ctx.fillStyle = '#ddd';
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = '#999'; ctx.font = '14px sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('No site photo', W / 2, H / 2);
  }
  _drawBillboard(ctx, W, H);
}
// Public alias so template can call it after nextTick
function drawOverlayCanvas() { _redraw(); }

function _poleH(oh) { return Math.min(oh * 0.52, 90); }

function _drawBillboard(ctx, W, H) {
  const o = overlayOvl.value;
  const ox = o.x * W, oy = o.y * H, ow = o.w * W, oh = o.h * H;
  const pw = Math.max(4, ow * 0.10);
  const ph = _poleH(oh);
  const pxL = ox + (ow - pw) / 2;

  // ── Pole (black, slight left-highlight) ──
  ctx.fillStyle = 'rgba(0,0,0,0.25)';
  ctx.fillRect(pxL + 2, oy + oh + 2, pw, ph); // shadow
  const pGrad = ctx.createLinearGradient(pxL, 0, pxL + pw, 0);
  pGrad.addColorStop(0,   '#1a1a1a');
  pGrad.addColorStop(0.3, '#444');
  pGrad.addColorStop(0.7, '#222');
  pGrad.addColorStop(1,   '#111');
  ctx.fillStyle = pGrad;
  ctx.fillRect(pxL, oy + oh, pw, ph);
  // subtle highlight
  ctx.fillStyle = 'rgba(255,255,255,0.10)';
  ctx.fillRect(pxL + pw * 0.15, oy + oh, pw * 0.2, ph);

  // ── Board shadow ──
  ctx.fillStyle = 'rgba(0,0,0,0.22)';
  ctx.fillRect(ox + 4, oy + 4, ow, oh);

  // ── Board fill (fully opaque yellow) ──
  if (overlayArtImage.value) {
    ctx.drawImage(overlayArtImage.value, ox, oy, ow, oh);
  } else {
    ctx.fillStyle = '#FFD64A';
    ctx.fillRect(ox, oy, ow, oh);
    // thin vertical texture lines
    ctx.strokeStyle = 'rgba(180,120,0,0.18)';
    ctx.lineWidth = 1;
    for (let i = 1; i < 5; i++) {
      const lx = ox + (ow / 5) * i;
      ctx.beginPath(); ctx.moveTo(lx, oy); ctx.lineTo(lx, oy + oh); ctx.stroke();
    }
  }

  // ── Board border ──
  ctx.strokeStyle = '#b07a00';
  ctx.lineWidth = 2;
  ctx.strokeRect(ox, oy, ow, oh);

  // ── Selection / handles — only when hovered or actively dragging ──
  const active = overlayHovered.value || !!overlayDragState.value;
  if (active) {
    // dashed selection outline
    ctx.strokeStyle = '#1d4ed8';
    ctx.lineWidth = 1.5;
    ctx.setLineDash([4, 3]);
    ctx.strokeRect(ox - 1, oy - 1, ow + 2, oh + 2);
    ctx.setLineDash([]);
    // corner + mid-edge handles
    const s = 8;
    const pts = [
      [ox, oy], [ox + ow / 2, oy], [ox + ow, oy],
      [ox, oy + oh / 2],            [ox + ow, oy + oh / 2],
      [ox, oy + oh], [ox + ow / 2, oy + oh], [ox + ow, oy + oh],
    ];
    pts.forEach(([hx, hy]) => {
      ctx.fillStyle = '#fff';
      ctx.fillRect(hx - s / 2, hy - s / 2, s, s);
      ctx.strokeStyle = '#1d4ed8';
      ctx.lineWidth = 1.5;
      ctx.strokeRect(hx - s / 2, hy - s / 2, s, s);
    });
  }
}

function _getHandle(px, py, W, H) {
  const o = overlayOvl.value;
  const ox = o.x * W, oy = o.y * H, ow = o.w * W, oh = o.h * H;
  const ht = 10;
  const handles = [
    { key: 'nw', x: ox,            y: oy       }, { key: 'n',  x: ox + ow / 2, y: oy       },
    { key: 'ne', x: ox + ow,       y: oy       }, { key: 'w',  x: ox,           y: oy + oh / 2 },
    { key: 'e',  x: ox + ow,       y: oy + oh / 2 }, { key: 'sw', x: ox,        y: oy + oh  },
    { key: 's',  x: ox + ow / 2,   y: oy + oh  }, { key: 'se', x: ox + ow,     y: oy + oh  },
  ];
  return handles.find(h => Math.abs(px - h.x) < ht && Math.abs(py - h.y) < ht) || null;
}

function _canvasPos(e, canvas) {
  const r = canvas.getBoundingClientRect();
  return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) };
}

function _isOverBoard(x, y, W, H) {
  const o = overlayOvl.value;
  const ox = o.x * W - 6, oy = o.y * H - 6, ow = o.w * W + 12, oh = o.h * H + 12;
  return x >= ox && x <= ox + ow && y >= oy && y <= oy + oh;
}

function onCanvasMouseDown(e) {
  const canvas = overlayCanvas.value;
  if (!canvas) return;
  const { x, y } = _canvasPos(e, canvas);
  const W = canvas.width, H = canvas.height;
  const o = overlayOvl.value;
  const handle = _getHandle(x, y, W, H);
  if (handle) {
    overlayDragState.value = { type: 'resize', handle: handle.key, sx: x, sy: y, so: { ...o } };
    return;
  }
  const ox = o.x * W, oy = o.y * H, ow = o.w * W, oh = o.h * H;
  if (x >= ox && x <= ox + ow && y >= oy && y <= oy + oh) {
    overlayDragState.value = { type: 'move', sx: x, sy: y, so: { ...o } };
  }
}

function onCanvasMouseMove(e) {
  const canvas = overlayCanvas.value;
  if (!canvas) return;
  const { x, y } = _canvasPos(e, canvas);
  const W = canvas.width, H = canvas.height;
  const d = overlayDragState.value;

  if (d) {
    const dx = (x - d.sx) / W, dy = (y - d.sy) / H;
    const s = d.so;
    if (d.type === 'move') {
      overlayOvl.value = { ...s, x: Math.max(0, Math.min(1 - s.w, s.x + dx)), y: Math.max(0, Math.min(1 - s.h, s.y + dy)) };
    } else {
      let nx = s.x, ny = s.y, nw = s.w, nh = s.h;
      const h = d.handle;
      if (h.includes('e')) nw = Math.max(0.06, s.w + dx);
      if (h.includes('s')) nh = Math.max(0.08, s.h + dy);
      if (h.includes('w')) { nx = s.x + dx; nw = Math.max(0.06, s.w - dx); }
      if (h.includes('n')) { ny = s.y + dy; nh = Math.max(0.08, s.h - dy); }
      overlayOvl.value = { x: nx, y: ny, w: nw, h: nh };
    }
    _redraw();
    return;
  }

  // Hover detection (no drag active)
  const nowOver = _isOverBoard(x, y, W, H);
  if (nowOver !== overlayHovered.value) {
    overlayHovered.value = nowOver;
    canvas.style.cursor = nowOver ? 'grab' : 'default';
    _redraw();
  }
}

function onCanvasMouseUp() {
  overlayDragState.value = null;
  // Keep handles visible if still hovering
  _redraw();
}

function onCanvasMouseLeave() {
  overlayDragState.value = null;
  if (overlayHovered.value) {
    overlayHovered.value = false;
    const canvas = overlayCanvas.value;
    if (canvas) canvas.style.cursor = 'default';
    _redraw();
  }
}

function onArtworkFileSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    overlayArtDataUrl.value = e.target.result;
    const img = new Image();
    img.onload  = () => { overlayArtImage.value = img; _redraw(); };
    img.onerror = () => { overlayArtImage.value = null; _redraw(); };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function applyBillboardComposite() {
  const canvas = overlayCanvas.value;
  if (!canvas || !props.product) return;
  // Temporarily hide handles for the final composite
  overlayHovered.value = false;
  overlayDragState.value = null;
  _redraw();
  emit('apply', canvas.toDataURL('image/jpeg', 0.92));
}
</script>

<style scoped>
@keyframes overlay-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
@keyframes modal-spring-in {
  from { opacity: 0; transform: scale(0.92) translateY(10px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.overlay-editor-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  animation: overlay-fade-in 0.18s ease;
}
.overlay-editor-backdrop > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }
.overlay-editor-modal {
  background: var(--surface); border-radius: var(--radius-lg); overflow: hidden;
  box-shadow: 0 24px 72px rgba(0,0,0,0.3); width: min(680px, 96vw);
  display: flex; flex-direction: column;
}
.overlay-editor-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 14px 16px 12px; border-bottom: 1px solid var(--border); gap: 12px;
}
.overlay-editor-title { margin: 0 0 2px; font-size: 14px; font-weight: 700; color: var(--text-1); }
.overlay-editor-sub { margin: 0; font-size: 11px; color: var(--text-3); }
.overlay-canvas-wrap {
  /* Fixed dark backdrop for the photo editing canvas — intentionally theme-invariant
     so the billboard photo being edited always renders against a neutral surface. */
  background: #111; display: flex; align-items: center; justify-content: center; padding: 8px;
}
.overlay-canvas {
  max-width: 100%; display: block; cursor: default; border-radius: 4px;
}
.overlay-editor-foot {
  display: flex; align-items: center; gap: 8px; padding: 12px 14px;
  border-top: 1px solid var(--border); flex-wrap: wrap;
}
.btn-add, .btn-dark, .btn-clear {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.4); }
.btn-add:hover { background: var(--primary-hover); }
.btn-dark { background: var(--text-1); color: var(--primary-on); }
.btn-dark:hover { opacity: 0.88; }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--border); color: var(--text-1); }
.btn-upload {
  height: 30px; border: none; border-radius: var(--radius-sm); padding: 0 14px; font-size: 12px;
  font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
  transition: background 0.15s;
  background: var(--primary); color: var(--primary-on);
}
.btn-upload:hover { background: var(--primary-hover); }
.btn-upload input { display: none; }
</style>
