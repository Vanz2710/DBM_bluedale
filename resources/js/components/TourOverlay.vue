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
            <!-- Arrow — hidden in the centered fallback (no spotlight target) -->
            <div v-if="spotlightStyle" class="tour-arrow" :class="'arrow-' + (currentStep.position ?? 'right')"></div>

            <div class="tour-header">
              <span class="tour-step-badge">{{ currentIndex + 1 }} / {{ activeSteps.length }}</span>
              <button class="tour-close" @click="skip" title="Skip tour">✕</button>
            </div>

            <h3 class="tour-title">{{ currentStep.title }}</h3>
            <p class="tour-body">{{ currentStep.body }}</p>

            <!-- Progress dots -->
            <div class="tour-dots">
              <span
                v-for="(_, i) in activeSteps"
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
import { useTour } from '../composables/useTour.js';

const { active, currentIndex, currentStep, isFirst, isLast, activeSteps, next, prev, skip, finish } = useTour();

const tooltipEl    = ref(null);
const spotlightStyle = ref(null);
const tooltipStyle   = ref(null);

const PAD = 10;  // padding around highlighted element
const GAP = 18;  // gap between spotlight and tooltip

// Wait for two animation frames — first lets the browser process a scroll,
// second lets layout recalculate so getBoundingClientRect is accurate.
function rafDouble() {
  return new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
}

async function recalculate() {
  if (!active.value || !currentStep.value) return;

  await nextTick();

  const step = currentStep.value;
  const el   = document.querySelector(step.target);
  const vw   = window.innerWidth;
  const vh   = window.innerHeight;

  if (el) {
    // Instant scroll so getBoundingClientRect reflects the final position immediately.
    // (smooth scroll is async with no completion callback, causing spotlight misalignment)
    el.scrollIntoView({ block: 'center', behavior: 'instant' });
    await rafDouble();
  } else {
    // Target not in DOM for this view — clear spotlight and center the tooltip
    spotlightStyle.value = null;
    await nextTick();
    const tipW = tooltipEl.value?.offsetWidth  ?? 300;
    const tipH = tooltipEl.value?.offsetHeight ?? 200;
    tooltipStyle.value = {
      top:  (vh / 2 - tipH / 2) + 'px',
      left: (vw / 2 - tipW / 2) + 'px',
    };
    return;
  }

  const rect = el.getBoundingClientRect();

  // Spotlight
  spotlightStyle.value = {
    top:    (rect.top    - PAD) + 'px',
    left:   (rect.left   - PAD) + 'px',
    width:  (rect.width  + PAD * 2) + 'px',
    height: (rect.height + PAD * 2) + 'px',
  };

  // Wait for tooltip to render so we can read its size
  await nextTick();
  const tipW = tooltipEl.value?.offsetWidth  ?? 300;
  const tipH = tooltipEl.value?.offsetHeight ?? 200;

  const pos   = step.position ?? 'right';
  let   top   = 0;
  let   left  = 0;
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
  top = Math.max(12, Math.min(top, vh - tipH - 12));
  if (right !== null) {
    right = Math.max(8, Math.min(right, vw - tipW - 8));
  } else {
    left = Math.max(12, Math.min(left, vw - tipW - 12));
  }

  tooltipStyle.value = right !== null
    ? { top: top + 'px', right: right + 'px' }
    : { top: top + 'px', left: left + 'px' };
}

function jumpTo(i) {
  currentIndex.value = i;
}

watch([active, currentIndex], recalculate, { immediate: true });

// Lock body scroll while tour is open so the page can't be scrolled behind the overlay
watch(active, (val) => {
  document.body.style.overflow = val ? 'hidden' : '';
});

// Recalculate on window resize
function onResize() { recalculate(); }
window.addEventListener('resize', onResize);
import { onUnmounted } from 'vue';
onUnmounted(() => {
  window.removeEventListener('resize', onResize);
  document.body.style.overflow = '';
});
</script>

<style scoped>
.tour-root {
  position: fixed; inset: 0; z-index: 9000;
  pointer-events: all;
}

/* Spotlight — box-shadow IS the dark overlay */
.tour-spotlight {
  position: fixed;
  border-radius: 10px;
  background: transparent;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.62), 0 0 0 3px #1d4ed8, 0 0 0 5px rgba(29,78,216,0.3);
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
  font-size: 11px; font-weight: 700; color: #1d4ed8;
  background: #dbeafe; padding: 2px 8px; border-radius: 999px;
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
.tour-dot.active { background: #1d4ed8; width: 18px; border-radius: 3px; }
.tour-dot.done   { background: #60a5fa; }

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
  background: #1d4ed8; color: #ffffff; border: none; cursor: pointer;
  box-shadow: 0 4px 12px -4px rgba(29,78,216,0.5);
  transition: background 0.15s, box-shadow 0.15s;
}
.tour-btn-next:hover { background: #1e40af; }

/* Transitions */
.tour-fade-enter-active, .tour-fade-leave-active { transition: opacity 0.25s; }
.tour-fade-enter-from,  .tour-fade-leave-to      { opacity: 0; }

.tour-pop-enter-active  { transition: opacity 0.2s, transform 0.2s; }
.tour-pop-leave-active  { transition: opacity 0.15s; }
.tour-pop-enter-from    { opacity: 0; transform: scale(0.92) translateY(6px); }
.tour-pop-leave-to      { opacity: 0; }
</style>
