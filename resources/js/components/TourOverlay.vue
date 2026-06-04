<template>
  <Teleport to="body">
    <Transition name="tour-fade">
      <div v-if="active" class="tour-root" @keydown.escape="skip" tabindex="-1">

        <!-- Spotlight box — box-shadow creates the dark overlay around it -->
        <div
          v-if="spotlightStyle"
          class="tour-spotlight"
          :style="spotlightStyle"
        ></div>

        <!-- Tooltip card -->
        <Transition name="tour-pop">
          <div
            v-if="tooltipStyle && currentStep"
            class="tour-tooltip"
            :style="tooltipStyle"
            :key="'tip-' + currentIndex"
            ref="tooltipEl"
          >
            <!-- Arrow -->
            <div class="tour-arrow" :class="'arrow-' + (currentStep.position ?? 'right')"></div>

            <div class="tour-header">
              <span class="tour-step-badge">{{ currentIndex + 1 }} / {{ TOUR_STEPS.length }}</span>
              <button class="tour-close" @click="skip" title="Skip tour">✕</button>
            </div>

            <h3 class="tour-title">{{ currentStep.title }}</h3>
            <p class="tour-body">{{ currentStep.body }}</p>

            <!-- Progress dots -->
            <div class="tour-dots">
              <span
                v-for="(_, i) in TOUR_STEPS"
                :key="i"
                :class="['tour-dot', { active: i === currentIndex, done: i < currentIndex }]"
                @click="jumpTo(i)"
              ></span>
            </div>

            <div class="tour-actions">
              <button class="tour-btn-ghost" @click="skip">Skip tour</button>
              <div class="tour-nav">
                <button v-if="!isFirst" class="tour-btn-back" @click="prev">← Back</button>
                <button class="tour-btn-next" @click="next">
                  {{ isLast ? '🎉 Finish' : 'Next →' }}
                </button>
              </div>
            </div>
          </div>
        </Transition>

      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue';
import { useTour, TOUR_STEPS } from '../composables/useTour.js';

const { active, currentIndex, currentStep, isFirst, isLast, next, prev, skip, finish } = useTour();

const tooltipEl    = ref(null);
const spotlightStyle = ref(null);
const tooltipStyle   = ref(null);

const PAD = 10;  // padding around highlighted element
const GAP = 18;  // gap between spotlight and tooltip

async function recalculate() {
  if (!active.value || !currentStep.value) return;

  await nextTick();

  const step = currentStep.value;
  const el   = document.querySelector(step.target);
  if (!el) return;

  // Bring element into view
  el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  await nextTick();

  const rect = el.getBoundingClientRect();
  const vw   = window.innerWidth;
  const vh   = window.innerHeight;

  // Spotlight
  spotlightStyle.value = {
    top:    (rect.top    - PAD) + 'px',
    left:   (rect.left   - PAD) + 'px',
    width:  (rect.width  + PAD * 2) + 'px',
    height: (rect.height + PAD * 2) + 'px',
  };

  // Tooltip position — wait one tick for the tooltip to render so we can read its size
  await nextTick();
  const tipW = tooltipEl.value?.offsetWidth  ?? 300;
  const tipH = tooltipEl.value?.offsetHeight ?? 200;

  const pos  = step.position ?? 'right';
  let   top  = 0;
  let   left = 0;
  let   right = null;

  if (pos === 'right') {
    left = rect.right + PAD + GAP;
    top  = rect.top + rect.height / 2 - tipH / 2;
  } else if (pos === 'left') {
    left = rect.left - PAD - GAP - tipW;
    top  = rect.top + rect.height / 2 - tipH / 2;
  } else if (pos === 'bottom') {
    left = rect.left + rect.width / 2 - tipW / 2;
    top  = rect.bottom + PAD + GAP;
  } else if (pos === 'bottom-left') {
    right = vw - rect.right - PAD;
    top   = rect.bottom + PAD + GAP;
  }

  // Clamp to viewport
  top  = Math.max(12, Math.min(top,  vh - tipH - 12));
  if (right !== null) {
    right = Math.max(8, Math.min(right, vw - tipW - 8));
  } else {
    left  = Math.max(12, Math.min(left, vw - tipW - 12));
  }

  tooltipStyle.value = right !== null
    ? { top: top + 'px', right: right + 'px' }
    : { top: top + 'px', left: left + 'px' };
}

function jumpTo(i) {
  currentIndex.value = i;
}

watch([active, currentIndex], recalculate, { immediate: true });

// Recalculate on window resize
function onResize() { recalculate(); }
window.addEventListener('resize', onResize);
import { onUnmounted } from 'vue';
onUnmounted(() => window.removeEventListener('resize', onResize));
</script>

<style scoped>
.tour-root {
  position: fixed; inset: 0; z-index: 9000;
  pointer-events: none;
}

/* Spotlight — box-shadow IS the dark overlay */
.tour-spotlight {
  position: fixed;
  border-radius: 10px;
  background: transparent;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.62), 0 0 0 3px #7c3aed, 0 0 0 5px rgba(124,58,237,0.3);
  pointer-events: none;
  z-index: 9001;
  transition: top 0.35s cubic-bezier(0.4,0,0.2,1),
              left 0.35s cubic-bezier(0.4,0,0.2,1),
              width 0.35s cubic-bezier(0.4,0,0.2,1),
              height 0.35s cubic-bezier(0.4,0,0.2,1);
}

/* Tooltip card */
.tour-tooltip {
  position: fixed;
  width: 300px;
  background: #ffffff;
  border-radius: 14px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.06);
  padding: 18px 20px 16px;
  z-index: 9002;
  pointer-events: all;
}

/* Arrow */
.tour-arrow {
  position: absolute;
  width: 10px; height: 10px;
  background: #ffffff;
  transform: rotate(45deg);
  box-shadow: -2px -2px 4px rgba(0,0,0,0.05);
}
.arrow-right   { left: -5px;  top: 50%; transform: translateY(-50%) rotate(45deg); }
.arrow-left    { right: -5px; top: 50%; transform: translateY(-50%) rotate(45deg); }
.arrow-bottom  { top: -5px;   left: 50%; transform: translateX(-50%) rotate(45deg); }
.arrow-bottom-left { top: -5px; right: 24px; transform: rotate(45deg); }

.tour-header {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
}

.tour-step-badge {
  font-size: 11px; font-weight: 700; color: #7c3aed;
  background: #ede9fe; padding: 2px 8px; border-radius: 999px;
}

.tour-close {
  background: none; border: none; cursor: pointer;
  color: #94a3b8; font-size: 13px; padding: 2px 4px; line-height: 1;
  border-radius: 4px; transition: color 0.15s, background 0.15s;
}
.tour-close:hover { color: #ef4444; background: #fee2e2; }

.tour-title {
  font-size: 15px; font-weight: 700; color: #1e293b;
  margin: 0 0 8px; line-height: 1.3;
}

.tour-body {
  font-size: 13px; color: #64748b; line-height: 1.6;
  margin: 0 0 14px;
}

/* Progress dots */
.tour-dots {
  display: flex; gap: 5px; justify-content: center; margin-bottom: 14px;
}
.tour-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: #e2e8f0; cursor: pointer; transition: all 0.2s;
}
.tour-dot.active { background: #7c3aed; width: 18px; border-radius: 3px; }
.tour-dot.done   { background: #a78bfa; }

.tour-actions {
  display: flex; justify-content: space-between; align-items: center; gap: 8px;
}

.tour-btn-ghost {
  font-size: 12px; color: #94a3b8; background: none; border: none;
  cursor: pointer; padding: 4px 0; transition: color 0.15s;
}
.tour-btn-ghost:hover { color: #64748b; }

.tour-nav { display: flex; gap: 8px; }

.tour-btn-back {
  padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 500;
  background: #f1f5f9; color: #64748b; border: none; cursor: pointer;
  transition: background 0.15s;
}
.tour-btn-back:hover { background: #e2e8f0; color: #334155; }

.tour-btn-next {
  padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
  background: #7c3aed; color: #ffffff; border: none; cursor: pointer;
  box-shadow: 0 4px 12px -4px rgba(124,58,237,0.5);
  transition: background 0.15s, box-shadow 0.15s;
}
.tour-btn-next:hover { background: #6d28d9; }

/* Transitions */
.tour-fade-enter-active, .tour-fade-leave-active { transition: opacity 0.25s; }
.tour-fade-enter-from,  .tour-fade-leave-to      { opacity: 0; }

.tour-pop-enter-active  { transition: opacity 0.2s, transform 0.2s; }
.tour-pop-leave-active  { transition: opacity 0.15s; }
.tour-pop-enter-from    { opacity: 0; transform: scale(0.92) translateY(6px); }
.tour-pop-leave-to      { opacity: 0; }
</style>
