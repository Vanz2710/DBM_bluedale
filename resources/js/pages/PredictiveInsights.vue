<template>
  <div class="pi">

    <!-- ══ Header ══════════════════════════════════════════════════════════════════ -->
    <div class="pi-header">
      <div class="pi-header-left">
        <h1>Predictive Insights</h1>
        <p class="pi-subtitle">Pipeline health, contact risks, and agent performance — grounded in your actual data.</p>
      </div>
      <div class="pi-header-actions">
        <div class="pi-date-wrap" ref="pickerRef">
          <button class="pi-date-btn" @click.stop="pickerOpen = !pickerOpen">
            <svg class="pi-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
            <span>{{ filters.label }}</span>
            <svg class="pi-caret" viewBox="0 0 10 6" fill="currentColor"><path d="M0 0l5 6 5-6z"/></svg>
          </button>
          <transition name="panel-drop">
            <div v-if="pickerOpen" class="pi-date-panel" @click.stop>
              <div class="pi-presets-section">
                <div class="pi-custom-title">Presets</div>
                <div class="pi-presets">
                  <button
                    v-for="p in PRESETS" :key="p.label"
                    class="pi-preset"
                    :class="{ 'pi-preset--on': filters.label === p.label }"
                    @click="applyPreset(p)"
                  >{{ p.label }}</button>
                </div>
              </div>
              <div class="pi-custom">
                <div class="pi-custom-title">Custom range</div>
                <div class="pi-custom-inline">
                  <input type="date" v-model="customFrom" :max="customTo" />
                  <span class="pi-custom-sep">to</span>
                  <input type="date" v-model="customTo" :min="customFrom" :max="todayStr" />
                  <button class="pi-apply-btn" @click="applyCustom">Apply</button>
                </div>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </div>

    <!-- ══ Filter Bar ══════════════════════════════════════════════════════════════ -->
    <div v-if="isAdmin" class="pi-filter-bar">
      <div class="pi-filter-group">
        <span class="pi-filter-label">AGENT</span>
        <select v-model="filters.user_id" @change="loadAll" class="pi-filter-select">
          <option value="">All Agents</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
    </div>

    <!-- ══ Date-range scope notice ═════════════════════════════════════════════════ -->
    <div class="pi-scope-note">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
      </svg>
      <span>The date range above only narrows <strong>Historical Win Rates</strong> and <strong>Deal Velocity</strong>. Every other card below reflects live data as of today.</span>
    </div>

    <!-- ══ Summary error banner ════════════════════════════════════════════════════ -->
    <div v-if="errors.summary" class="pi-error-banner">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span>Failed to load summary stats.</span>
      <button class="pi-retry-btn" @click="loadSummary">Retry</button>
    </div>

    <!-- ══ KPI Summary Row ══════════════════════════════════════════════════════════ -->
    <div class="pi-kpi-row">

      <!-- Neglected Contacts -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--danger">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">
            <div v-if="loading.summary" class="pi-kpi-skel"></div>
            <template v-else>{{ summary.neglected ?? '—' }}</template>
          </div>
          <div class="pi-kpi-label">Neglected Contacts</div>
          <div class="pi-kpi-sub">Potential/Existing, 60+ days since last interaction · live</div>
        </div>
      </div>

      <!-- Expected Pipeline -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--success">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="12" y1="1" x2="12" y2="23"/>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">
            <div v-if="loading.summary" class="pi-kpi-skel"></div>
            <template v-else>{{ summary.pipeline_value != null ? formatCurrency(summary.pipeline_value) : '—' }}</template>
          </div>
          <div class="pi-kpi-label">Expected Pipeline</div>
          <div class="pi-kpi-sub">Open deals × agent-set probability · live</div>
        </div>
      </div>

      <!-- Coverage Imbalance -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--warning">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <circle cx="12" cy="12" r="6"/>
            <circle cx="12" cy="12" r="2"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">
            <div v-if="loading.summary" class="pi-kpi-skel"></div>
            <template v-else>{{ summary.overloaded_agents ?? '—' }}</template>
          </div>
          <div class="pi-kpi-label">Overloaded Agents</div>
          <div class="pi-kpi-sub">Carrying 1.5× average portfolio · live</div>
        </div>
      </div>

      <!-- Unworked Opportunities -->
      <div class="pi-kpi-card">
        <div class="pi-kpi-icon pi-kpi-icon--info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
          </svg>
        </div>
        <div class="pi-kpi-body">
          <div class="pi-kpi-value">
            <div v-if="loading.summary" class="pi-kpi-skel"></div>
            <template v-else>{{ summary.unworked_opps ?? '—' }}</template>
          </div>
          <div class="pi-kpi-label">Unworked Opportunities</div>
          <div class="pi-kpi-sub">Active contacts, 30+ days since last interaction · live</div>
        </div>
      </div>

    </div>

    <!-- ══ Pipeline by Close Month + Neglected Contacts ═══════════════════════════ -->
    <div class="pi-row-asym">

      <!-- Pipeline by Close Month -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
              <polyline points="17 6 23 6 23 12"/>
            </svg>
            <span class="pi-card-title">Pipeline by Close Month</span>
          </div>
          <span class="pi-card-meta">Open deals grouped by expected close date, weighted by agent probability · live, not limited by the date range above</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.forecast" class="pi-skeleton-block"></div>
          <div v-else-if="errors.forecast" class="pi-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Failed to load pipeline data</p>
            <button class="pi-retry-btn" @click="loadForecast">Retry</button>
          </div>
          <div v-else-if="!forecast.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
              <polyline points="17 6 23 6 23 12"/>
            </svg>
            <p>No open deals with a close date</p>
          </div>
          <div v-else class="pi-chart-wrap" ref="forecastWrapRef">
            <canvas ref="forecastCanvasRef"></canvas>
          </div>
        </div>
      </div>

      <!-- Neglected Contacts -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon pi-card-icon--danger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span class="pi-card-title">Neglected Contacts</span>
          </div>
          <div class="pi-card-head-right">
            <span class="pi-card-meta">60+ days since last completed interaction · live</span>
            <button class="pi-export-btn" @click="exportNeglected" :disabled="!neglected.length">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              CSV
            </button>
          </div>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.neglected" class="pi-skeleton-list">
            <div v-for="i in 5" :key="i" class="pi-skeleton-row"></div>
          </div>
          <div v-else-if="errors.neglected" class="pi-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Failed to load neglected contacts</p>
            <button class="pi-retry-btn" @click="loadNeglected">Retry</button>
          </div>
          <div v-else-if="!neglected.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <p>All active contacts have been recently touched</p>
          </div>
          <ul v-else class="pi-risk-list">
            <li v-for="c in neglected" :key="c.id" class="pi-risk-item">
              <div class="pi-risk-left">
                <div class="pi-risk-name">{{ c.name }}</div>
                <div class="pi-risk-owner">{{ c.owner_name }} · <span class="pi-status-chip">{{ c.status_name }}</span></div>
              </div>
              <span class="pi-risk-badge" :class="neglectedBadgeClass(c.days_since_update)">{{ c.days_since_update }}d</span>
            </li>
          </ul>
        </div>
      </div>

    </div>

    <!-- ══ Agent Coverage Load + Unworked Segments ════════════════════════════════ -->
    <div class="pi-row-2col">

      <!-- Agent Coverage Load -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon pi-card-icon--warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="10"/>
              <circle cx="12" cy="12" r="6"/>
              <circle cx="12" cy="12" r="2"/>
            </svg>
            <span class="pi-card-title">Agent Coverage Load</span>
          </div>
          <span class="pi-card-meta">Total contacts per agent — bar shows actionable share · live</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.agentLoad" class="pi-skeleton-list">
            <div v-for="i in 4" :key="i" class="pi-skeleton-row"></div>
          </div>
          <div v-else-if="errors.agentLoad" class="pi-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Failed to load agent data</p>
            <button class="pi-retry-btn" @click="loadAgentLoad">Retry</button>
          </div>
          <div v-else-if="!agentLoad.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <circle cx="12" cy="12" r="10"/>
              <circle cx="12" cy="12" r="6"/>
              <circle cx="12" cy="12" r="2"/>
            </svg>
            <p>No agent data available</p>
          </div>
          <ul v-else class="pi-load-list">
            <li v-for="agent in agentLoad" :key="agent.user_id" class="pi-load-item">
              <div class="pi-load-header">
                <span class="pi-load-name">{{ agent.name }}</span>
                <span class="pi-load-stat">
                  {{ agent.total.toLocaleString() }}
                  <span class="pi-load-engaged-text">· {{ agent.engaged_count }} active</span>
                </span>
              </div>
              <div class="pi-load-track">
                <div
                  class="pi-load-bar"
                  :class="loadBarClass(agent.engaged_pct)"
                  :style="{ width: agent.load_pct + '%' }"
                ></div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Unworked Segment Opportunities -->
      <div class="pi-card">
        <div class="pi-card-head">
          <div class="pi-card-title-wrap">
            <svg class="pi-card-icon pi-card-icon--info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
            <span class="pi-card-title">Unworked Opportunities</span>
          </div>
          <span class="pi-card-meta">Active contacts per industry, 30+ days since last interaction · live</span>
        </div>
        <div class="pi-card-body">
          <div v-if="loading.unworked" class="pi-skeleton-list">
            <div v-for="i in 4" :key="i" class="pi-skeleton-row"></div>
          </div>
          <div v-else-if="errors.unworked" class="pi-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Failed to load opportunity data</p>
            <button class="pi-retry-btn" @click="loadUnworked">Retry</button>
          </div>
          <div v-else-if="!unworked.length" class="pi-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <p>All active contacts have been recently engaged</p>
          </div>
          <ul v-else class="pi-unworked-list">
            <li v-for="s in unworked" :key="s.industry_id" class="pi-unworked-item">
              <div class="pi-unworked-header">
                <span class="pi-unworked-name">{{ s.industry_name }}</span>
                <span class="pi-unworked-badge">{{ s.unworked }} unworked</span>
              </div>
              <div class="pi-unworked-bar-row">
                <div class="pi-mini-bar pi-mini-bar--wide">
                  <div class="pi-mini-fill pi-prob--warning" :style="{ width: s.unworked_pct + '%' }"></div>
                </div>
                <span class="pi-unworked-pct">{{ s.unworked_pct }}%</span>
                <span class="pi-unworked-of">of {{ s.total }}</span>
              </div>
            </li>
          </ul>
        </div>
      </div>

    </div>

    <!-- ══ Historical Win Rates ════════════════════════════════════════════════════ -->
    <div class="pi-card">
      <div class="pi-card-head">
        <div class="pi-card-title-wrap">
          <svg class="pi-card-icon pi-card-icon--success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <polyline points="22 4 12 14.01 9 11.01"/><path d="M22 4H15"/>
            <path d="M20 7L22 4"/><circle cx="5" cy="17" r="3"/><path d="M9 17H5m0 0V9"/>
          </svg>
          <span class="pi-card-title">Historical Win Rates</span>
        </div>
        <span class="pi-card-meta">From won / lost deals in the selected period</span>
      </div>
      <div class="pi-card-body">
        <div v-if="loading.winRates" class="pi-skeleton-list">
          <div v-for="i in 4" :key="i" class="pi-skeleton-row"></div>
        </div>
        <div v-else-if="errors.winRates" class="pi-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <p>Failed to load win rate data</p>
          <button class="pi-retry-btn" @click="loadWinRates">Retry</button>
        </div>
        <div v-else-if="!winRates.by_agent.length && !winRates.by_industry.length" class="pi-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
          <p>No closed deals in this period — win rates require won or lost deals</p>
        </div>
        <div v-else class="pi-wr-grid">
          <!-- By Agent -->
          <div v-if="winRates.by_agent.length">
            <div class="pi-wr-subtitle">By Agent</div>
            <div class="pi-table-wrap">
              <table class="pi-table">
                <thead><tr><th>Agent</th><th>Won</th><th>Total</th><th>Win Rate</th></tr></thead>
                <tbody>
                  <tr v-for="r in winRates.by_agent" :key="r.user_id">
                    <td class="pi-td-bold">{{ r.name }}</td>
                    <td>{{ r.won }}</td>
                    <td>{{ r.total }}</td>
                    <td><span class="pi-deal-pill" :class="winRatePillClass(r.win_rate)">{{ r.win_rate }}%</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- By Industry -->
          <div v-if="winRates.by_industry.length">
            <div class="pi-wr-subtitle">By Industry</div>
            <div class="pi-table-wrap">
              <table class="pi-table">
                <thead><tr><th>Industry</th><th>Won</th><th>Total</th><th>Win Rate</th></tr></thead>
                <tbody>
                  <tr v-for="r in winRates.by_industry" :key="r.industry_id">
                    <td class="pi-td-bold">{{ r.industry_name }}</td>
                    <td>{{ r.won }}</td>
                    <td>{{ r.total }}</td>
                    <td><span class="pi-deal-pill" :class="winRatePillClass(r.win_rate)">{{ r.win_rate }}%</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Deal Velocity ═══════════════════════════════════════════════════════════ -->
    <div class="pi-card">
      <div class="pi-card-head">
        <div class="pi-card-title-wrap">
          <svg class="pi-card-icon pi-card-icon--warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          <span class="pi-card-title">Deal Velocity</span>
        </div>
        <span class="pi-card-meta">
          <template v-if="velocity.benchmark_days">
            Won deals close in <strong>{{ velocity.benchmark_days }}d</strong> on average ({{ velocity.sample_size }} deals) · {{ velocity.stalling_count }} stalling
          </template>
          <template v-else>Open deals vs close-time benchmark from the selected period</template>
        </span>
      </div>
      <div class="pi-card-body">
        <div v-if="loading.velocity" class="pi-skeleton-list">
          <div v-for="i in 5" :key="i" class="pi-skeleton-row"></div>
        </div>
        <div v-else-if="errors.velocity" class="pi-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <p>Failed to load velocity data</p>
          <button class="pi-retry-btn" @click="loadVelocity">Retry</button>
        </div>
        <div v-else-if="!velocity.deals?.length" class="pi-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          <p>No open deals</p>
        </div>
        <div v-else-if="!velocity.benchmark_days" class="pi-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
          <p>No won deals in this period — close some deals to establish a benchmark</p>
        </div>
        <div v-else>
          <ul class="pi-vel-list">
            <li v-for="d in velocity.deals" :key="d.id" class="pi-vel-item">
              <div class="pi-vel-left">
                <span class="pi-vel-title">{{ d.title }}</span>
                <span class="pi-vel-contact">{{ d.contact_name }}</span>
              </div>
              <div class="pi-vel-mid">
                <span class="pi-vel-days">{{ d.days_open }}d open</span>
                <span class="pi-vel-vs" :class="d.vs_benchmark > 0 ? 'pi-vel-vs--over' : 'pi-vel-vs--under'">
                  {{ d.vs_benchmark > 0 ? '+' : '' }}{{ d.vs_benchmark }}d vs avg
                </span>
              </div>
              <div class="pi-vel-right">
                <span class="pi-vel-value">{{ formatCurrency(d.value) }}</span>
                <span v-if="d.is_stalling" class="pi-deal-pill pi-deal-pill--high-risk">Stalling</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- ══ Pipeline Coverage vs Targets ═══════════════════════════════════════════ -->
    <div class="pi-card">
      <div class="pi-card-head">
        <div class="pi-card-title-wrap">
          <svg class="pi-card-icon pi-card-icon--info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>
          </svg>
          <span class="pi-card-title">Pipeline Coverage</span>
        </div>
        <span class="pi-card-meta">Weighted open pipeline vs won_deal_value KPI target · live</span>
      </div>
      <div class="pi-card-body">
        <div v-if="loading.coverage" class="pi-skeleton-list">
          <div v-for="i in 3" :key="i" class="pi-skeleton-row"></div>
        </div>
        <div v-else-if="errors.coverage" class="pi-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <p>Failed to load coverage data</p>
          <button class="pi-retry-btn" @click="loadCoverage">Retry</button>
        </div>
        <div v-else-if="!coverage.length" class="pi-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>
          </svg>
          <p>No open pipeline found. Set KPI targets in Performance → Targets to see coverage.</p>
        </div>
        <ul v-else class="pi-cov-list">
          <li v-for="r in coverage" :key="r.user_id" class="pi-cov-item">
            <div class="pi-cov-header">
              <span class="pi-cov-name">{{ r.name }}</span>
              <span class="pi-cov-pct" :class="coveragePillClass(r.coverage_pct)">
                {{ r.coverage_pct !== null ? r.coverage_pct + '%' : 'No target' }}
              </span>
            </div>
            <div v-if="r.coverage_pct !== null" class="pi-load-track">
              <div
                class="pi-cov-bar"
                :class="coverageBarClass(r.coverage_pct)"
                :style="{ width: Math.min(r.coverage_pct, 100) + '%' }"
              ></div>
            </div>
            <div class="pi-cov-detail">
              {{ formatCurrency(r.weighted_pipeline) }} pipeline
              <template v-if="r.target > 0">
                · target {{ formatCurrency(r.target) }}
                <span v-if="r.gap > 0" class="pi-cov-gap">· gap {{ formatCurrency(r.gap) }}</span>
              </template>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- ══ Open Deals ══════════════════════════════════════════════════════════════ -->
    <div class="pi-card" data-tour="pi-deals">
      <div class="pi-card-head">
        <div class="pi-card-title-wrap">
          <svg class="pi-card-icon pi-card-icon--success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="2" y="7" width="20" height="14" rx="2"/>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
          <span class="pi-card-title">Open Deals</span>
        </div>
        <div class="pi-card-head-right">
          <span class="pi-card-meta">Activity = completed todos in last 30 days · live, not limited by the date range above</span>
          <button class="pi-export-btn" @click="exportDeals" :disabled="!deals.length">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            CSV
          </button>
        </div>
      </div>
      <div class="pi-card-body">
        <div v-if="loading.deals" class="pi-skeleton-block"></div>
        <div v-else-if="errors.deals" class="pi-error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <p>Failed to load deal data</p>
          <button class="pi-retry-btn" @click="loadDeals">Retry</button>
        </div>
        <div v-else-if="!deals.length" class="pi-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <rect x="2" y="7" width="20" height="14" rx="2"/>
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
          </svg>
          <p>No open deals found</p>
        </div>
        <div v-else class="pi-table-wrap">
          <table class="pi-table" aria-label="Open Deals">
            <thead>
              <tr>
                <th class="pi-th-sort" @click="toggleSort('title')">Deal <span v-if="sortKey === 'title'" class="pi-sort-ind">{{ sortDir === 'asc' ? '▲' : '▼' }}</span></th>
                <th class="pi-th-sort" @click="toggleSort('contact_name')">Contact <span v-if="sortKey === 'contact_name'" class="pi-sort-ind">{{ sortDir === 'asc' ? '▲' : '▼' }}</span></th>
                <th class="pi-th-sort" @click="toggleSort('value')">Value <span v-if="sortKey === 'value'" class="pi-sort-ind">{{ sortDir === 'asc' ? '▲' : '▼' }}</span></th>
                <th class="pi-th-sort" @click="toggleSort('expected_close_date')">Close Date <span v-if="sortKey === 'expected_close_date'" class="pi-sort-ind">{{ sortDir === 'asc' ? '▲' : '▼' }}</span></th>
                <th class="pi-th-sort" @click="toggleSort('recent_activity')">Activity (30d) <span v-if="sortKey === 'recent_activity'" class="pi-sort-ind">{{ sortDir === 'asc' ? '▲' : '▼' }}</span></th>
                <th class="pi-th-sort" @click="toggleSort('days_to_close')">Days to Close <span v-if="sortKey === 'days_to_close'" class="pi-sort-ind">{{ sortDir === 'asc' ? '▲' : '▼' }}</span></th>
                <th>Health</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in sortedDeals" :key="d.id">
                <td class="pi-td-bold">{{ d.title }}</td>
                <td>{{ d.contact_name }}</td>
                <td>{{ formatCurrency(d.value) }}</td>
                <td>{{ formatDate(d.expected_close_date) }}</td>
                <td>
                  <span :class="activityClass(d.recent_activity)">
                    {{ d.recent_activity > 0 ? d.recent_activity + ' done' : '—' }}
                  </span>
                </td>
                <td>
                  <span :class="daysToCloseClass(d.days_to_close)">{{ formatDaysToClose(d.days_to_close) }}</span>
                </td>
                <td><span class="pi-deal-pill" :class="dealHealthClass(d)">{{ dealHealthLabel(d) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import {
  Chart,
  BarController, BarElement,
  CategoryScale, LinearScale,
  Tooltip, Legend,
} from 'chart.js';
import api from '../api.js';
import { getStoredUser } from '../utils/storage.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

// ── Auth ──────────────────────────────────────────────────────────────────────
const currentUser = getStoredUser();
const isAdmin = computed(() => {
  const roles = currentUser?.roles ?? [];
  return roles.includes('admin') || roles.includes('super-admin');
});

// ── Date picker ───────────────────────────────────────────────────────────────
const todayStr = new Date().toISOString().slice(0, 10);

const PRESETS = [
  { label: 'Last 30 Days', days: 30  },
  { label: 'Last 60 Days', days: 60  },
  { label: 'Last 90 Days', days: 90  },
  { label: 'This Year',    days: 365 },
];

function dateStr(daysAgo) {
  const d = new Date();
  d.setDate(d.getDate() - daysAgo);
  return d.toISOString().slice(0, 10);
}

const pickerOpen = ref(false);
const pickerRef  = ref(null);
const customFrom = ref(dateStr(30));
const customTo   = ref(todayStr);

const filters = ref({
  label:   'Last 30 Days',
  from:    dateStr(30),
  to:      todayStr,
  user_id: '',
});

function applyPreset(p) {
  filters.value.from  = dateStr(p.days);
  filters.value.to    = todayStr;
  filters.value.label = p.label;
  pickerOpen.value    = false;
  loadAll();
}

function applyCustom() {
  if (!customFrom.value || !customTo.value) return;
  filters.value.from  = customFrom.value;
  filters.value.to    = customTo.value;
  filters.value.label = `${customFrom.value} → ${customTo.value}`;
  pickerOpen.value    = false;
  loadAll();
}

function closePicker(e) {
  if (pickerRef.value && !pickerRef.value.contains(e.target)) pickerOpen.value = false;
}

function handleKey(e) {
  if (e.key === 'Escape') pickerOpen.value = false;
}

// ── Users (admin agent filter) ────────────────────────────────────────────────
const users = ref([]);

async function loadLookups() {
  try {
    const { data } = await api.get('/v1/lookups');
    users.value = data.users ?? [];
  } catch (_) { /* noop */ }
}

// ── Data refs ─────────────────────────────────────────────────────────────────
const summary   = ref({ neglected: null, pipeline_value: null, overloaded_agents: null, unworked_opps: null });
const forecast  = ref([]);
const neglected = ref([]);
const agentLoad = ref([]);
const unworked  = ref([]);
const deals     = ref([]);
const winRates  = ref({ by_agent: [], by_industry: [] });
const velocity  = ref({ benchmark_days: null, sample_size: 0, stalling_count: 0, deals: [] });
const coverage  = ref([]);

const loading = ref({
  summary: false, forecast: false, neglected: false, agentLoad: false,
  unworked: false, deals: false, winRates: false, velocity: false, coverage: false,
});

const errors = ref({
  summary: false, forecast: false, neglected: false, agentLoad: false,
  unworked: false, deals: false, winRates: false, velocity: false, coverage: false,
});

// ── Sort (deals table) ────────────────────────────────────────────────────────
const sortKey = ref('expected_close_date');
const sortDir = ref('asc');

const sortedDeals = computed(() => {
  if (!deals.value.length) return [];
  return [...deals.value].sort((a, b) => {
    const av = a[sortKey.value] ?? '';
    const bv = b[sortKey.value] ?? '';
    const cmp = typeof av === 'number' ? av - bv : String(av).localeCompare(String(bv));
    return sortDir.value === 'asc' ? cmp : -cmp;
  });
});

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = key === 'expected_close_date' ? 'asc' : 'desc';
  }
}

// ── Chart canvas refs ─────────────────────────────────────────────────────────
const forecastCanvasRef = ref(null);
const forecastWrapRef   = ref(null);
let   forecastChartInst = null;
let   forecastResizeObs = null;

// ── Params builder ────────────────────────────────────────────────────────────
function buildParams() {
  const p = { from: filters.value.from, to: filters.value.to };
  if (filters.value.user_id) p.user_id = filters.value.user_id;
  return p;
}

// ── API calls ─────────────────────────────────────────────────────────────────
async function loadSummary() {
  loading.value.summary = true; errors.value.summary = false;
  try {
    const { data } = await api.get('/v1/predictive/summary', { params: buildParams() });
    summary.value = data;
  } catch (_) { errors.value.summary = true; }
  finally { loading.value.summary = false; }
}

async function loadForecast() {
  loading.value.forecast = true; errors.value.forecast = false;
  forecastChartInst?.destroy(); forecastChartInst = null;
  try {
    const { data } = await api.get('/v1/predictive/forecast', { params: buildParams() });
    forecast.value = data;
  } catch (_) { forecast.value = []; errors.value.forecast = true; }
  finally { loading.value.forecast = false; }
  await nextTick();
  buildForecastChart();
}

async function loadNeglected() {
  loading.value.neglected = true; errors.value.neglected = false;
  try {
    const { data } = await api.get('/v1/predictive/at-risk', { params: buildParams() });
    neglected.value = data;
  } catch (_) { neglected.value = []; errors.value.neglected = true; }
  finally { loading.value.neglected = false; }
}

async function loadAgentLoad() {
  loading.value.agentLoad = true; errors.value.agentLoad = false;
  try {
    const { data } = await api.get('/v1/predictive/pace', { params: buildParams() });
    agentLoad.value = data;
  } catch (_) { agentLoad.value = []; errors.value.agentLoad = true; }
  finally { loading.value.agentLoad = false; }
}

async function loadUnworked() {
  loading.value.unworked = true; errors.value.unworked = false;
  try {
    const { data } = await api.get('/v1/predictive/overdue-risk', { params: buildParams() });
    unworked.value = data;
  } catch (_) { unworked.value = []; errors.value.unworked = true; }
  finally { loading.value.unworked = false; }
}

async function loadDeals() {
  loading.value.deals = true; errors.value.deals = false;
  try {
    const { data } = await api.get('/v1/predictive/deals', { params: buildParams() });
    deals.value = data;
  } catch (_) { deals.value = []; errors.value.deals = true; }
  finally { loading.value.deals = false; }
}

async function loadWinRates() {
  loading.value.winRates = true; errors.value.winRates = false;
  try {
    const { data } = await api.get('/v1/predictive/win-rates', { params: buildParams() });
    winRates.value = data;
  } catch (_) { winRates.value = { by_agent: [], by_industry: [] }; errors.value.winRates = true; }
  finally { loading.value.winRates = false; }
}

async function loadVelocity() {
  loading.value.velocity = true; errors.value.velocity = false;
  try {
    const { data } = await api.get('/v1/predictive/deal-velocity', { params: buildParams() });
    velocity.value = data;
  } catch (_) { velocity.value = { benchmark_days: null, sample_size: 0, stalling_count: 0, deals: [] }; errors.value.velocity = true; }
  finally { loading.value.velocity = false; }
}

async function loadCoverage() {
  loading.value.coverage = true; errors.value.coverage = false;
  try {
    const { data } = await api.get('/v1/predictive/pipeline-coverage', { params: buildParams() });
    coverage.value = data;
  } catch (_) { coverage.value = []; errors.value.coverage = true; }
  finally { loading.value.coverage = false; }
}

function loadAll() {
  loadSummary(); loadForecast(); loadNeglected(); loadAgentLoad();
  loadUnworked(); loadDeals(); loadWinRates(); loadVelocity(); loadCoverage();
}

// ── Display helpers ───────────────────────────────────────────────────────────
function formatCurrency(val) {
  if (val == null) return '—';
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(val);
}

function formatDate(str) {
  if (!str) return '—';
  return new Date(str + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDaysToClose(days) {
  if (days === null || days === undefined) return '—';
  if (days < 0)  return `${Math.abs(days)}d overdue`;
  if (days === 0) return 'Today';
  return `${days}d`;
}

function neglectedBadgeClass(days) {
  if (days >= 180) return 'pi-risk-badge--danger';
  if (days >= 90)  return 'pi-risk-badge--warning';
  return 'pi-risk-badge--info';
}

function loadBarClass(engagedPct) {
  if (engagedPct >= 20) return 'pi-load-bar--primary';
  if (engagedPct >= 10) return 'pi-load-bar--warning';
  return 'pi-load-bar--muted';
}

// Deal health — based on observable facts, not made-up adjustments
function dealHealthClass(d) {
  if (d.days_to_close !== null && d.days_to_close < 0) return 'pi-deal-pill--high-risk';
  if (d.recent_activity === 0 && d.days_to_close !== null && d.days_to_close <= 30) return 'pi-deal-pill--at-risk';
  if (d.days_to_close !== null && d.days_to_close <= 14 && d.recent_activity < 2) return 'pi-deal-pill--at-risk';
  return 'pi-deal-pill--on-track';
}

function dealHealthLabel(d) {
  if (d.days_to_close !== null && d.days_to_close < 0) return 'Overdue';
  if (d.recent_activity === 0 && d.days_to_close !== null && d.days_to_close <= 30) return 'At Risk';
  if (d.days_to_close !== null && d.days_to_close <= 14 && d.recent_activity < 2) return 'At Risk';
  return 'On Track';
}

function daysToCloseClass(days) {
  if (days === null || days === undefined) return '';
  if (days < 0)  return 'pi-days--overdue';
  if (days <= 14) return 'pi-days--soon';
  return '';
}

function activityClass(count) {
  if (count >= 3) return 'pi-activity--high';
  if (count >= 1) return 'pi-activity--mid';
  return 'pi-activity--none';
}

function winRatePillClass(rate) {
  if (rate >= 60) return 'pi-deal-pill--on-track';
  if (rate >= 35) return 'pi-deal-pill--at-risk';
  return 'pi-deal-pill--high-risk';
}

function coveragePillClass(pct) {
  if (pct === null) return 'pi-deal-pill--at-risk';
  if (pct >= 80)   return 'pi-deal-pill--on-track';
  if (pct >= 50)   return 'pi-deal-pill--at-risk';
  return 'pi-deal-pill--high-risk';
}

function coverageBarClass(pct) {
  if (pct >= 80) return 'pi-load-bar--primary';
  if (pct >= 50) return 'pi-load-bar--warning';
  return 'pi-cov-bar--danger';
}

// ── CSV export ────────────────────────────────────────────────────────────────
function downloadCsv(rows, headers, filename) {
  const esc = v => {
    const s = String(v ?? '');
    return /[,"\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const csv = [headers, ...rows].map(r => r.map(esc).join(',')).join('\r\n');
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}

function exportDeals() {
  downloadCsv(
    deals.value.map(d => [d.title, d.contact_name, d.value, d.expected_close_date, d.recent_activity, d.days_to_close, d.probability, dealHealthLabel(d)]),
    ['Deal', 'Contact', 'Value', 'Close Date', 'Activity (30d)', 'Days to Close', 'Agent Probability (%)', 'Health'],
    'open-deals.csv',
  );
}

function exportNeglected() {
  downloadCsv(
    neglected.value.map(c => [c.name, c.owner_name, c.status_name, c.days_since_update]),
    ['Contact', 'Owner', 'Status', 'Days Since Last Interaction'],
    'neglected-contacts.csv',
  );
}

// ── Chart ─────────────────────────────────────────────────────────────────────
function tooltipDefaults() {
  return {
    backgroundColor: '#0f2456',
    padding: 10,
    titleFont:   { size: 11, weight: '600' },
    bodyFont:    { size: 12 },
    displayColors: true,
    cornerRadius: 8,
  };
}

function buildForecastChart() {
  if (!forecastCanvasRef.value || !forecast.value.length) return;
  const rows = forecast.value;
  forecastChartInst = new Chart(forecastCanvasRef.value.getContext('2d'), {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [
        {
          label: 'Full Pipeline',
          data: rows.map(r => r.total_value),
          backgroundColor: 'rgba(148,163,184,0.2)',
          borderColor: 'rgba(148,163,184,0.5)',
          borderWidth: 1, borderRadius: 4, borderSkipped: false, order: 2,
        },
        {
          label: 'Expected Value',
          data: rows.map(r => r.expected_value),
          backgroundColor: 'rgba(29,78,216,0.55)',
          borderColor: '#1d4ed8',
          borderWidth: 1, borderRadius: 4, borderSkipped: false, order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: { font: { size: 11 }, color: '#64748b', boxWidth: 12, padding: 12 },
        },
        tooltip: {
          ...tooltipDefaults(),
          callbacks: {
            label: (item) => ` ${item.dataset.label}: ${formatCurrency(item.raw)}`,
            afterBody: (items) => {
              const row = rows[items[0]?.dataIndex];
              return row ? [`Deals: ${row.deal_count}`] : [];
            },
          },
        },
      },
      scales: {
        x: {
          border: { display: false },
          grid:   { display: false },
          ticks:  { font: { size: 11 }, color: '#94a3b8' },
        },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: 'rgba(148,163,184,0.12)', drawTicks: false },
          ticks: {
            font: { size: 10 }, color: '#94a3b8', padding: 8,
            callback: v => v >= 1000 ? `$${(v / 1000).toFixed(0)}k` : `$${v}`,
          },
        },
      },
    },
  });
  forecastResizeObs?.disconnect();
  forecastResizeObs = new ResizeObserver(() => forecastChartInst?.resize());
  if (forecastWrapRef.value) forecastResizeObs.observe(forecastWrapRef.value);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => {
  document.addEventListener('click', closePicker);
  document.addEventListener('keydown', handleKey);
  loadLookups();
  loadAll();
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closePicker);
  document.removeEventListener('keydown', handleKey);
  forecastResizeObs?.disconnect();
  forecastChartInst?.destroy();
});
</script>

<style scoped>
/* ── Root layout ──────────────────────────────────────────────────────────── */
.pi {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px 32px 48px;
  max-width: 1500px;
  margin: 0 auto;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.pi-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.pi-header h1 {
  font-size: 28px;
  font-weight: 800;
  color: var(--text-1);
  letter-spacing: -0.5px;
  margin: 0 0 4px;
}
.pi-subtitle {
  font-size: 13.5px;
  color: var(--text-3);
  margin: 0;
}
.pi-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* ── Date picker ──────────────────────────────────────────────────────────── */
.pi-date-wrap { position: relative; }
.pi-date-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 14px; background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); font-size: 13.5px; font-weight: 500; color: var(--text-1);
  cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s;
}
.pi-date-btn:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--focus-ring); }
.pi-date-icon { width: 15px; height: 15px; color: var(--text-2); flex-shrink: 0; }
.pi-caret { width: 8px; height: 5px; color: var(--text-3); flex-shrink: 0; }
.pi-date-panel {
  position: absolute; top: calc(100% + 6px); right: 0; z-index: 200;
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg); padding: 16px; min-width: 320px;
}
.pi-presets-section { margin-bottom: 14px; }
.pi-custom-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--text-3); margin-bottom: 8px;
}
.pi-presets { display: flex; flex-wrap: wrap; gap: 6px; }
.pi-preset {
  padding: 5px 12px; background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 20px; font-size: 12.5px; color: var(--text-2); cursor: pointer; transition: all 0.15s;
}
.pi-preset:hover { background: var(--primary-soft); border-color: var(--primary); color: var(--primary-text); }
.pi-preset--on  { background: var(--primary-soft); border-color: var(--primary); color: var(--primary-text); font-weight: 600; }
.pi-custom-inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pi-custom-inline input {
  padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface); flex: 1; min-width: 120px;
}
.pi-custom-sep { font-size: 12px; color: var(--text-3); }
.pi-apply-btn {
  white-space: nowrap; padding: 6px 14px; font-size: 13px; border-radius: var(--radius-sm);
  border: none; background: var(--primary); color: var(--primary-on);
  font-weight: 600; cursor: pointer; transition: background 0.15s;
}
.pi-apply-btn:hover { background: var(--primary-hover); }
.panel-drop-enter-active, .panel-drop-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.panel-drop-enter-from, .panel-drop-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Filter bar ───────────────────────────────────────────────────────────── */
.pi-filter-bar {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 10px 16px;
  box-shadow: var(--shadow-xs);
}
.pi-filter-group { display: flex; align-items: center; gap: 8px; }
.pi-filter-label {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--text-3); white-space: nowrap;
}
.pi-filter-select {
  height: 34px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; color: var(--text-1); background: var(--surface); cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s; outline: none;
}
.pi-filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }

/* ── KPI row ──────────────────────────────────────────────────────────────── */
.pi-kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.pi-kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  box-shadow: var(--shadow-sm);
}
.pi-kpi-icon {
  width: 44px; height: 44px; border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.pi-kpi-icon svg           { width: 20px; height: 20px; }
.pi-kpi-icon--danger  { background: var(--danger-soft);  color: var(--danger); }
.pi-kpi-icon--success { background: var(--success-soft); color: var(--success); }
.pi-kpi-icon--warning { background: var(--warning-soft); color: var(--warning); }
.pi-kpi-icon--info    { background: var(--info-soft);    color: var(--info); }
.pi-kpi-value { font-size: 26px; font-weight: 800; color: var(--text-1); line-height: 1.1; }
.pi-kpi-label { font-size: 13px; font-weight: 600; color: var(--text-1); margin-top: 2px; }
.pi-kpi-sub   { font-size: 11.5px; color: var(--text-3); margin-top: 2px; }
.pi-kpi-skel  {
  height: 30px; width: 72px;
  background: var(--border-soft); border-radius: var(--radius-sm);
  animation: pi-pulse 1.4s ease-in-out infinite;
}

/* ── Layout grids ─────────────────────────────────────────────────────────── */
.pi-row-asym { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start; }
.pi-row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }

/* ── Card shell ───────────────────────────────────────────────────────────── */
.pi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
}
.pi-card-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; padding: 18px 20px 14px;
  border-bottom: 1px solid var(--border-soft); flex-wrap: wrap;
}
.pi-card-title-wrap { display: flex; align-items: center; gap: 10px; }
.pi-card-icon { width: 18px; height: 18px; color: var(--primary); flex-shrink: 0; }
.pi-card-icon--danger  { color: var(--danger); }
.pi-card-icon--warning { color: var(--warning); }
.pi-card-icon--info    { color: var(--info); }
.pi-card-icon--success { color: var(--success); }
.pi-card-title { font-size: 14px; font-weight: 700; color: var(--text-1); }
.pi-card-meta  { font-size: 12px; color: var(--text-3); }
.pi-card-body  { padding: 16px 20px; flex: 1; }
.pi-card-head-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* ── Empty state ──────────────────────────────────────────────────────────── */
.pi-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; padding: 32px 16px; color: var(--text-3); text-align: center;
}
.pi-empty svg { width: 36px; height: 36px; opacity: 0.4; }
.pi-empty p   { font-size: 13px; margin: 0; }

/* ── Skeleton loading ─────────────────────────────────────────────────────── */
.pi-skeleton-block {
  height: 200px; background: var(--border-soft); border-radius: var(--radius);
  animation: pi-pulse 1.4s ease-in-out infinite;
}
.pi-skeleton-list { display: flex; flex-direction: column; gap: 10px; }
.pi-skeleton-row {
  height: 36px; background: var(--border-soft); border-radius: var(--radius-sm);
  animation: pi-pulse 1.4s ease-in-out infinite;
}
@keyframes pi-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

/* ── Scope note ───────────────────────────────────────────────────────────── */
.pi-scope-note {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; background: var(--info-soft);
  border: 1px solid var(--info); border-radius: var(--radius);
  font-size: 12.5px; color: var(--text-2);
}
.pi-scope-note svg    { width: 16px; height: 16px; flex-shrink: 0; color: var(--info); }
.pi-scope-note strong { color: var(--text-1); font-weight: 700; }

/* ── Error states ─────────────────────────────────────────────────────────── */
.pi-error-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px; background: var(--danger-soft);
  border: 1px solid var(--danger); border-radius: var(--radius);
  font-size: 13px; color: var(--danger);
}
.pi-error-banner svg { width: 16px; height: 16px; flex-shrink: 0; }
.pi-error-banner span { flex: 1; }
.pi-error {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 32px 16px; color: var(--danger); text-align: center;
}
.pi-error svg { width: 36px; height: 36px; opacity: 0.6; }
.pi-error p   { font-size: 13px; margin: 0; }
.pi-retry-btn {
  padding: 5px 14px; border-radius: var(--radius-sm);
  border: 1px solid currentColor; background: transparent;
  font-size: 12.5px; font-weight: 600; cursor: pointer;
  color: inherit; transition: background 0.15s;
}
.pi-retry-btn:hover { background: rgba(0, 0, 0, 0.06); }

/* ── Export button ────────────────────────────────────────────────────────── */
.pi-export-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: var(--radius-sm);
  border: 1px solid var(--border); background: var(--surface-2);
  font-size: 12px; font-weight: 600; color: var(--text-2);
  cursor: pointer; transition: border-color 0.15s, color 0.15s;
  white-space: nowrap;
}
.pi-export-btn svg { width: 13px; height: 13px; flex-shrink: 0; }
.pi-export-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
.pi-export-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Neglected contacts list ──────────────────────────────────────────────── */
.pi-risk-list {
  list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column;
  max-height: 320px; overflow-y: auto;
}
.pi-risk-item {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  padding: 9px 0; border-bottom: 1px solid var(--border-soft);
}
.pi-risk-item:last-child { border-bottom: none; }
.pi-risk-left   { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.pi-risk-name   { font-size: 13.5px; font-weight: 500; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pi-risk-owner  { font-size: 11.5px; color: var(--text-3); display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.pi-status-chip {
  font-size: 10.5px; font-weight: 600; padding: 1px 7px;
  border-radius: 999px; background: var(--primary-soft); color: var(--primary-text);
}
.pi-risk-badge  { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
.pi-risk-badge--danger  { background: var(--danger-soft);  color: var(--danger); }
.pi-risk-badge--warning { background: var(--warning-soft); color: var(--warning); }
.pi-risk-badge--info    { background: var(--info-soft);    color: var(--info); }

/* ── Agent coverage load ──────────────────────────────────────────────────── */
.pi-load-list  { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 16px; }
.pi-load-item  { display: flex; flex-direction: column; gap: 6px; }
.pi-load-header { display: flex; align-items: center; justify-content: space-between; }
.pi-load-name  { font-size: 13px; font-weight: 600; color: var(--text-1); }
.pi-load-stat  { font-size: 12px; color: var(--text-2); }
.pi-load-engaged-text { color: var(--success); font-weight: 600; }
.pi-load-track { height: 8px; background: var(--border-soft); border-radius: 99px; overflow: hidden; }
.pi-load-bar   { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.pi-load-bar--primary { background: var(--primary); }
.pi-load-bar--warning { background: var(--warning); }
.pi-load-bar--muted   { background: var(--text-3); }

/* ── Unworked segments ────────────────────────────────────────────────────── */
.pi-unworked-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.pi-unworked-item { display: flex; flex-direction: column; gap: 6px; }
.pi-unworked-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pi-unworked-name  { font-size: 13px; font-weight: 600; color: var(--text-1); }
.pi-unworked-badge { font-size: 11.5px; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: var(--warning-soft); color: var(--warning); white-space: nowrap; }
.pi-unworked-bar-row { display: flex; align-items: center; gap: 8px; }
.pi-unworked-pct { font-size: 12px; font-weight: 700; color: var(--text-2); min-width: 36px; }
.pi-unworked-of  { font-size: 11.5px; color: var(--text-3); }

/* ── Win rates ────────────────────────────────────────────────────────────── */
.pi-wr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
.pi-wr-subtitle {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: var(--text-3); margin-bottom: 10px;
}

/* ── Deal velocity ────────────────────────────────────────────────────────── */
.pi-vel-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.pi-vel-item {
  display: flex; align-items: center; gap: 16px;
  padding: 10px 0; border-bottom: 1px solid var(--border-soft);
}
.pi-vel-item:last-child { border-bottom: none; }
.pi-vel-left  { flex: 1; min-width: 0; }
.pi-vel-title { display: block; font-size: 13.5px; font-weight: 600; color: var(--text-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pi-vel-contact { font-size: 11.5px; color: var(--text-3); }
.pi-vel-mid   { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; min-width: 90px; }
.pi-vel-days  { font-size: 12px; color: var(--text-2); }
.pi-vel-vs    { font-size: 11.5px; font-weight: 700; }
.pi-vel-vs--over  { color: var(--danger); }
.pi-vel-vs--under { color: var(--success); }
.pi-vel-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.pi-vel-value { font-size: 13px; font-weight: 600; color: var(--text-1); white-space: nowrap; }

/* ── Pipeline coverage ────────────────────────────────────────────────────── */
.pi-cov-list  { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 18px; }
.pi-cov-item  { display: flex; flex-direction: column; gap: 6px; }
.pi-cov-header { display: flex; align-items: center; justify-content: space-between; }
.pi-cov-name   { font-size: 13px; font-weight: 600; color: var(--text-1); }
.pi-cov-pct    { font-size: 11.5px; font-weight: 700; padding: 2px 9px; border-radius: 999px; }
.pi-cov-detail { font-size: 12px; color: var(--text-3); }
.pi-cov-gap    { color: var(--danger); font-weight: 600; }
.pi-cov-bar    { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.pi-cov-bar--danger { background: var(--danger); }

/* ── Tables ───────────────────────────────────────────────────────────────── */
.pi-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius); }
.pi-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.pi-table thead tr { background: var(--surface-2); }
.pi-table th {
  text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-2);
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
.pi-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-soft); color: var(--text-1); vertical-align: middle; }
.pi-table tr:last-child td { border-bottom: none; }
.pi-table tbody tr:hover td { background: var(--surface-2); }
.pi-td-bold { font-weight: 600; }
.pi-th-sort { cursor: pointer; user-select: none; transition: color 0.15s; }
.pi-th-sort:hover { color: var(--primary); }
.pi-sort-ind { font-size: 9px; margin-left: 4px; color: var(--primary); }

/* Mini bar */
.pi-mini-bar  { width: 80px; height: 6px; background: var(--border-soft); border-radius: 99px; overflow: hidden; }
.pi-mini-bar--wide { width: 100%; flex: 1; }
.pi-mini-fill { height: 100%; border-radius: 99px; background: var(--primary); }
.pi-prob--warning { background: var(--warning); }

/* Deal / win-rate pills */
.pi-deal-pill { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
.pi-deal-pill--on-track  { background: var(--success-soft); color: var(--success); }
.pi-deal-pill--at-risk   { background: var(--warning-soft); color: var(--warning); }
.pi-deal-pill--high-risk { background: var(--danger-soft);  color: var(--danger); }

/* Days to close cell */
.pi-days--overdue { color: var(--danger); font-weight: 600; }
.pi-days--soon    { color: var(--warning); font-weight: 600; }

/* Activity cell */
.pi-activity--high { color: var(--success); font-weight: 600; }
.pi-activity--mid  { color: var(--text-2); }
.pi-activity--none { color: var(--text-3); }

/* ── Chart canvases ───────────────────────────────────────────────────────── */
.pi-chart-wrap { height: 260px; position: relative; }
.pi-chart-wrap canvas { width: 100% !important; height: 100% !important; }
.pi-row-asym .pi-card:first-child .pi-card-body { min-height: 300px; }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .pi-kpi-row  { grid-template-columns: repeat(2, 1fr); }
  .pi-row-asym { grid-template-columns: 1fr; }
  .pi-wr-grid  { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
  .pi               { padding: 18px 14px; }
  .pi-kpi-row       { grid-template-columns: 1fr 1fr; }
  .pi-row-2col      { grid-template-columns: 1fr; }
  .pi-date-panel    { min-width: 0; right: -10px; }
  .pi-header-actions { width: 100%; }
  .pi-vel-item      { flex-wrap: wrap; }
}
@media (max-width: 480px) {
  .pi         { padding: 14px 10px; }
  .pi-kpi-row { grid-template-columns: 1fr; }
}
</style>
