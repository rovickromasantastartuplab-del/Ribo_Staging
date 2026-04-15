# Deep Walkthrough: Automated Follow-Up System Implementation

This document provides a comprehensive technical breakdown of the **GMass-Style Automated Follow-Up** system we built within the CRM's Conversation module.

---

## 🏗️ 1. Database & Persistence Layer

We created four new tables to support automation and behavioral tracking (Opens/Clicks).

| Table | Purpose |
| :--- | :--- |
| `thread_follow_up_stages` | Stores the **configuration** of a follow-up sequence (Stage #, Trigger type, Delay days, Subject/Body). |
| `thread_follow_up_queue` | Manages the **execution** of scheduled follow-ups. Keeps track of status (pending, sent, cancelled) and linked Gmail message IDs. |
| `email_open_logs` | Logs every time a recipient **opens** an email via the 1x1 tracking pixel. |
| `email_click_logs` | Logs every time a recipient **clicks** a link in a follow-up email. |

### Key Eloquent Models
- **[ThreadFollowUpStage.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Models/ThreadFollowUpStage.php)**: Represents a single "step" in a sequence.
- **[ThreadFollowUpQueue.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Models/ThreadFollowUpQueue.php)**: A scheduled task that points to a specific Stage and Thread.
- **[EmailThread.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Models/EmailThread.php)**: Updated to include the `followUpStages()` relationship.

---

## ✉️ 2. Gmail Service Extensions

To ensure follow-ups look like natural, human-written responses, we extended the core Gmail service.

#### [GmailService.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Services/GmailService.php)
- **`hasReply($threadId)`**: Checks if any external participant has replied to a specific Gmail thread. This is the primary trigger for cancelling "no_reply" automation.
- **`sendFollowUpReply()`**:
    - Constructs `In-Reply-To` and `References` headers using the `gmail_message_id_header`.
    - This ensures the follow-up appears in the recipient's inbox as part of the existing conversation thread, rather than a new email.

---

## 🎯 3. Behavioral Tracking System

We implemented a zero-dependency tracking system for Opens and Clicks.

#### [TrackingController.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Http/Controllers/TrackingController.php)
- Serves a 1x1 transparent GIF via the `pixel()` method.
- Logs the timestamp and IP address of the recipient.

#### [routes/web.php](file:///c:/Users/Rovick/Downloads/Final_Production/routes/web.php)
- Registered a **public** route `GET /t/{messageId}`.
- Excluded this route from CSRF and Session middleware to allow loading in external email clients.

---

## ⚙️ 4. Automation & Intelligence Engine

The heart of the system is the scheduler-driven command.

#### [ProcessFollowUps.php](file:///c:/Users/Rovick/Downloads/Final_Production/app/Console/Commands/ProcessFollowUps.php)
- **Execution Flow**:
    1. Fetches all `pending` queue items where `scheduled_at` <= `now()`.
    2. **Trigger Evaluation**: Checks if the recipient has already replied (Gmail API) or if there are local open/click logs.
    3. **Cancellation**: If a condition is met (e.g., they replied to a "no-reply" sequence), the automation is cancelled.
    4. **Merge Tag Resolution**: Dynamically replaces tags like `{FirstName}` and `{Company}` using the linked **Lead** or **Contact** record.
    5. **Next-Stage Chaining**: After sending Stage 1, it automatically calculates the delay for Stage 2 and creates the next `pending` item in the queue.

---

## 🖥️ 5. Frontend & UI components

The user interface was built to be intuitive and native to the Conversation module.

#### [FollowUpSequenceBuilder.tsx](file:///c:/Users/Rovick/Downloads/Final_Production/resources/js/pages/conversations/components/follow-up-sequence-builder.tsx)
- A standalone React component integrated into the sidebar of [index.tsx](file:///c:/Users/Rovick/Downloads/Final_Production/resources/js/pages/conversations/index.tsx).
- **Features**:
    - Add/Remove stages dynamically.
    - Change trigger types (No Reply, No Open, No Click, Drip).
    - Configure custom delays.
    - **One-Click Templates**: Load pre-defined "Gentle Nudge", "Value Add", or "Break-up" content.
    - **Merge Tag Chips**: Quickly insert placeholders into the email body.
    - **Live Queue Status**: Displays "Sent", "Pending", or "Cancelled" badges for each stage.

---

## 📑 6. Content & Templates

We created three default Blade templates to provide a "professional starting point."

- **[nudge.blade.php](file:///c:/Users/Rovick/Downloads/Final_Production/resources/views/emails/followups/nudge.blade.php)** (Gentle Stage 1)
- **[value_add.blade.php](file:///c:/Users/Rovick/Downloads/Final_Production/resources/views/emails/followups/value_add.blade.php)** (Helpful Stage 2)
- **[breakup.blade.php](file:///c:/Users/Rovick/Downloads/Final_Production/resources/views/emails/followups/breakup.blade.php)** (Final Stage 3)

---

## ⚠️ Critical Operational Requirements

> [!IMPORTANT]
> **Scheduler Status**
> The system **rely entirely** on the Laravel Scheduler. You must run:
> `php artisan schedule:run`
> to trigger the `ProcessFollowUps.php` command.

> [!WARNING]
> **Tracking Pixels**
> For email open tracking to work, your CRM must be accessible via a **public URL**. Pixels will not report back to `localhost` when viewed in external mail clients.

---

### Verification Summary
- ✅ **Backend Integration**: Routes, Controllers, and Jobs are active.
- ✅ **Gmail Logic**: Threading and reply-detection verified.
- ✅ **Frontend Component**: UI is responsive and linked to the API.
- ✅ **Content**: Default templates are live and selectable.
