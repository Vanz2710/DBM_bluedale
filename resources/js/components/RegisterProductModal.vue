<template>
  <Teleport to="body">
    <div v-if="open" class="modal-backdrop">
      <section class="register-modal" role="dialog" aria-modal="true">
        <div class="register-modal-header">
          <h2>Register New Product</h2>
          <button type="button" class="detail-close" @click="emit('close')">&times;</button>
        </div>
        <div class="register-modal-body">
          <div v-if="error" class="error-msg">{{ error }}</div>
          <div class="register-form-grid">

            <div class="field register-full">
              <label>Site Name <span class="req-star">*</span></label>
              <div class="register-site-row">
                <input v-model="form.site_name" placeholder="e.g. KL - Bangsar: Jalan Maarof">
              </div>
            </div>

            <div class="field register-full">
              <div class="field-loc-head">
                <label style="margin:0;">Find Location <span class="field-hint">Paste a Google Maps link or type a place name</span></label>
                <a :href="registerCoordMapUrl" target="_blank" rel="noopener" class="btn-open-gmaps">Open Google Maps</a>
              </div>
              <div class="location-input-wrap">
                <input
                  v-model="locationInput"
                  placeholder="Paste a Maps link or type a place name..."
                  autocomplete="off"
                  :class="{ 'input-error-border': locationError }"
                  @input="onLocationInput"
                >
                <span v-if="locationResolving || locationSearching" class="location-status">
                  <span class="lm-spinner"></span> {{ locationResolving ? 'Resolving...' : 'Searching...' }}
                </span>
                <div v-if="locationResults.length > 0" class="place-results">
                  <button
                    v-for="result in locationResults"
                    :key="result.place_id"
                    type="button"
                    class="place-result-item"
                    @click="selectLocationResult(result)"
                  >{{ result.display_name }}</button>
                </div>
              </div>
              <div v-if="locationError" class="maps-link-error">{{ locationError }}</div>
              <template v-else-if="form.coordinate">
                <div class="coord-preview">Coordinate: {{ form.coordinate }}</div>
                <div ref="registerPreviewMapEl" class="location-preview-map"></div>
                <p class="location-preview-hint">Drag the pin if it's not sitting exactly on the site.</p>
              </template>
            </div>

            <div class="field">
              <label>Ad Format <span class="req-star">*</span></label>
              <select v-model="form.product_type">
                <option value="" disabled>Select ad format...</option>
                <option v-for="p in productOptions" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="field">
              <label>Type <span class="req-star">*</span></label>
              <select v-model="form.type">
                <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div class="field">
              <label>Status <span class="req-star">*</span></label>
              <select v-model="form.status">
                <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div class="field">
              <label>Site Code <span class="field-hint">optional</span></label>
              <input v-model="form.site_code" placeholder="e.g. BB-KL-001">
            </div>
            <div class="field">
              <label>Board / Unit Size <span class="field-hint">optional</span></label>
              <input v-model="form.size" placeholder="e.g. 20ft × 10ft">
            </div>
            <template v-if="form.product_type !== 'Lamp Post Bunting'">
              <div class="field">
                <label>Illumination</label>
                <select v-model="form.illumination">
                  <option value="">—</option>
                  <option v-for="opt in ILLUMINATION_OPTIONS" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
              <div class="field">
                <label>Facing</label>
                <select v-model="form.facing">
                  <option value="">—</option>
                  <option v-for="opt in FACING_OPTIONS" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>
            </template>
            <div class="field">
              <label>State & City</label>
              <input v-model="form.state_city" placeholder="Optional">
            </div>

            <div class="field register-full">
              <div class="landmark-label-row">
                <label style="margin:0">
                  Nearest Landmarks
                  <span v-if="landmarkFetched && !landmarkFetching" class="landmark-fetch-status landmark-fetch-ok">Filled</span>
                </label>
                <button
                  v-if="form.coordinate"
                  type="button"
                  class="btn-refresh-landmarks"
                  :disabled="landmarkFetching"
                  @click="refreshLandmarks"
                >
                  <span v-if="landmarkFetching"><span class="lm-spinner"></span> Searching...</span>
                  <span v-else>Auto Search Landmarks</span>
                </button>
              </div>
              <table class="register-landmark-table">
                <thead>
                  <tr><th>Category</th><th>Nearest Place</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(lm, i) in form.nearest_landmarks" :key="i">
                    <td class="lm-cat-cell">{{ lm.category }}</td>
                    <td class="lm-place-cell">
                      <span v-if="landmarkFetching" class="lm-skeleton"></span>
                      <input
                        v-else
                        :value="lm.place === 'Not Found' ? '' : lm.place"
                        class="lm-place-input"
                        placeholder="Not found"
                        :class="{ 'lm-not-found': lm.place === 'Not Found' }"
                        @input="form.nearest_landmarks[i].place = $event.target.value || 'Not Found'"
                      >
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
        <div class="register-modal-footer">
          <p class="register-footer-note">
            <strong>Register Product</strong> adds it to the active list.
            <strong>Save as Draft</strong> saves a temporary entry and opens the proposal wizard so you can print a PDF first.
          </p>
          <div class="register-footer-actions">
            <button type="button" class="btn-clear" @click="emit('close')">Cancel</button>
            <button type="button" class="btn-stage-pdf" :disabled="saving || !form.site_name.trim() || !form.product_type" @click="emit('stage')">
              {{ saving ? 'Saving...' : 'Save as Draft + Print PDF' }}
            </button>
            <button type="button" class="btn-add" :disabled="saving || !form.site_name.trim() || !form.product_type" @click="emit('register')">
              {{ saving ? 'Registering...' : 'Register Product' }}
            </button>
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import api from '../api.js';
import L from 'leaflet';

const props = defineProps({
  open: { type: Boolean, default: false },
  productOptions: { type: Array, default: () => [] },
  typeOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  error: { type: String, default: '' },
});
const emit = defineEmits(['close', 'register', 'stage']);
const form = defineModel('form', { required: true });

const ILLUMINATION_OPTIONS = ['Lit', 'Unlit', 'LED', 'Backlit'];
const FACING_OPTIONS = ['Right-Hand Read', 'Left-Hand Read', 'Cross Read', 'Single-Face', 'Back-To-Back', 'V-Shape', 'Tri-Face'];

function parseCoordinate(coord) {
  if (!coord) return null;
  const parts = coord.split(',').map((s) => parseFloat(s.trim()));
  if (parts.length !== 2 || parts.some(isNaN)) return null;
  return { lat: parts[0], lng: parts[1] };
}

const registerCoordMapUrl = computed(() => {
  const coord = form.value?.coordinate;
  if (!coord) return 'https://maps.google.com';
  const parsed = parseCoordinate(coord);
  if (!parsed) return 'https://maps.google.com';
  return `https://www.google.com/maps?q=${parsed.lat},${parsed.lng}`;
});

const locationInput = ref('');
const locationError = ref('');
const locationResolving = ref(false);
const locationSearching = ref(false);
const locationResults = ref([]);
let locationTimer = null;

// Inline preview map — lets staff visually confirm a resolved coordinate actually
// lands on the right spot (and drag to correct it) instead of trusting the raw
// lat/lng text or having to click through to Google Maps in a new tab.
const registerPreviewMapEl = ref(null);
let registerPreviewMap = null;
let registerPreviewMarker = null;

function ensureRegisterPreviewMap() {
  if (registerPreviewMap || !registerPreviewMapEl.value) return;
  registerPreviewMap = L.map(registerPreviewMapEl.value).setView([3.139, 101.6869], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(registerPreviewMap);
}

function updateRegisterPreviewMap(coordStr) {
  const coord = parseCoordinate(coordStr);
  if (!coord) {
    if (registerPreviewMarker) { registerPreviewMarker.remove(); registerPreviewMarker = null; }
    return;
  }
  nextTick(() => {
    ensureRegisterPreviewMap();
    if (!registerPreviewMap) return;
    registerPreviewMap.invalidateSize();
    registerPreviewMap.setView([coord.lat, coord.lng], 16);
    if (registerPreviewMarker) {
      registerPreviewMarker.setLatLng([coord.lat, coord.lng]);
    } else {
      const icon = L.divIcon({
        html: '<div style="width:26px;height:26px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:#1d4ed8;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.4)"></div>',
        className: '', iconSize: [26, 26], iconAnchor: [13, 26],
      });
      registerPreviewMarker = L.marker([coord.lat, coord.lng], { icon, draggable: true }).addTo(registerPreviewMap);
      // Dragging the pin corrects the coordinate directly — useful when a resolved
      // link is close but not exact (e.g. the building centroid vs. the actual pole).
      registerPreviewMarker.on('dragend', () => {
        const pos = registerPreviewMarker.getLatLng();
        form.value.coordinate = `${pos.lat.toFixed(6)}, ${pos.lng.toFixed(6)}`;
      });
    }
  });
}

watch(() => form.value.coordinate, updateRegisterPreviewMap);

watch(() => props.open, (open) => {
  if (open) {
    // Clear any stale search state left over from the last time this modal was used.
    locationInput.value = '';
    locationError.value = '';
    locationResolving.value = false;
    locationSearching.value = false;
    locationResults.value = [];
    landmarkFetching.value = false;
    landmarkFetched.value = false;
  } else if (registerPreviewMap) {
    registerPreviewMap.remove();
    registerPreviewMap = null;
    registerPreviewMarker = null;
  }
});

const landmarkFetching = ref(false);
const landmarkFetched = ref(false);

function parseMapsLink(url) {
  // Normalise URL-encoded spaces after commas (Google search redirects use "lat,+lng")
  const u = url.replace(/,\+/g, ',').replace(/,\s/g, ',');

  // Street View share links encode the camera position as @lat,lng,<N>a,<heading>h,<tilt>t
  // — note the "a" (altitude/pitch) suffix, versus a normal map link's "z" (zoom level):
  // @lat,lng,17z. If the user reached that spot via a place search first, the URL can
  // still carry a leftover !3d!4d for that ORIGINAL place, which may sit meters away from
  // where the camera is actually pointed — so for a Street View link, @ is the one that
  // reflects what's actually being looked at, and must be checked before !3d!4d.
  let m = u.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+),\d+(?:\.\d+)?a,/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

  // Otherwise (a regular map/place link), the precise pinned-marker coordinate
  // (Google's !3d<lat>!4d<lng> data params) is more reliable than the @lat,lng viewport
  // center, which can drift from the actual pin if the map was panned/zoomed afterwards.
  m = u.match(/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/\/search\/(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/[?&]q=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/[?&]ll=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  m = u.match(/\/(-?\d{1,3}\.\d{4,}),(-?\d{1,3}\.\d{4,})/);
  if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
  return null;
}

// --- Unified location input (Maps link OR place name search) ---
function onLocationInput() {
  clearTimeout(locationTimer);
  locationError.value = '';
  locationResults.value = [];
  form.value.coordinate = '';

  const val = locationInput.value.trim();
  if (!val) return;

  // Raw "lat, lng" pasted directly (e.g. from a GPS device during a site survey) —
  // handle it as literal coordinates rather than relying on Nominatim's place-name
  // search to happen to interpret a bare number pair correctly.
  const rawCoord = val.match(/^(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)$/);
  if (rawCoord) {
    const lat = parseFloat(rawCoord[1]);
    const lng = parseFloat(rawCoord[2]);
    if (Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
      form.value.coordinate = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
      return;
    }
  }

  if (val.startsWith('http://') || val.startsWith('https://')) {
    const direct = parseMapsLink(val);
    if (direct) {
      form.value.coordinate = `${direct.lat.toFixed(6)}, ${direct.lng.toFixed(6)}`;
      return;
    }
    if (val.length > 15) {
      locationTimer = setTimeout(() => resolveMapsUrl(val), 400);
      return;
    }
    locationError.value = 'Invalid Google Maps URL.';
    return;
  }

  locationTimer = setTimeout(searchLocations, 400);
}

async function resolveMapsUrl(url) {
  locationResolving.value = true;
  locationError.value = '';
  try {
    const res = await api.post('/v1/site-availability/resolve-maps-url', { url });
    const lat = parseFloat(res.data.lat);
    const lng = parseFloat(res.data.lng);
    form.value.coordinate = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  } catch (e) {
    locationError.value = e.response?.data?.error ?? 'Could not extract coordinates from this link.';
  } finally {
    locationResolving.value = false;
  }
}

async function searchLocations() {
  locationSearching.value = true;
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(locationInput.value)}&limit=6&countrycodes=my`,
      { headers: { 'Accept-Language': 'en' } },
    );
    locationResults.value = await res.json();
  } catch {
    locationResults.value = [];
  } finally {
    locationSearching.value = false;
  }
}

function selectLocationResult(result) {
  const lat = parseFloat(result.lat).toFixed(6);
  const lng = parseFloat(result.lon).toFixed(6);
  form.value.coordinate = `${lat}, ${lng}`;
  locationInput.value = result.display_name;
  locationResults.value = [];
}

function refreshLandmarks() {
  const parsed = parseCoordinate(form.value.coordinate);
  if (parsed) fetchNearestLandmarks(parsed.lat, parsed.lng);
}

// --- Overpass API landmark fetching ---
function haversineKm(lat1, lng1, lat2, lng2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// One merged Overpass query → categorise client-side.
// Avoids 9 parallel requests which quickly hit rate limits on the public endpoint.
async function fetchNearestLandmarks(lat, lng) {
  landmarkFetching.value = true;
  landmarkFetched.value = false;

  const r = 3000;
  const q = `[out:json][timeout:30];
(
  node["shop"~"^(mall|shopping_centre)$"]["name"](around:${r},${lat},${lng});
  way["shop"~"^(mall|shopping_centre)$"]["name"](around:${r},${lat},${lng});
  relation["shop"~"^(mall|shopping_centre)$"]["name"](around:${r},${lat},${lng});
  node["amenity"="school"]["name"](around:${r},${lat},${lng});
  way["amenity"="school"]["name"](around:${r},${lat},${lng});
  node["place"~"^(neighbourhood|suburb|village|town)$"]["name"](around:${r},${lat},${lng});
  node["amenity"~"^(hospital|clinic)$"]["name"](around:${r},${lat},${lng});
  way["amenity"~"^(hospital|clinic)$"]["name"](around:${r},${lat},${lng});
  node["railway"~"^(station|halt)$"]["name"](around:${r},${lat},${lng});
  node["public_transport"="station"]["name"](around:${r},${lat},${lng});
  node["amenity"="fuel"]["name"](around:${r},${lat},${lng});
  node["amenity"~"^(university|college)$"]["name"](around:${r},${lat},${lng});
  way["amenity"~"^(university|college)$"]["name"](around:${r},${lat},${lng});
  node["amenity"="police"]["name"](around:${r},${lat},${lng});
  node["amenity"="townhall"]["name"](around:${r},${lat},${lng});
  node["office"="government"]["name"](around:${r},${lat},${lng});
);
out center 300;`;

  const CATEGORIES = [
    { category: 'Shopping Mall',        test: (t) => t.shop === 'mall' || t.shop === 'shopping_centre' },
    { category: 'School',               test: (t) => t.amenity === 'school' },
    { category: 'Residential Area',     test: (t) => ['neighbourhood','suburb','village','town'].includes(t.place) },
    { category: 'Hospital / Clinic',    test: (t) => t.amenity === 'hospital' || t.amenity === 'clinic' },
    { category: 'MRT / LRT Station',    test: (t) => t.railway === 'station' || t.railway === 'halt' || t.public_transport === 'station' },
    { category: 'Petrol Station',       test: (t) => t.amenity === 'fuel' },
    { category: 'University / College', test: (t) => t.amenity === 'university' || t.amenity === 'college' },
    { category: 'Police Station',       test: (t) => t.amenity === 'police' },
    { category: 'Government Office',    test: (t) => t.amenity === 'townhall' || t.office === 'government' },
  ];

  const ENDPOINTS = [
    'https://overpass-api.de/api/interpreter',
    'https://lz4.overpass-api.de/api/interpreter',
  ];

  let elements = [];
  for (const endpoint of ENDPOINTS) {
    try {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), 28000);
      const res = await fetch(endpoint, { method: 'POST', body: q, signal: ctrl.signal });
      clearTimeout(timer);
      const data = await res.json();
      elements = data?.elements ?? [];
      break;
    } catch { /* try next endpoint */ }
  }

  form.value.nearest_landmarks = CATEGORIES.map(({ category, test }) => {
    const best = elements
      .filter((e) => e.tags?.name && test(e.tags))
      .map((e) => {
        const elat = e.lat ?? e.center?.lat;
        const elng = e.lon ?? e.center?.lon;
        if (elat == null || elng == null) return null;
        return { name: e.tags.name, km: haversineKm(lat, lng, elat, elng) };
      })
      .filter(Boolean)
      .sort((a, b) => a.km - b.km)[0];
    return { category, place: best ? `${best.km.toFixed(1)}km — ${best.name}` : 'Not Found' };
  });

  landmarkFetched.value = true;
  landmarkFetching.value = false;
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
.modal-backdrop {
  position: fixed; inset: 0; z-index: 2000; background: rgba(15,23,42,0.45);
  display: flex; align-items: center; justify-content: center; padding: 24px;
  animation: overlay-fade-in 0.18s ease;
}
.modal-backdrop > * { animation: modal-spring-in 0.26s cubic-bezier(0.34, 1.4, 0.64, 1); }

.error-msg {
  background: var(--danger-soft); color: var(--danger); border: 1px solid var(--danger-soft); border-radius: var(--radius-sm);
  padding: 10px 14px; margin-bottom: 14px; font-size: 13px; font-weight: 600;
}

.field { display: flex; flex-direction: column; gap: 5px; min-width: 150px; }
.field label {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-2);
}
.field input, .field select {
  height: 38px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 0 12px;
  font-size: 13px; outline: none; background: var(--surface); color: var(--text-1);
}
.field input:focus, .field select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.field-hint { margin-left: 6px; font-size: 10px; font-weight: 600; color: var(--text-3); text-transform: none; letter-spacing: 0; }
.req-star { color: var(--danger); font-weight: 700; margin-left: 1px; }

.detail-close {
  flex-shrink: 0; width: 30px; height: 30px; border: none; margin-top: 2px;
  border-radius: var(--radius-sm); background: var(--surface-2); color: var(--text-1);
  font-size: 20px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.detail-close:hover { background: var(--border); }

.btn-add, .btn-clear {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 16px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-add { background: var(--primary); color: var(--primary-on); box-shadow: 0 6px 18px -6px rgba(29,78,216,0.4); }
.btn-add:hover { background: var(--primary-hover); }
.btn-add:disabled { background: var(--text-3); cursor: not-allowed; box-shadow: none; }
.btn-clear { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
.btn-clear:hover { background: var(--border); color: var(--text-1); }

/* Register Product modal */
.register-modal {
  width: min(720px, 95vw); max-height: 85vh; background: var(--surface); color: var(--text-1);
  border-radius: var(--radius-lg); overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2); display: flex; flex-direction: column;
}
.register-modal-header {
  position: relative; background: var(--text-1); color: var(--primary-on); padding: 18px 20px 14px;
  display: flex; align-items: flex-start; justify-content: space-between; flex-shrink: 0;
}
.register-modal-header h2 { margin: 0; font-size: 15px; font-weight: 700; }
.register-modal-header .detail-close { background: rgba(255,255,255,0.12); color: var(--primary-on); }
.register-modal-header .detail-close:hover { background: rgba(255,255,255,0.22); }
.register-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.register-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.register-full { grid-column: 1 / -1; }
.register-site-row { display: flex; gap: 8px; align-items: center; }
.register-site-row input { flex: 1; min-width: 0; }
.field-loc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
.btn-open-gmaps {
  flex-shrink: 0; height: 36px; padding: 0 14px; border-radius: var(--radius-sm);
  background: var(--text-1); color: var(--primary-on); font-size: 13px; font-weight: 600;
  text-decoration: none; display: flex; align-items: center; white-space: nowrap;
}
.btn-open-gmaps:hover { opacity: 0.88; }
.coord-preview { margin-top: 5px; font-size: 11px; font-weight: 700; color: var(--primary); }
.location-preview-map {
  margin-top: 8px; height: 220px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); overflow: hidden; isolation: isolate;
}
.location-preview-hint { margin: 6px 0 0; font-size: 11px; color: var(--text-3); }
.register-modal-footer { display: flex; flex-direction: column; gap: 10px; padding: 12px 20px 16px; border-top: 1px solid var(--border); flex-shrink: 0; }
.register-footer-note { margin: 0; font-size: 11.5px; color: var(--text-3); line-height: 1.5; }
.register-footer-note strong { color: var(--text-2); }
.register-footer-actions { display: flex; justify-content: flex-end; gap: 8px; }
.location-input-wrap { position: relative; display: flex; align-items: center; gap: 8px; }
.location-input-wrap input { flex: 1; min-width: 0; width: 100%; box-sizing: border-box; }
.location-status { font-size: 11px; font-weight: 700; color: var(--primary); white-space: nowrap; display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.maps-link-error { margin-top: 5px; font-size: 11px; font-weight: 700; color: var(--danger); }
.input-error-border { border-color: var(--danger) !important; }

.place-results {
  position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 200;
  background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow-md); overflow: hidden; max-height: 220px; overflow-y: auto;
}
.place-result-item {
  width: 100%; min-height: 38px; border: none; border-bottom: 1px solid var(--border-soft);
  background: var(--surface); color: var(--text-1); text-align: left; padding: 8px 12px;
  font-size: 12px; font-weight: 600; cursor: pointer; display: block; line-height: 1.4;
}
.place-result-item:last-child { border-bottom: none; }
.place-result-item:hover { background: var(--surface-2); }

/* Landmark auto-fill */
.landmark-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.landmark-fetch-status { margin-left: 8px; font-size: 10px; font-weight: 700; text-transform: none; letter-spacing: 0; color: var(--text-3); }
.landmark-fetch-ok { color: var(--success); }
.btn-refresh-landmarks {
  flex-shrink: 0; height: 30px; padding: 0 14px; border: none; border-radius: var(--radius-sm);
  background: var(--primary); color: var(--primary-on); font-size: 11px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 5px; transition: opacity 0.15s;
}
.btn-refresh-landmarks:hover:not(:disabled) { opacity: 0.88; }
.btn-refresh-landmarks:disabled { opacity: 0.55; cursor: not-allowed; }
.register-landmark-table { width: 100%; border-collapse: collapse; margin-top: 0; table-layout: fixed; }
.register-landmark-table th {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  color: var(--primary-on); background: var(--text-1); padding: 6px 10px; text-align: left;
}
.register-landmark-table th:first-child { width: 36%; }
.register-landmark-table tbody tr { border-bottom: 1px solid var(--border-soft); }
.register-landmark-table tbody tr:last-child { border-bottom: none; }
.lm-cat-cell { padding: 7px 10px; font-size: 12px; font-weight: 700; color: var(--text-2); white-space: nowrap; }
.lm-place-cell { padding: 7px 10px; font-size: 12px; font-weight: 600; color: var(--text-1); }
.lm-not-found { color: var(--text-3); font-style: italic; }
.lm-place-input {
  width: 100%; min-height: 30px; border: 1.5px solid transparent; border-radius: 4px;
  padding: 0 8px; font-size: 12px; font-weight: 600; color: var(--text-1);
  background: transparent; outline: none; transition: border-color 0.15s;
}
.lm-place-input:hover { border-color: var(--border); background: var(--surface); }
.lm-place-input:focus { border-color: var(--primary); background: var(--surface); box-shadow: 0 0 0 3px var(--primary-soft); }
.lm-place-input::placeholder { color: var(--text-3); font-style: italic; font-weight: 500; }
.lm-place-input.lm-not-found { color: var(--text-3); font-style: italic; }
@keyframes lm-skeleton-shine { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }
.lm-skeleton {
  display: inline-block; width: 70%; height: 12px; border-radius: var(--radius-sm);
  background: var(--border); animation: lm-skeleton-shine 1.2s ease-in-out infinite;
}
@keyframes lm-spin { to { transform: rotate(360deg); } }
.lm-spinner {
  display: inline-block; width: 11px; height: 11px; border: 2px solid var(--border);
  border-top-color: var(--primary); border-radius: 50%; animation: lm-spin 0.7s linear infinite; vertical-align: middle;
}

.btn-stage-pdf {
  height: 36px; border: none; border-radius: var(--radius-sm); padding: 0 14px;
  font-size: 13px; font-weight: 700; cursor: pointer;
  background: var(--warning); color: var(--primary-on);
}
.btn-stage-pdf:hover:not(:disabled) { filter: brightness(0.9); }
.btn-stage-pdf:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 760px) {
  .register-form-grid { grid-template-columns: 1fr; }
}
</style>
