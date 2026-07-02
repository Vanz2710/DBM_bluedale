<template>
  <div class="sig-pad-wrap">
    <div class="sig-pad-head">
      <label style="margin:0;">Signature
        <span class="field-hint">draw or upload — saved per user</span>
      </label>
      <div class="sig-pad-actions">
        <button type="button" class="sig-btn" @click="clearSigPad">Clear</button>
        <label class="sig-btn">
          Upload
          <input type="file" accept="image/png,image/jpeg,image/webp" @change="onSigFileUpload">
        </label>
        <button type="button" class="sig-btn sig-btn-save" :disabled="sigSaving" @click="saveSig">
          {{ sigSaving ? 'Saving…' : saved ? 'Saved' : 'Save Signature' }}
        </button>
      </div>
    </div>
    <div class="sig-pad-canvas-wrap">
      <canvas
        ref="sigPadRef"
        width="800" height="160"
        class="sig-pad-canvas"
        @mousedown="sigStartDraw"
        @mousemove="sigDraw"
        @mouseup="sigStopDraw"
        @mouseleave="sigStopDraw"
        @touchstart.prevent="sigStartDraw"
        @touchmove.prevent="sigDraw"
        @touchend="sigStopDraw"
      ></canvas>
      <span class="sig-pad-hint">Draw your signature here</span>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../api.js';

const emit = defineEmits(['signature-saved']);
const signature = defineModel('signature', { default: '' });
// Persisted across step-navigation remounts (parent resets these only when the
// wizard session itself opens/closes) so a saved signature isn't silently
// re-fetched (and any unsaved local edit discarded) every time the user tabs
// away from and back to this step within the same wizard session.
const loaded = defineModel('loaded', { default: false });
const saved = defineModel('saved', { default: false });

const sigPadRef = ref(null);
const sigDrawing = ref(false);
const sigSaving = ref(false);

function _sigCtx() {
  const c = sigPadRef.value;
  if (!c) return null;
  const ctx = c.getContext('2d');
  ctx.strokeStyle = '#111827';
  ctx.lineWidth   = 2.5;
  ctx.lineCap     = 'round';
  ctx.lineJoin    = 'round';
  return ctx;
}

function _sigCoords(e) {
  const c = sigPadRef.value;
  const r = c.getBoundingClientRect();
  const sx = c.width  / r.width;
  const sy = c.height / r.height;
  const src = e.touches ? e.touches[0] : e;
  return { x: (src.clientX - r.left) * sx, y: (src.clientY - r.top) * sy };
}

function sigStartDraw(e) {
  sigDrawing.value = true;
  const ctx = _sigCtx();
  if (!ctx) return;
  const { x, y } = _sigCoords(e);
  ctx.beginPath();
  ctx.moveTo(x, y);
}

function sigDraw(e) {
  if (!sigDrawing.value) return;
  const ctx = _sigCtx();
  if (!ctx) return;
  const { x, y } = _sigCoords(e);
  ctx.lineTo(x, y);
  ctx.stroke();
}

function sigStopDraw() {
  if (!sigDrawing.value) return;
  sigDrawing.value = false;
  const c = sigPadRef.value;
  if (c) signature.value = c.toDataURL('image/png');
}

function clearSigPad() {
  const c = sigPadRef.value;
  if (!c) return;
  c.getContext('2d').clearRect(0, 0, c.width, c.height);
  signature.value = '';
  saved.value = false;
}

function onSigFileUpload(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = new Image();
    img.onload = () => {
      const c = sigPadRef.value;
      if (!c) return;
      const ctx = c.getContext('2d');
      ctx.clearRect(0, 0, c.width, c.height);
      const ratio = Math.min(c.width / img.width, c.height / img.height);
      const w = img.width * ratio;
      const h = img.height * ratio;
      ctx.drawImage(img, (c.width - w) / 2, (c.height - h) / 2, w, h);
      signature.value = c.toDataURL('image/png');
    };
    img.src = ev.target.result;
  };
  reader.readAsDataURL(file);
  e.target.value = '';
}

async function saveSig() {
  const c = sigPadRef.value;
  if (!c) return;
  const data = c.toDataURL('image/png');
  sigSaving.value = true;
  try {
    await api.put('/v1/my-signature', { signature_data: data });
    signature.value = data;
    saved.value = true;
    emit('signature-saved');
  } catch (_) {
    // non-fatal
  } finally {
    sigSaving.value = false;
  }
}

async function loadSavedSig() {
  try {
    const res = await api.get('/v1/my-signature');
    const data = res.data?.signature_data;
    if (!data) return;
    signature.value = data;
    // Re-check ref after async gap — user may have navigated away from step 'info'
    const c = sigPadRef.value;
    if (!c) return;
    const img = new Image();
    img.onload = () => {
      // Re-fetch ref again in case canvas was replaced between load and paint
      const canvas = sigPadRef.value;
      if (!canvas) return;
      canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
      canvas.getContext('2d').drawImage(img, 0, 0);
    };
    img.src = data;
  } catch (_) { /* no saved sig */ }
}

onMounted(() => {
  if (loaded.value) return;
  loaded.value = true;
  loadSavedSig();
});
</script>

<style scoped>
.sig-pad-wrap { border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; background: var(--surface); }
.sig-pad-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.field-hint { margin-left: 6px; font-size: 10px; font-weight: 600; color: var(--text-3); text-transform: none; letter-spacing: 0; }
.sig-pad-actions { display: flex; gap: 6px; align-items: center; }
.sig-btn {
  height: 28px; padding: 0 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface-2, var(--surface)); color: var(--text-2);
  cursor: pointer; display: inline-flex; align-items: center; white-space: nowrap;
}
.sig-btn input[type="file"] { display: none; }
.sig-btn:hover:not(:disabled) { background: var(--surface-3, var(--border)); }
.sig-btn-save { background: var(--primary); color: var(--primary-on); border-color: var(--primary); }
.sig-btn-save:hover:not(:disabled) { opacity: 0.88; }
.sig-btn-save:disabled { opacity: 0.55; cursor: default; }
.sig-pad-canvas-wrap { position: relative; }
.sig-pad-canvas {
  width: 100%; height: 110px; display: block; cursor: crosshair; touch-action: none;
  border: 1.5px dashed var(--border); border-radius: var(--radius-sm); background: var(--surface);
}
.sig-pad-hint {
  position: absolute; bottom: 7px; left: 50%; transform: translateX(-50%);
  font-size: 10px; color: var(--text-3); pointer-events: none; white-space: nowrap;
}
</style>
