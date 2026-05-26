<template>
  <div class="page">
    <router-link to="/travel" class="back-link">← Back to Travel Hub</router-link>

    <div v-if="loading" class="loading-msg">Loading…</div>

    <template v-else-if="location">
      <div class="profile-header">
        <h1>{{ location.company_name }}</h1>
        <span class="badge">{{ location.category ?? 'Uncategorized' }}</span>
        <span class="badge" style="margin-left:10px;">📍 {{ location.state ?? 'Unknown State' }}</span>
        <a :href="mapsLink" target="_blank" class="map-btn">🗺 Open in Google Maps</a>
      </div>

      <div class="profile-body">
        <div class="info-grid">
          <div>
            <div class="info-block">
              <div class="info-label">Full Address</div>
              <div class="info-value">{{ location.address || 'No address provided' }}</div>
            </div>

            <div class="info-label" style="margin-bottom:10px">Contact Persons (PIC)</div>
            <div v-if="location.contacts?.length" v-for="c in location.contacts" :key="c.id" class="contact-box">
              <strong>{{ c.contact_name }}</strong><br>
              📞 {{ c.phone }}<br>
              ✉ {{ c.email }}
            </div>
            <p v-else style="color:#95a5a6;font-style:italic;margin:0">No contacts listed.</p>
          </div>

          <div>
            <div class="info-label" style="margin-bottom:10px">Specific Details</div>
            <div v-if="location.extra && Object.keys(location.extra).length" class="json-grid">
              <div v-for="(val, key) in location.extra" :key="key" class="json-row">
                <span class="json-key">{{ key }}</span>
                <span class="json-val">{{ val }}</span>
              </div>
            </div>
            <div v-else class="no-extra">No extra details found for this location.</div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="loading-msg">Location not found.</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from '../api.js';

const route    = useRoute();
const loading  = ref(true);
const location = ref(null);

const mapsLink = computed(() => {
  if (!location.value) return '#';
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location.value.company_name + ' ' + (location.value.state ?? ''))}`;
});

onMounted(async () => {
  const { data } = await axios.get(`/v1/travel/${route.params.id}`);
  location.value = data.data;
  loading.value  = false;
});
</script>

<style scoped>
.page { max-width: 900px; margin: 0 auto; padding: 28px 24px; }
.loading-msg { padding: 60px; text-align: center; color: #94a3b8; }
.back-link { display:inline-block; margin-bottom:20px; text-decoration:none; color:#1abc9c; font-weight:bold; background:white; padding:10px 15px; border-radius:4px; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
.back-link:hover { background:#f8f9fa; }

.profile-header { background:linear-gradient(135deg,#1abc9c,#16a085); color:white; padding:40px; border-radius:8px 8px 0 0; position:relative; }
.profile-header h1 { margin:0 0 10px; font-size:32px; }
.badge { background:rgba(255,255,255,0.2); padding:5px 12px; border-radius:15px; font-size:14px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; }
.map-btn { position:absolute; bottom:-20px; right:40px; background:#e74c3c; color:white; text-decoration:none; padding:12px 25px; border-radius:25px; font-weight:bold; box-shadow:0 4px 10px rgba(231,76,60,0.4); display:flex; align-items:center; gap:8px; transition:transform 0.2s; }
.map-btn:hover { transform:translateY(-3px); }

.profile-body { background:white; padding:40px; border-radius:0 0 8px 8px; box-shadow:0 4px 6px rgba(0,0,0,0.05); }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:30px; }
.info-block { margin-bottom:20px; }
.info-label { color:#7f8c8d; font-size:12px; text-transform:uppercase; font-weight:bold; margin-bottom:5px; }
.info-value { font-size:16px; color:#2c3e50; line-height:1.5; }

.contact-box { background:#f8f9fa; padding:15px; border-radius:8px; border-left:4px solid #3498db; margin-bottom:10px; line-height:1.6; font-size:14px; }

.json-grid { background:#fdfbf7; padding:25px; border-radius:8px; border:1px solid #f1c40f; display:flex; flex-direction:column; gap:10px; }
.json-row  { display:flex; flex-direction:column; border-bottom:1px solid #f2e3a1; padding-bottom:10px; }
.json-row:last-child { border-bottom:none; padding-bottom:0; }
.json-key { font-weight:bold; color:#d35400; font-size:14px; text-transform:uppercase; }
.json-val { font-size:16px; color:#2c3e50; margin-top:5px; }
.no-extra { padding:20px; background:#fdfbf7; border-radius:8px; color:#7f8c8d; text-align:center; font-size:14px; }
</style>
