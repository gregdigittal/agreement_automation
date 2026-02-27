> **Claude Code quick-run:** `@workspace Read ccrs-tdd-prompts/12-notifications.md and execute the instructions inside it`
> 
> Run after 11-merchant-agreements.

# CCRS TDD — 12: Notifications & Reminders

```
@workspace Create Pest PHP feature tests in tests/Feature/Notifications/NotificationTest.php for the multi-channel notification and reminder system.

Preferences:
1. User can configure preferences per category and channel
2. Categories: workflow_actions, contract_updates, signing_events, escalations, reminders
3. Channels: email, teams, in_app, calendar_ics
4. Toggling off prevents notifications on that channel for that category

In-App:
5. Creating notification makes it appear in user's inbox
6. Notifications have: type, title, message, channel, timestamp, read_status
7. Clicking marks as read
8. "Mark all as read" updates all unread
9. Badge count reflects unread count

Key Dates:
10. Created with: contract_id, date_type, date_value, description
11. Page shows consolidated list filtered by accessible contracts
12. Filtering by date_range, contract, date_type works

Reminders:
13. Links to KeyDate with: lead_days, channel, is_active
14. Dispatches when current_date within lead_days of key_date
15. Updates last_sent_at to prevent duplicate sends
16. Inactive reminders skipped

Escalation Notifications:
17. SLA breach sends escalation to configured channels
18. Tier 1, 2, 3 escalations notify progressively senior roles

Teams:
19. Posts formatted message via webhook with title, body, link

Calendar:
20. Generates .ics file with correct date and event details

Use Notification::fake(), Mail::fake(), Http::fake(). Create KeyDate, Reminder factories.
```


---
> **Next: @workspace Read ccrs-tdd-prompts/13-reports.md and execute the instructions inside it**
