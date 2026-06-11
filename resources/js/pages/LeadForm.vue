<template>
  <div class="lead-page">
    <div class="lead-card" v-if="!submitted">
      <div class="lead-header">
        <h1>Get in Touch</h1>
        <p>Fill in the form below and one of our team will contact you shortly.</p>
      </div>
      <form @submit.prevent="submit">
        <div v-if="errorMsg" class="error-box">{{ errorMsg }}</div>
        <div class="form-group">
          <label>Company Name <span class="req">*</span></label>
          <input v-model="form.company_name" placeholder="Your company name" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Your Name <span class="req">*</span></label>
            <input v-model="form.pic_name" placeholder="Full name" required>
          </div>
          <div class="form-group">
            <label>Phone Number <span class="req">*</span></label>
            <input v-model="form.pic_phone" placeholder="e.g. 0123456789" required>
          </div>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" v-model="form.pic_email" placeholder="you@example.com">
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea v-model="form.message" rows="4" placeholder="Tell us how we can help…"></textarea>
        </div>
        <button type="submit" class="btn-submit" :disabled="saving">
          {{ saving ? 'Sending…' : 'Submit Enquiry' }}
        </button>
      </form>
    </div>

    <div class="lead-card success-card" v-else>
      <div class="success-icon">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h2>Thank you!</h2>
      <p>We've received your enquiry and will be in touch with you shortly.</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const saving   = ref(false);
const submitted = ref(false);
const errorMsg = ref('');

const form = ref({
  company_name: '',
  pic_name: '',
  pic_phone: '',
  pic_email: '',
  message: '',
});

async function submit() {
  saving.value  = true;
  errorMsg.value = '';
  try {
    await axios.post('/api/public/lead', form.value);
    submitted.value = true;
  } catch (e) {
    const errors = e.response?.data?.errors;
    if (errors) {
      errorMsg.value = Object.values(errors).flat().join(' ');
    } else {
      errorMsg.value = e.response?.data?.message ?? 'Something went wrong. Please try again.';
    }
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.lead-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a2f4a 0%, #22c55e 100%);
  padding: 24px 16px;
}
.lead-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  padding: 40px 36px;
  width: 100%;
  max-width: 520px;
}
.lead-header { text-align: center; margin-bottom: 28px; }
.lead-header h1 { font-size: 24px; font-weight: 800; color: var(--text-1); letter-spacing: -0.3px; margin: 0 0 6px; }
.lead-header p { font-size: 14px; color: var(--text-2); margin: 0; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { margin-bottom: 16px; }
.form-group label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-2); margin-bottom: 6px;
}
.form-group input, .form-group textarea {
  width: 100%; height: 42px; padding: 0 14px; border: 1.5px solid var(--border);
  border-radius: var(--radius-sm); font-size: 13px; color: var(--text-1); outline: none;
  background: var(--surface); box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.form-group input::placeholder, .form-group textarea::placeholder { color: var(--text-3); }
.form-group textarea { height: 100px; padding: 10px 14px; resize: vertical; }
.form-group input:focus, .form-group textarea:focus {
  border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.12);
}
.req { color: var(--danger); }
.error-box {
  background: var(--danger-soft); color: var(--danger); border-radius: var(--radius-sm);
  padding: 10px 14px; font-size: 13px; margin-bottom: 16px;
}
.btn-submit {
  width: 100%; height: 46px; background: #22c55e; color: #fff;
  border: none; border-radius: var(--radius-sm); font-size: 15px; font-weight: 700;
  cursor: pointer; transition: background 0.15s;
}
.btn-submit:hover { background: #16a34a; }
.btn-submit:disabled { background: var(--text-3); cursor: not-allowed; }
.success-card { text-align: center; padding: 56px 36px; }
.success-icon {
  width: 64px; height: 64px; background: var(--success-soft); color: var(--success);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
}
.success-card h2 { font-size: 24px; font-weight: 800; color: var(--text-1); letter-spacing: -0.3px; margin: 0 0 8px; }
.success-card p { font-size: 14px; color: var(--text-2); margin: 0; }
@media (max-width: 480px) {
  .lead-card { padding: 28px 20px; }
  .form-row { grid-template-columns: 1fr; }
}
</style>
