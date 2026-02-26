# Getting Started

This chapter walks you through your first session with CCRS -- from signing in to finding your way around the interface.

---

## Logging In

CCRS uses **Azure Active Directory (Microsoft) Single Sign-On** for authentication. There are no separate CCRS usernames or passwords to manage.

1. Open your browser and navigate to the CCRS application URL provided by your organisation.
2. You will see the CCRS login page with a **"Sign in with Microsoft"** button.
3. Click the button. You will be redirected to the Microsoft login page.
4. Enter your **corporate Microsoft credentials** (the same email and password you use for Outlook, Teams, etc.) and complete any multi-factor authentication prompts.
5. Once authenticated, Microsoft redirects you back to CCRS and you are logged in.

### First-Time Users

When you sign in for the first time, CCRS automatically provisions your account based on your **Azure AD group membership**. Your Azure AD group determines which CCRS role you are assigned (for example, Legal, Commercial, Finance, Operations, or Audit). You do not need to register or request a separate account.

If you are unable to sign in or you see an "Access Denied" message, contact your **System Administrator** to verify that your Azure AD account is in the correct group.

---

## The Dashboard

After a successful login you land on the **Dashboard** -- your central overview of contract activity across the organisation. The Dashboard is composed of several widgets, each providing a focused summary.

### Contract Status

A breakdown of all contracts grouped by their current workflow state (Draft, Review, Approval, Active, Expired, Terminated, and so on). Use this widget to get a quick picture of where contracts sit in the lifecycle.

### Expiry Horizon

Highlights contracts that are approaching their expiration date within the next **30, 60, and 90 days**. This is your early-warning system for renewals and renegotiations.

### Pending Workflows

Lists action items that are **awaiting your approval or input**. If a workflow stage is assigned to you (or your role), it appears here. Click an item to go directly to the relevant contract.

### Active Escalations

Shows workflow stages that are **overdue** and have been escalated. These require immediate attention to prevent bottlenecks.

### AI Cost

A spending summary for AI-powered contract analysis. Displays token usage and associated costs so the organisation can monitor AI expenditure.

### Compliance Overview

Displays the regulatory compliance status across your contract portfolio. Flags contracts that have outstanding compliance issues or missing regulatory checks.

### Contract Pipeline Funnel

A visual funnel showing how contracts progress through lifecycle stages -- from initial draft through to fully executed. Useful for spotting stages where contracts tend to stall.

### Obligation Tracker

Lists upcoming contract obligations and deadlines (payment milestones, deliverables, reporting requirements, etc.) so nothing falls through the cracks.

### Risk Distribution

Groups contracts by **risk level** as determined by AI analysis (Low, Medium, High, Critical). Helps Legal and Compliance teams prioritise their review workload.

### Workflow Performance

Shows the **average time spent** in each workflow stage. Use this to identify process bottlenecks and measure improvements over time.

---

## Navigation

### Left Sidebar

The **left sidebar** is your primary navigation menu. It is organised by feature area -- Contracts, Counterparties, Workflows, Reports, Administration, and more.

Menu items are **role-based**: you will only see the sections and pages that your assigned role permits. For example, a user with the Finance role will see finance-related reports but may not see system administration pages.

### Global Search

Press **Cmd+K** (macOS) or **Ctrl+K** (Windows/Linux) at any time to open the **Global Search** overlay. From here you can quickly search across:

- Contracts (by title, reference number, or content)
- Counterparties (by name or registration number)
- Other records throughout the system

Start typing and results appear instantly. Click a result to navigate directly to that record.

### Breadcrumbs

Every page displays a **breadcrumb trail** at the top of the content area. Breadcrumbs show your current location in the application hierarchy and let you jump back to any parent page with a single click.

### Notifications Bell

In the **top-right corner** of every page you will find the notifications bell icon. A badge indicates the number of **unread notifications**. Click the bell to expand the notifications panel and review recent alerts such as workflow assignments, escalation notices, and contract status changes.

---

## Profile and Preferences

Click your **name or avatar** in the top-right corner of the page to access your profile menu.

### Notification Preferences

From the profile menu, navigate to **Notification Preferences** to control how and when CCRS contacts you. You can configure each notification type independently across the following channels:

- **Email** -- notifications sent to your corporate email address
- **Microsoft Teams** -- notifications posted via the Teams integration
- **In-App** -- notifications shown in the CCRS notifications panel (the bell icon)
- **Calendar ICS** -- calendar invitations sent for deadline-based notifications (e.g., contract expiry reminders, obligation due dates)

Adjust these settings to match your workflow. For example, you might choose to receive escalation alerts via both email and Teams, but limit routine status updates to in-app only.

---

## Getting Help

If you need assistance while using CCRS:

1. **Help and Guide page** -- accessible from the left sidebar (look for the question mark icon). This page contains contextual guidance and frequently asked questions.
2. **Support** -- for issues not covered by the in-app guide, or if you encounter a technical problem, contact **support@digittal.io**.
