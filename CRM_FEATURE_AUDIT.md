# CRM Feature Audit — Progress Tracker

> Based on Zoho CRM feature reference checklist.
> Last updated: 2026-05-21 (Customizable drag-and-drop widget dashboard)
> Track status over time: ✅ IMPLEMENTED | ⚠️ PARTIAL | ❌ MISSING

---

## Scorecard

| Status | Count | % |
|--------|-------|---|
| ✅ Implemented | 19 | 45% |
| ⚠️ Partial | 2 | 5% |
| ❌ Missing | 21 | 50% |

---

## 1. Contact & Lead Management

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 1.1 | Lead capture | ✅ IMPLEMENTED | lead_source field on contacts (manual/web_form/whatsapp/phone_call/referral/social_media/email_campaign/walk_in/other); ContactAdd.vue dropdown (default: manual); WhatsApp webhook auto-sets whatsapp; public web form at /lead → POST /api/public/lead (throttled, no auth); source badge in ContactView.vue | 2026-05-20 |
| 1.2 | 360° contact view | ✅ IMPLEMENTED | Contact.php has full relationships (todos, projects, deals, incharges); ContactView.vue displays full history | 2026-05-18 |
| 1.3 | Lead scoring | ❌ MISSING | No scoring model, logic, or UI | 2026-05-18 |
| 1.4 | Account hierarchy | ❌ MISSING | No parent-child company relationships in Contact model | 2026-05-18 |
| 1.5 | Data enrichment & deduplication | ✅ IMPLEMENTED | DataHealth.vue now has "Review & Merge" button per duplicate group; merge endpoint (POST /v1/contacts/merge) moves todos/deals/projects/PICs to kept record | 2026-05-18 |
| 1.6 | Segmentation & filtering | ✅ IMPLEMENTED | Filters by status, industry, category, type, area, user — full UI support | 2026-05-18 |

---

## 2. Pipeline & Deal Management

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 2.1 | Visual pipeline (Kanban) | ✅ IMPLEMENTED | DealList.vue has drag-and-drop Kanban with 6 stages, count + value aggregates | 2026-05-18 |
| 2.2 | Deal tracking | ✅ IMPLEMENTED | Deal model: title, stage, value, probability %, expected_close_date, status, lost_reason | 2026-05-18 |
| 2.3 | Sales forecasting | ✅ IMPLEMENTED | DealController.summary() now returns weighted_forecast (value × probability), won_count, by_stage breakdown; Pipeline Forecast section card added to Performance.vue Overview tab | 2026-05-18 |
| 2.4 | CPQ | ❌ MISSING | No quoting, pricing rules, or configurator | 2026-05-18 |
| 2.5 | Multi-currency | ❌ MISSING | Single decimal value field; no currency support | 2026-05-18 |
| 2.6 | Product catalog | ❌ MISSING | No product or catalog model | 2026-05-18 |

---

## 3. Automation & Workflow

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 3.1 | Workflow automation | ✅ IMPLEMENTED | DealObserver: stage change → auto-creates follow-up todo (due +3 days); WorkflowInactivityCheck command: daily at 08:00, creates re-engage todo for contacts with 30+ days no activity | 2026-05-18 |
| 3.2 | Blueprints | ❌ MISSING | No locked step-by-step process enforcement | 2026-05-18 |
| 3.3 | Cadences | ❌ MISSING | No multi-step follow-up sequences | 2026-05-18 |
| 3.4 | Macros | ❌ MISSING | No bulk or one-click action bundles | 2026-05-18 |
| 3.5 | Journey orchestration | ❌ MISSING | No journey mapping or multi-touch logic | 2026-05-18 |
| 3.6 | Auto lead assignment | ✅ IMPLEMENTED | RoundRobinAssigner service + round_robin_state table; ContactController.store() auto-assigns when user_id is blank; ContactAdd.vue shows "Auto-assign (round-robin)" hint | 2026-05-18 |

---

## 4. Omnichannel Communication

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 4.1 | Email integration | ✅ IMPLEMENTED | contact_emails table + ContactEmailController; log sent/received emails against a contact; history displayed in ContactView.vue with inline form | 2026-05-18 |
| 4.2 | Telephony & call logging | ✅ IMPLEMENTED | contact_calls table + ContactCallController; log inbound/outbound calls with duration + notes against a contact; Call Log card in ContactView.vue | 2026-05-18 |
| 4.3 | WhatsApp & social | ✅ IMPLEMENTED | WhatsApp Business Cloud API webhook: inbound message ingestion, contact match/create, message logging (whatsapp_messages), round-robin rep assignment, auto-deal creation (New Lead stage), one-time auto-reply, in-app rep notification (notifications table); delivery/read status updates patched on outbound messages | 2026-05-19 |
| 4.4 | Live chat | ❌ MISSING | No chat widget | 2026-05-18 |
| 4.5 | Task & activity management | ✅ IMPLEMENTED | ToDo + FollowUp models with full CRUD, completion tracking, linked to contacts | 2026-05-18 |
| 4.6 | Smart inbox | ❌ MISSING | Reminders bell only; no unified inbox | 2026-05-18 |

---

## 5. AI & Intelligence

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 5.1 | Deal outcome prediction | ❌ MISSING | No ML model or probability prediction logic | 2026-05-18 |
| 5.2 | Best time to contact | ❌ MISSING | No AI-driven contact timing recommendations | 2026-05-18 |
| 5.3 | Anomaly detection | ❌ MISSING | No behavioral anomaly or pipeline spike detection | 2026-05-18 |
| 5.4 | Email intelligence | ❌ MISSING | No email tracking, read receipts, or sentiment analysis | 2026-05-18 |
| 5.5 | Churn prediction | ❌ MISSING | No predictive churn models or risk scoring | 2026-05-18 |
| 5.6 | Conversational AI | ❌ MISSING | No chatbot, conversational interface, or AI assistant | 2026-05-18 |

---

## 6. Analytics & Reporting

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 6.1 | Custom dashboards | ✅ IMPLEMENTED | Fully interactive drag-and-drop widget dashboard at `/`; 4 built-in widgets (Monthly Pipeline chart, Recent Contacts, KPI Stats, Pending Tasks); add/remove widgets via picker modal; resizable grid via grid-layout-plus; layout persisted per-user to `dashboard_layout` JSON column via `GET/PUT /api/v1/user/dashboard-layout`; "Save Layout" button turns green on unsaved changes; page refresh restores exact layout | 2026-05-21 |
| 6.2 | Pre-built reports | ✅ IMPLEMENTED | Reports.vue page added at /reports with 6 named reports (by Status, Industry, Category, Type, Agent, Monthly Growth); each shows bar chart + sortable table | 2026-05-18 |
| 6.3 | Cohort analysis | ❌ MISSING | No cohort segmentation or retention analysis | 2026-05-18 |
| 6.4 | Territory & quota management | ✅ IMPLEMENTED | territories table + Territory model; territory_id on contacts; TerritoryController (admin CRUD); territory filter on contacts index; territory picker in ContactAdd.vue; territories in /lookups; Team tab shows per-user revenue quota vs attainment (from won_deal_value KPI target) with progress cards; territory breakdown panel shows new contacts per territory per period | 2026-05-21 |
| 6.5 | Campaign ROI tracking | ❌ MISSING | No campaign attribution or cost-per-lead tracking | 2026-05-18 |
| 6.6 | Scheduled report delivery | ❌ MISSING | No scheduled export or email delivery | 2026-05-18 |

---

## 7. Customization & Extensibility

| # | Feature | Status | Notes | Updated |
|---|---------|--------|-------|---------|
| 7.1 | Custom modules & fields | ❌ MISSING | No dynamic field schema or metadata tables | 2026-05-18 |
| 7.2 | No-code layout designer | ⚠️ PARTIAL | Dashboard layout designer implemented (drag-and-drop widget grid, per-user persistence); general-purpose form/page builder for CRM views/modules still missing | 2026-05-21 |
| 7.3 | Roles & permissions (RBAC) | ✅ IMPLEMENTED | Spatie laravel-permission; super-admin/admin/user roles; adminOnly route guards in Vue Router | 2026-05-18 |
| 7.4 | REST API & webhooks | ✅ IMPLEMENTED | Webhook model + WebhookController (CRUD + test); WebhookDispatcher service fires contact.created, deal.stage_changed, deal.won, deal.lost; Webhooks.vue admin page with add/edit/test/delete; zero cost (Laravel Http facade only) | 2026-05-18 |
| 7.5 | Team spaces | ❌ MISSING | Flat user-scoped model; no org/team isolation | 2026-05-18 |
| 7.6 | Guided workflow builder | ❌ MISSING | No visual workflow designer or process builder | 2026-05-18 |

---

## Build Priority Queue

Use this section to pick what to work on next. Update status as features are completed.

### Tier 1 — Foundational (do first)

| Priority | Feature | Status | Notes |
|----------|---------|--------|-------|
| ⭐ 1 | Email integration (4.1) | ✅ DONE | contact_emails table, ContactEmailController, email history section in ContactView.vue |
| ⭐ 2 | Workflow automation (3.1) | ✅ DONE | DealObserver (stage change → todo) + WorkflowInactivityCheck command (30-day inactivity → re-engage todo) |
| ⭐ 3 | Auto lead assignment (3.6) | ✅ DONE | RoundRobinAssigner service; auto-assigns on contact create when user_id blank; round_robin_state tracks rotation |
| ⭐ 4 | Webhooks (7.4) | ✅ DONE | WebhookDispatcher + 4 events + Webhooks.vue admin UI; uses Laravel Http facade only (no paid services) |
| ⭐ 5 | Sales forecasting (2.3) | ⚠️ PARTIAL | Already have value + probability; add weighted forecast and trend chart in Performance.vue |

### Quick Wins (nearly done, small effort)

| Feature | What's needed |
|---------|--------------|
| Sales forecasting (2.3) | Add `value × probability` weighted sum to DealController.summary(); add forecast chart in Performance.vue |
| Reports library (6.2) | AnalyticsController breakdowns already exist; expose as named reports with a report picker UI |
| Deduplication (1.5) | `checkDuplicate` exists; add "Review duplicates" admin action in contact list with merge UI |

---

## WhatsApp Integration — Setup & Risks

### Required env vars (add to `.env`)
```
WHATSAPP_VERIFY_TOKEN=   # any secret string you set in Meta App Dashboard
WHATSAPP_APP_SECRET=     # App Secret from Meta App Dashboard → Settings → Basic
WHATSAPP_ACCESS_TOKEN=   # Permanent system-user token from Meta Business Suite
WHATSAPP_PHONE_NUMBER_ID=# Phone Number ID from WhatsApp → Getting Started
```

### Migrations added
| Migration | Description |
|-----------|-------------|
| `2026_05_19_010000_add_whatsapp_phone_to_contacts_table` | Adds `whatsapp_phone` (varchar 50, nullable, unique) to contacts |
| `2026_05_19_020000_create_whatsapp_messages_table` | Message log (inbound + outbound, status tracking, deduplication by message ID) |
| `2026_05_19_030000_create_notifications_table` | Standard Laravel `notifications` table for database-channel rep alerts |

### Remaining risks / manual steps
| Risk | Mitigation |
|------|-----------|
| Access token expiry | Use a permanent system-user token from Meta Business Suite; short-lived tokens will break the auto-reply |
| Webhook URL must be HTTPS | Production must be behind SSL; use ngrok for local dev |
| Queue must be running | `php artisan queue:work` must be running; otherwise jobs pile up and are never processed |
| Contact name collision | WhatsApp contacts are created with the sender's display name; if the display name exactly matches an existing contact's name, creation will succeed (no unique constraint on name at DB level) but may look like a duplicate — review in DataHealth.vue |
| Auto-reply only fires once | Controlled by the `isNew` flag in `ProcessWhatsAppWebhook`; repeat messages from the same number are logged but do not trigger a second reply or deal |
| Media files not downloaded | `media_id` is stored but the actual file is not fetched; use the Graph API (`GET /{media-id}`) to retrieve it if needed |

### Follow-up tasks
- [ ] Expose `GET /api/v1/contacts/{contact}/whatsapp-messages` for display in ContactView.vue
- [ ] Add WhatsApp message history card to ContactView.vue (matches email/call log pattern)
- [ ] Surface WhatsApp lead notifications in the NotificationBell (read `GET /api/v1/me/notifications`)
- [ ] Add outbound reply endpoint so reps can reply from the CRM
- [ ] Extend WebhookDispatcher with a `whatsapp.lead.created` event

---

## Changelog

| Date | Feature | Change | Note |
|------|---------|--------|------|
| 2026-05-18 | All | Initial audit | Baseline assessment of all 42 features |
| 2026-05-18 | 2.3 | ⚠️ → ✅ | Added weighted_forecast + by_stage to DealController; Pipeline Forecast card in Performance.vue |
| 2026-05-18 | 1.5 | ⚠️ → ✅ | Added merge endpoint + Review & Merge modal UI in DataHealth.vue |
| 2026-05-18 | 6.2 | ⚠️ → ✅ | Created Reports.vue with 6 pre-built reports; linked in sidebar |
| 2026-05-18 | 4.1 | ❌ → ✅ | contact_emails table + model + ContactEmailController (index/store/destroy); Email History card in ContactView.vue with log form and direction badges |
| 2026-05-18 | 3.1 | ❌ → ✅ | DealObserver fires on stage change → creates follow-up todo due +3 days; WorkflowInactivityCheck artisan command runs daily at 08:00 → creates re-engage todo for 30-day inactive contacts |
| 2026-05-18 | 3.6 | ❌ → ✅ | RoundRobinAssigner service with DB lock; round_robin_state table tracks last assigned user; ContactController.store() auto-assigns when user_id blank; ContactAdd.vue updated with round-robin hint |
| 2026-05-18 | 7.4 | ⚠️ → ✅ | Webhook model + migration; WebhookController (index/store/update/destroy/test/events); WebhookDispatcher fires 4 events; DealObserver extended for deal.won/lost; Webhooks.vue admin page; sidebar link added |
| 2026-05-18 | 4.2 | ❌ → ✅ | contact_calls table + ContactCall model + ContactCallController (index/store/destroy); Call Log card in ContactView.vue with inbound/outbound badges, duration, notes |
| 2026-05-19 | 4.3 | ❌ → ✅ | WhatsApp Business Cloud API webhook: GET verify + POST receive (signature-verified); ProcessWhatsAppWebhook queued job; WhatsAppPayloadParser + WhatsAppSender services; whatsapp_messages table; whatsapp_phone field on contacts; round-robin rep assignment; auto-deal (New Lead); one-time auto-reply; database notification to assigned rep; status update patching; 10 feature tests passing |
| 2026-05-20 | 1.1 | ⚠️ → ✅ | lead_source field (migration + model + validation); ContactAdd.vue source dropdown (default: manual); WhatsApp job auto-sets whatsapp; public web form at /lead (LeadForm.vue) → POST /api/public/lead (throttled, no auth required); source badge in ContactView.vue with 9 color-coded variants |
| 2026-05-21 | 6.4 | ⚠️ → ✅ | territories table + Territory model; territory_id FK on contacts; TerritoryController (CRUD, admin write); territory filter on contacts index; territory picker in ContactAdd.vue; territories in /lookups; Team tab: per-user revenue quota vs attainment cards + Quota/Attainment columns; territory breakdown panel in Team tab |
| 2026-05-21 | 6.1 | ✅ enhanced | Replaced static Dashboard.vue with fully interactive drag-and-drop widget system; DashboardContainer.vue (grid-layout-plus, 12-col grid, row-height 80px); 4 widget components (RevenueChartWidget, RecentContactsWidget, KpiStatsWidget, TasksWidget) each self-contained; layout saved per-user via `dashboard_layout` JSON column; migration + UserDashboardController + 8 feature tests |
| 2026-05-21 | 7.2 | ❌ → ⚠️ | Dashboard layout designer now implemented (drag-and-drop, resizable, per-user persistent); general CRM form/page builder still absent |

---

## Testing Guide

> After each feature is implemented, a short observation checklist is added here. Run `composer run dev` first to start Laravel + Vite before testing any UI changes.

### 4.1 — Email Integration
1. Open any contact → scroll to the **Email History** card at the bottom.
2. Click **+ Log Email** → fill in direction, date, subject, and optional body → click **Save Email**.
3. The email appears immediately with a blue ↑ Sent or green ↓ Received badge.
4. Click **✕** on any entry → confirm → it disappears from the list.
5. API check: `GET /api/v1/contacts/{id}/emails` returns the list; `POST` with `{subject, direction, emailed_at}` creates one.

### 3.1 — Workflow Automation
1. **Deal stage trigger:** Open any deal → change its stage → save. Go to Todos (`/todos`) — a new pending task appears with remark `Auto: Deal "…" moved to [stage]`, due 3 days from today.
2. **Inactivity trigger:** Run `php artisan workflow:inactivity-check` in the terminal. Check Todos for entries with remark `Auto: No activity for 30+ days — re-engage`. Running it twice the same day will not create duplicates.
3. **Schedule check:** Run `php artisan schedule:list` — `workflow:inactivity-check` should be listed as daily at 08:00.

### 7.4 — Webhooks
1. Log in as **admin** → go to **Administration → Webhooks** in the sidebar.
2. Click **+ Add Webhook** → enter a name, paste a test URL (use [https://webhook.site](https://webhook.site) for free inspection), tick one or more events, click **Save Webhook**.
3. Click **Test** on the new webhook — you should see "Test payload sent successfully." A test POST lands at your URL within seconds.
4. Create a new contact with no assigned user → your `contact.created` subscriber receives a POST with the contact's name and assigned user.
5. Open a deal → change the **Stage** → save → your `deal.stage_changed` subscriber fires with `stage` and `previous_stage`.
6. Mark a deal as **Won** → `deal.won` fires. Mark as **Lost** → `deal.lost` fires.
7. To pause without deleting: click **Edit** → uncheck **Active** → save. The badge changes to "Paused" and no events fire.
8. If a secret is set, each POST includes an `X-CRM-Signature: sha256=…` header for HMAC verification.

### 4.2 — Call Logging
1. Open any contact → scroll to the **Call Log** card at the bottom.
2. Click **+ Log Call** → choose direction (Outbound / Inbound), set date/time, optionally enter duration in minutes and notes → click **Save Call**.
3. The call appears with a purple ↗ Outbound or amber ↙ Inbound badge, the date, duration, and logged-by user.
4. Click **✕** on any entry → confirm → it disappears from the list.
5. API check: `GET /api/v1/contacts/{id}/calls` returns the list; `POST` with `{direction, called_at}` (plus optional `duration`, `notes`) creates one; `DELETE /api/v1/contacts/{id}/calls/{callId}` removes it.

### 4.3 — WhatsApp Business Webhook

#### Local testing with ngrok

1. Start the app: `composer run dev` (Laravel + queue worker must both be running — `php artisan queue:work` in a separate terminal).
2. Expose the local server: `ngrok http 80` (or your Vite/artisan port). Copy the `https://` forwarding URL.
3. In the [Meta App Dashboard](https://developers.facebook.com/apps/), go to **WhatsApp → Configuration → Webhook**. Set:
   - **Callback URL:** `https://<ngrok-host>/webhooks/whatsapp`
   - **Verify Token:** matches your `WHATSAPP_VERIFY_TOKEN` in `.env`
4. Click **Verify and Save** — the GET handshake is confirmed.
5. Subscribe to the **messages** webhook field.

#### Sample Meta webhook payload (POST)

```json
{
  "object": "whatsapp_business_account",
  "entry": [{
    "id": "YOUR_WABA_ID",
    "changes": [{
      "value": {
        "messaging_product": "whatsapp",
        "metadata": { "phone_number_id": "YOUR_PHONE_NUMBER_ID" },
        "contacts": [{ "profile": { "name": "Alice" }, "wa_id": "601112345678" }],
        "messages": [{
          "from": "601112345678",
          "id": "wamid.UNIQUE_MESSAGE_ID",
          "timestamp": "1716000000",
          "type": "text",
          "text": { "body": "Hello, I need info on your products." }
        }]
      },
      "field": "messages"
    }]
  }]
}
```

#### Expected results after first message from a new number
- New Contact created (`name = Alice`, `whatsapp_phone = 601112345678`, `remark = WhatsApp inbound lead`)
- Assigned to next rep in the round-robin rotation
- `whatsapp_messages` row: direction `inbound`, `message_text = "Hello…"`
- Open Deal created: `WhatsApp enquiry - Alice`, stage `New Lead`
- Auto-reply sent and logged as direction `outbound`
- Assigned rep gets a `whatsapp_lead` notification in the `notifications` table

#### Checking notifications for a rep (DB)
```sql
SELECT data FROM notifications WHERE notifiable_type = 'App\\Models\\User' AND notifiable_id = <user_id>;
```

---

### 3.6 — Auto Lead Assignment
1. Go to **Add New Company** → leave **Assigned User** on "Auto-assign (round-robin)" → complete both steps.
2. Open the new contact — **Assigned To** shows a user (not blank).
3. Add a second contact the same way → it is assigned to the next user in the list.
4. Add a third → wraps back to the first user.
5. If you manually pick a user before saving, round-robin is skipped for that contact.
6. DB check: `SELECT * FROM round_robin_state;` — `last_user_id` updates with each auto-assignment.

---

### 6.1 — Customizable Widget Dashboard

**Prerequisites:** run `php artisan migrate` to add the `dashboard_layout` column.

1. Navigate to `/` (home). The static overview is gone — you now see **My Dashboard** with 4 default widgets pre-loaded in a grid.
2. Click **Edit Layout** — each widget gets a blue dashed outline and a red ✕ remove button. Drag any widget to a new position; drag its bottom-right corner to resize.
3. Click **Done** to exit edit mode.
4. Click **Add Widget** — a modal lists all 4 widget types. Click one to append it below the current grid.
5. Click **Save Layout** (turns green when there are unsaved changes) — the layout is persisted via `PUT /api/v1/user/dashboard-layout`.
6. Hard-refresh the page — the grid reloads exactly as you left it (position, size, widget types).
7. Log in as a second user — their dashboard is independent (null until they save their own layout).
8. Remove all widgets → empty-state message appears with an "Add Your First Widget" button.
9. API checks:
   - `GET /api/v1/user/dashboard-layout` → `{ layout: [...] }` (or `null` if unsaved)
   - `PUT /api/v1/user/dashboard-layout` with invalid payload → 422 Unprocessable Entity

---

> **How to use this file:**
> - When a feature is completed, update its Status cell and the Updated date.
> - Add a row to the Changelog with a short note on what was built.
> - Add a subsection under **Testing Guide** with steps to observe the changes.
> - Re-run the audit periodically to keep the scorecard accurate.
