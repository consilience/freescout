# FreeScout System Architecture

**Version:** 1.8.197
**Laravel:** 5.7.29 (upgrading to 12.x)
**PHP:** 7.1+ (targeting 8.3/8.4)
**Date:** 2025-11-22

---

## Table of Contents

1. [Executive Overview](#executive-overview)
2. [Application Bootstrap & Lifecycle](#application-bootstrap--lifecycle)
3. [Data Architecture](#data-architecture)
4. [Core Business Logic](#core-business-logic)
5. [Email System Architecture](#email-system-architecture)
6. [Module System](#module-system)
7. [Frontend Architecture](#frontend-architecture)
8. [Background Processing](#background-processing)
9. [Authentication & Authorization](#authentication--authorization)
10. [API & Integration Points](#api--integration-points)
11. [Configuration & Deployment](#configuration--deployment)
12. [Architecture Patterns](#architecture-patterns)
13. [Recommendations for Rebuild](#recommendations-for-rebuild)

---

## Executive Overview

### What is FreeScout?

FreeScout is a **self-hosted help desk and shared mailbox system** written in Laravel. It provides email-based customer support ticketing, conversation management, and team collaboration features.

### Core Value Proposition

- **Multi-mailbox Management:** Support multiple support email addresses
- **Email Integration:** Bi-directional IMAP/SMTP email handling
- **Conversation Threading:** Email chain management with proper threading
- **Team Collaboration:** Multi-user access with role-based permissions
- **Real-time Updates:** Browser-based real-time notifications
- **Module System:** Extensible plugin architecture

### Technology Stack Summary

```
Backend:         Laravel 5.7 (PHP 7.1+)
Database:        MySQL 5.7+ / PostgreSQL / SQLite
Email Incoming:  IMAP/POP3 (webklex/php-imap)
Email Outgoing:  SMTP (SwiftMailer)
Queues:          Database / Redis / Sync
Caching:         File / Redis / Database
Real-time:       Polycast (database-backed polling)
Modules:         nwidart/laravel-modules
Frontend:        Blade templates, JavaScript, Bootstrap
Background:      Laravel Scheduler + Queue Workers
```

### Key Metrics

| Metric | Value | Notes |
|--------|-------|-------|
| **Custom Code** | 32,875 lines | `/app` directory |
| **Controllers** | 11 | Largest: 3,600 lines |
| **Models** | 10 core | Eloquent ORM |
| **Migrations** | 73 files | Latest: 2023 |
| **Views** | 145 | Blade templates |
| **Languages** | 18 locales | i18n support |
| **Override Files** | 279 (3.9MB) | ⚠️ Major technical debt |
| **Tests** | 6 files | Minimal coverage |

---

## Application Bootstrap & Lifecycle

### Entry Points

#### Web Application (`/public/index.php`)

```
PHP Version Check (7.1+)
    ↓
Load Composer Autoloader
    ↓
Bootstrap Application (/bootstrap/app.php)
    ↓
Create HTTP Kernel Instance
    ↓
Process Request → Response
    ↓
Terminate Middleware
```

**Key File:** `/home/user/freescout/public/index.php`

#### CLI Application (`/artisan`)

```
Bootstrap Application (/bootstrap/app.php)
    ↓
Create Console Kernel Instance
    ↓
Process Symfony Console Command
    ↓
Exit with Status Code
```

**Key File:** `/home/user/freescout/artisan`

### Bootstrap Process (`/bootstrap/app.php`)

Creates singleton bindings for three critical kernel interfaces:

```php
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);
```

### Service Provider Registration Order

Service providers boot in the following order (defined in `config/app.php`):

1. **AppServiceProvider** - Core application logic
   - Sets MySQL string length (191 bytes)
   - Registers Model Observers (Mailbox, Email, User, Conversation, Customer, Thread, Attachment, Follower, DatabaseNotification)
   - Forces HTTPS if configured
   - Handles installation check
   - Module registration error handling

2. **AuthServiceProvider** - Authorization policies

3. **EventServiceProvider** - Event-listener bindings

4. **RouteServiceProvider** - Route registration and bindings

5. **BroadcastServiceProvider** - Broadcasting configuration

6. **PolycastServiceProvider** - Real-time updates (custom driver)

### Request Lifecycle

```
HTTP Request
    ↓
Global Middleware (maintenance mode, proxy trust, trim strings)
    ↓
Web Middleware Group (encryption, session, CSRF, auth, localization)
    ↓
Route Middleware (auth, roles, can)
    ↓
Controller Action
    ↓
Response
    ↓
Terminate Middleware (logging, cleanup)
```

---

## Data Architecture

### Core Domain Models

FreeScout's data model centers around **Conversations** (tickets) containing **Threads** (messages), organized by **Mailboxes**, involving **Users** and **Customers**.

```
Mailbox (Support Address)
    ↓ has many
Conversation (Ticket/Thread)
    ↓ has many
Thread (Individual Message/Note)
    ↓ has many
Attachment (File)
```

### Entity-Relationship Diagram (Simplified)

```
┌──────────────┐
│   Mailbox    │
│  (Support    │
│   Address)   │
└──────┬───────┘
       │ 1:N
       ↓
┌──────────────────┐        ┌──────────────┐
│   Conversation   │←──────→│   Customer   │
│    (Ticket)      │  N:1   │              │
└────────┬─────────┘        └──────┬───────┘
         │ 1:N                     │ 1:N
         ↓                         ↓
    ┌─────────┐              ┌─────────┐
    │ Thread  │              │  Email  │
    │(Message)│              │(Address)│
    └────┬────┘              └─────────┘
         │ 1:N
         ↓
    ┌────────────┐
    │ Attachment │
    └────────────┘

┌──────────────┐
│     User     │
│  (Agent)     │
└──────┬───────┘
       │ N:M
       ↓
┌──────────────┐
│ MailboxUser  │
│  (Junction)  │
└──────────────┘
```

### Database Schema

#### **Users Table**
Primary actor: support agents and administrators

```sql
id              INT PRIMARY KEY AUTO_INCREMENT
email           VARCHAR UNIQUE
password        VARCHAR (encrypted)
first_name      VARCHAR
last_name       VARCHAR
role            TINYINT (1=User, 2=Admin)
status          TINYINT (Active/Disabled/Deleted)
photo_url       VARCHAR
locale          VARCHAR(5)  -- en, es, fr, etc.
timezone        VARCHAR     -- UTC, America/New_York, etc.
time_format     TINYINT     -- 12h/24h
permissions     INT         -- Bitfield
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Roles:**
- `ROLE_USER (1)` - Standard support agent
- `ROLE_ADMIN (2)` - Full administrator access

**Permissions (Bitfield):**
- `PERM_DELETE_CONVERSATIONS (1)`
- `PERM_EDIT_CONVERSATIONS (2)`
- `PERM_EDIT_SAVED_REPLIES (3)`
- `PERM_EDIT_TAGS (4)`
- `PERM_EDIT_CUSTOM_FOLDERS (5)`
- `PERM_EDIT_USERS (10)`

#### **Mailboxes Table**
Support email addresses/departments

```sql
id                      INT PRIMARY KEY
name                    VARCHAR         -- "Support", "Sales"
email                   VARCHAR UNIQUE  -- support@company.com
aliases                 TEXT (JSON)     -- Alternative emails
from_name               TINYINT         -- Mailbox/User/Custom
from_name_custom        VARCHAR
signature               TEXT
auto_reply_enabled      BOOLEAN
auto_reply_subject      VARCHAR
auto_reply_message      TEXT
-- Incoming (IMAP/POP3)
in_server               VARCHAR
in_port                 INT
in_username             VARCHAR
in_password             VARCHAR (encrypted)
in_protocol             TINYINT (1=IMAP, 2=POP3)
-- Outgoing (SMTP)
out_server              VARCHAR
out_port                INT
out_username            VARCHAR
out_password            VARCHAR (encrypted)
out_encryption          TINYINT (None/SSL/TLS)
out_method              TINYINT (PHP/Sendmail/SMTP)
-- OAuth
oauth_provider          VARCHAR
oauth_token             TEXT (encrypted JSON)
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

#### **Conversations Table**
Tickets/email threads

```sql
id                      INT PRIMARY KEY
number                  INT UNIQUE      -- Sequential ticket number
type                    TINYINT (1=Email, 2=Phone, 3=Chat)
folder_id               INT
status                  TINYINT (1=Active, 2=Pending, 3=Closed, 4=Spam)
state                   TINYINT
subject                 VARCHAR(998)
preview                 VARCHAR(255)    -- Latest message preview
mailbox_id              INT FK
user_id                 INT FK (Assignee)
customer_id             INT FK
customer_email          VARCHAR         -- Reply-to address
cc                      TEXT (JSON array)
bcc                     TEXT (JSON array)
source_via              TINYINT         -- Origin (web/email/API)
source_type             TINYINT
created_by_user_id      INT FK
created_by_customer_id  INT FK
threads_count           INT             -- Cached count
has_attachments         BOOLEAN
read_by_user            BOOLEAN
closed_by_user_id       INT FK
closed_at               TIMESTAMP
user_updated_at         TIMESTAMP       -- Last agent update
last_reply_at           TIMESTAMP       -- Last message time
last_reply_from         TINYINT         -- Customer/User
imported                BOOLEAN
meta                    TEXT (JSON)
created_at              TIMESTAMP
updated_at              TIMESTAMP

INDEX(folder_id, status)
INDEX(mailbox_id, customer_id)
INDEX(user_id)
INDEX(number)
```

#### **Threads Table**
Individual messages and notes within conversations

```sql
id                  INT PRIMARY KEY
conversation_id     INT FK
type                TINYINT (1=Customer, 2=AgentMessage, 3=Note, 4=LineItem, 8=Chat)
status              TINYINT (Active/Pending/Closed/Spam/NoChange)
state               TINYINT (1=Draft, 2=Published, 3=Hidden, 4=Review)
user_id             INT FK (Author if agent)
customer_id         INT FK (Author if customer)
from                VARCHAR(191)
to                  TEXT (JSON)
cc                  TEXT (JSON)
bcc                 TEXT (JSON)
subject             VARCHAR(998)
body                LONGTEXT        -- HTML body
body_text           LONGTEXT        -- Plain text version
headers             TEXT (JSON)     -- Email headers
message_id          VARCHAR(998)    -- Email Message-ID header
in_reply_to         VARCHAR(998)    -- Email In-Reply-To header
meta                TEXT (JSON)
meta_subtype        TINYINT         -- Forward/Phone/etc
send_status         TINYINT         -- Sending status
send_status_data    TEXT (JSON)     -- Error details
deleted_by_user     BOOLEAN         -- Soft delete
created_at          TIMESTAMP
updated_at          TIMESTAMP

INDEX(conversation_id)
INDEX(message_id)
INDEX(type, state, status)
```

**Thread Types:**
- `TYPE_CUSTOMER (1)` - Incoming customer email
- `TYPE_MESSAGE (2)` - Outgoing agent reply
- `TYPE_NOTE (3)` - Internal note (not sent to customer)
- `TYPE_LINEITEM (4)` - Status change record
- `TYPE_CHAT (8)` - Chat message

#### **Customers Table**
Customer profiles

```sql
id              INT PRIMARY KEY
first_name      VARCHAR
last_name       VARCHAR
company         VARCHAR
job_title       VARCHAR
address         TEXT (JSON) -- City, state, zip, country
phones          TEXT (JSON) -- Array of {type, value}
websites        TEXT (JSON)
social_profiles TEXT (JSON)
background      TEXT
notes           LONGTEXT
gender          CHAR(1)
age             VARCHAR
photo_url       VARCHAR
photo_type      TINYINT (Gravatar/Twitter/Facebook)
created_at      TIMESTAMP
updated_at      TIMESTAMP

INDEX(first_name, last_name)
INDEX(company)
```

#### **Emails Table**
Customer email addresses (separate for multiple emails per customer)

```sql
id              INT PRIMARY KEY
customer_id     INT FK
email           VARCHAR(191) UNIQUE
created_at      TIMESTAMP
updated_at      TIMESTAMP

INDEX(customer_id)
UNIQUE(email)
```

#### **Attachments Table**
File attachments on threads

```sql
id              INT PRIMARY KEY
thread_id       INT FK
file_name       VARCHAR(255)
file_dir        VARCHAR(255)    -- Nested storage path
size            INT             -- Bytes
type            TINYINT         -- Attachment type
mime_type       VARCHAR(127)
embedded        BOOLEAN         -- Inline image
created_at      TIMESTAMP
updated_at      TIMESTAMP

INDEX(thread_id)
```

#### **Folders Table**
Conversation organization (inbox, assigned, closed, custom)

```sql
id              INT PRIMARY KEY
mailbox_id      INT FK
user_id         INT FK (if personal folder)
type            TINYINT (Unassigned/Mine/Starred/Drafts/Assigned/Closed/Spam/Deleted)
name            VARCHAR
meta            TEXT (JSON)
created_at      TIMESTAMP
updated_at      TIMESTAMP

INDEX(mailbox_id, type)
```

**System Folder Types:**
1. Unassigned (1)
2. Mine (20)
3. Starred (25)
4. Drafts (30)
5. Assigned (40)
6. Closed (60)
7. Spam (80)
8. Deleted (110)
9. Custom (100+)

#### **Supporting Tables**

**MailboxUser** - Junction table for user mailbox access
**ConversationFolder** - Junction table for conversations in folders
**Followers** - Users following specific conversations
**SendLog** - Email delivery tracking
**ActivityLog** - Audit trail (Spatie package)
**Options** - Key-value configuration storage
**Modules** - Installed module records
**Subscriptions** - Billing/subscription data (if applicable)
**PolycastEvents** - Real-time event queue

### Model Relationships (Eloquent)

```php
// User
User hasMany Conversation (as assignee)
User hasMany Thread (as author)
User belongsToMany Mailbox (via MailboxUser)
User hasMany Follower

// Mailbox
Mailbox hasMany Conversation
Mailbox belongsToMany User (via MailboxUser)
Mailbox hasMany Folder

// Conversation
Conversation belongsTo Mailbox
Conversation belongsTo User (assignee)
Conversation belongsTo Customer
Conversation hasMany Thread
Conversation belongsToMany Folder (via ConversationFolder)
Conversation hasMany Follower

// Thread
Thread belongsTo Conversation
Thread belongsTo User (author, nullable)
Thread belongsTo Customer (author, nullable)
Thread hasMany Attachment

// Customer
Customer hasMany Email
Customer hasMany Conversation
Customer hasMany Thread

// Attachment
Attachment belongsTo Thread

// Folder
Folder belongsTo Mailbox
Folder belongsTo User (for personal folders)
Folder belongsToMany Conversation (via ConversationFolder)
```

### Caching Strategy

**Rememberable Trait** - Applied to models for query caching:
- User
- Mailbox
- Conversation
- Customer
- Thread

Cache keys are generated automatically and invalidated on model updates.

---

## Core Business Logic

### HTTP Controllers

Located in `/home/user/freescout/app/Http/Controllers/`

#### **ConversationsController** (143KB, 3,600+ lines)
The largest and most complex controller, handling all conversation operations.

**Key Methods:**
- `view($id)` - Display single conversation (AJAX-driven)
- `create()` - New ticket creation form
- `ajax()` - Primary AJAX endpoint for all conversation operations
- `ajaxHtml()` - Render HTML fragments
- `upload()` - Handle attachment uploads
- `search()` - Full-text conversation search
- `chats()` - Chat conversation view
- `undoReply()` - Undo sent reply within timeout window
- `cloneConversation()` - Duplicate existing conversation

**AJAX Operations:**
- `saveReply` - Save and send reply
- `updateStatus` - Change conversation status
- `assign` - Assign to user
- `addFollower` / `removeFollower` - Manage followers
- `mark` - Star/unstar conversation
- `mergeConversations` - Merge duplicate tickets
- `move` - Move to different mailbox
- `deleteConversation` - Delete conversation
- `updateSubject` - Edit subject
- `updateCustomer` - Change customer

#### **MailboxesController** (40KB)
Mailbox management and configuration.

**Key Methods:**
- `mailboxes()` - List all mailboxes
- `create()`, `update()` - CRUD operations
- `connectionIncoming()`, `connectionOutgoing()` - Email setup
- `autoReply()`, `autoReplySave()` - Auto-reply configuration
- `permissions()`, `permissionsSave()` - Mailbox access control
- `oauth()`, `oauthDisconnect()` - OAuth integration
- `ajax()` - Test connections, fetch settings

#### **UsersController** (21KB)
User account management.

**Key Methods:**
- `users()` - List users
- `create()`, `profile()` - User creation and editing
- `password()` - Password change
- `permissions()` - User permission assignment
- `notifications()` - Notification preferences

#### **CustomersController** (19KB)
Customer profile management.

**Key Methods:**
- `conversations()` - Customer conversation history
- `update()`, `updateSave()` - Customer profile editing
- `merge()`, `mergeSave()` - Merge duplicate customers
- `ajaxSearch()` - Customer lookup

#### **SettingsController** (16KB)
Application-wide settings.

**Key Methods:**
- `view($section)` - Display settings page
- `save($section)` - Save settings
- Sections: general, alerts, logs, etc.

#### **SystemController** (16KB)
System administration tools.

**Key Methods:**
- `status()` - System health check
- `tools()`, `toolsExecute()` - Admin utilities
- `cron($hash)` - Cron job endpoint (public but hash-protected)
- `action()` - System actions

#### **Auth Controllers** (`Auth/` subdirectory)
Laravel default authentication scaffolding:
- `LoginController`
- `RegisterController`
- `ForgotPasswordController`
- `ResetPasswordController`

### Middleware Stack

Located in `/home/user/freescout/app/Http/Middleware/`

#### Global Middleware (All Requests)
1. `CheckForMaintenanceMode` - Maintenance mode check
2. `ValidatePostSize` - POST size validation
3. `TrimStrings` - Trim whitespace
4. `ConvertEmptyStringsToNull` - Null conversion
5. `TrustProxies` - Proxy header trust
6. `ResponseHeaders` - Security headers
7. `TerminateHandler` - Cleanup on termination

#### Web Middleware Group
1. `EncryptCookies` - Cookie encryption
2. `AddQueuedCookiesToResponse` - Queue cookies
3. `StartSession` - Session initialization
4. `TokenAuth` - Custom token authentication (alternative to session)
5. `ShareErrorsFromSession` - Error flash data
6. `VerifyCsrfToken` - CSRF protection
7. `SubstituteBindings` - Route model binding
8. `HttpsRedirect` - Force HTTPS if configured
9. `Localize` - Set user locale (i18n)
10. `LogoutIfDeleted` - Auto-logout deleted users
11. `FrameGuard` - X-Frame-Options header
12. `CustomHandle` - Custom request handling

#### Route Middleware
- `auth` - Require authentication
- `guest` - Redirect if authenticated
- `roles` - Role-based access (admin check)
- `can` - Laravel ability-based authorization

### Event System

Located in `/home/user/freescout/app/Events/`

**Conversation Events:**
- `ConversationStatusChanged` - Status updated
- `ConversationUserChanged` - Assignment changed
- `ConversationCustomerChanged` - Customer changed
- `UserCreatedConversation` - New conversation by agent
- `CustomerCreatedConversation` - New conversation from customer

**Thread Events:**
- `UserReplied` - Agent sent message
- `CustomerReplied` - Customer email received
- `UserAddedNote` - Internal note added
- `UserCreatedConversationDraft` - Draft saved
- `UserCreatedThreadDraft` - Thread draft saved

**Real-time Broadcasting Events:**
- `RealtimeConvNewThread` - New message notification
- `RealtimeConvView` - User viewing conversation
- `RealtimeConvViewFinish` - User stopped viewing
- `RealtimeMailboxNewThread` - New mailbox message
- `RealtimeChat` - Chat message
- `RealtimeBroadcastNotificationCreated` - User notification

**User Events:**
- `UserDeleted` - User account deleted

### Listeners

Located in `/home/user/freescout/app/Listeners/`

**Event → Listener Mappings:**

```php
ConversationStatusChanged → UpdateMailboxCounters
UserReplied → SendReplyToCustomer, SendNotificationToUsers, RefreshConversations
CustomerReplied → SendNotificationToUsers
UserCreatedConversation → SendReplyToCustomer, SendNotificationToUsers
CustomerCreatedConversation → SendAutoReply, SendNotificationToUsers
```

**Authentication Listeners:**
- `LogRegisteredUser` - Track registration
- `LogSuccessfulLogin` - Track login
- `RememberUserLocale` - Restore user locale
- `ActivateUser` - Activate on login
- `LogPasswordReset` - Track password reset
- `SendPasswordChanged` - Email notification

### Observers

Located in `/home/user/freescout/app/Observers/`

Model lifecycle hooks (registered in `AppServiceProvider`):

- `MailboxObserver` - Mailbox changes
- `EmailObserver` - Email address changes
- `UserObserver` - User changes
- `ConversationObserver` - Conversation changes
- `CustomerObserver` - Customer changes
- `ThreadObserver` - Thread changes
- `AttachmentObserver` - Attachment changes
- `FollowerObserver` - Follower changes
- `DatabaseNotificationObserver` - Notification changes

**Example Observer Actions:**
- Auto-increment conversation number
- Update cached counters
- Clear model cache
- Log activity
- Cascade deletes

### Console Commands

Located in `/home/user/freescout/app/Console/Commands/`

**Email Processing:**
- `FetchEmails` - IMAP/POP3 email fetching
- `SendReplyToCustomer` - Send outgoing emails
- `SendAutoReply` - Send auto-responses
- `SendAlert` - Send alerts
- `SendNotificationToUsers` - Send user notifications

**Maintenance:**
- `UpdateFolderCounters` - Recalculate folder counts
- `CleanSendLog` - Clean old send logs
- `CleanNotificationsTable` - Clean notifications
- `CleanTmp` - Clean temporary files
- `ClearCache` - Clear application cache
- `GenerateVars` - Generate JS variables

**Module Management:**
- `ModuleInstall` - Install module
- `ModuleUpdate` - Update module
- `ModuleCheckLicenses` - Verify module licenses

**Monitoring:**
- `FetchMonitor` - Monitor email fetching
- `SendMonitor` - Monitor email sending
- `CheckConvViewers` - Check conversation viewers
- `LogsMonitor` - Monitor application logs

**Utilities:**
- `CreateUser` - Interactive user creation
- `CheckRequirements` - System requirements check
- `Build` - Frontend asset compilation

### Scheduled Tasks

Defined in `/home/user/freescout/app/Console/Kernel.php` `schedule()` method:

```php
// Queue Management
queue:flush          - Weekly (Sunday 00:00)
queue:restart        - Hourly

// Email Processing
freescout:fetch-emails       - Configurable (1-60 min)
freescout:send-monitor       - Every 10 minutes

// Database Maintenance
freescout:clean-send-log            - Monthly
freescout:clean-notifications-table - Weekly
freescout:update-folder-counters    - Hourly

// File Maintenance
freescout:clean-tmp  - Daily (02:00)

// Monitoring
freescout:check-conv-viewers  - Every minute
freescout:logs-monitor        - Configurable

// Module System
freescout:module-check-licenses  - Based on app key hash

// Queue Worker
queue:work  - Every minute (mutex-protected against overlap)
```

---

## Email System Architecture

### Email Fetching (Incoming)

**Flow Diagram:**

```
Cron Trigger (every N minutes)
    ↓
freescout:fetch-emails Command
    ↓
For Each Active Mailbox:
    ↓
    Connect via IMAP/POP3 (webklex/php-imap)
    ↓
    Query Unseen/All Emails (configurable)
    ↓
    Fetch in Batches (300 emails per page)
    ↓
    For Each Email:
        ↓
        Parse MIME Headers & Body
        ↓
        Extract: From, To, Cc, Bcc, Subject, Message-ID, In-Reply-To
        ↓
        Decode Character Encodings
        ↓
        Parse HTML and Plain Text Bodies
        ↓
        Extract Attachments (inline and attached)
        ↓
        Thread Matching:
            - Match Message-ID to existing threads
            - Use In-Reply-To for conversation linking
            - Parse FreeScout Message-ID format
        ↓
        Customer Lookup/Creation:
            - Find or create Customer by email
            - Create Email record if new
        ↓
        Conversation Lookup/Creation:
            - If reply: Find existing conversation
            - If new: Create new conversation
        ↓
        Thread Creation:
            - Create Thread record
            - Type: TYPE_CUSTOMER
            - State: STATE_PUBLISHED
            - Store headers as JSON
        ↓
        Attachment Processing:
            - Save files to storage
            - Create Attachment records
            - Link to Thread
        ↓
        Fire CustomerReplied Event
            ↓
            Queue SendAutoReply (if enabled and new conversation)
            ↓
            Queue SendNotificationToUsers
            ↓
            Update Folder Counters
```

**Key Files:**
- Command: `/home/user/freescout/app/Console/Commands/FetchEmails.php`
- Helper: `/home/user/freescout/app/Misc/Mail.php`
- Package: `webklex/php-imap` v4.1.1

**Fetching Configuration:**
- Schedule: Configurable (1, 5, 10, 15, 30, 60 minutes)
- Timeout: 30 minutes max execution
- Mutex locking: Prevents overlapping runs
- Batch size: 300 emails per fetch
- Mode: Unseen only or all emails

**Character Encoding Handling:**

FreeScout handles various email encodings:
```php
'iso-2022-jp' → 'iso-2022-jp-ms'
'gb2312' → 'gb18030'
```

Supports: UTF-8, ISO-8859-*, Windows-1252, Shift-JIS, GB2312, Big5, KOI8-R, and more.

**Reply Detection:**

Extracts new reply content by detecting separators:

```
FreeScout HTML: <div id="fsReplyAbove">
FreeScout Text: "-- Please reply above this line --"
Gmail:          <div class="gmail_quote">
Outlook:        <div id="appendonsend">
Yahoo:          yahoo_quoted_
QQ:             "原始邮件" / "Original"
ProtonMail:     <div class="protonmail_quote">
Generic:        <blockquote> tags
```

Uses regex patterns to strip quoted text from previous messages.

### Email Sending (Outgoing)

**Flow Diagram:**

```
User Composes Reply in UI
    ↓
ConversationsController::ajax('saveReply')
    ↓
Create Thread Record:
    - Type: TYPE_MESSAGE
    - State: STATE_PUBLISHED (or DRAFT)
    - Store body HTML and plain text
    ↓
Fire UserReplied Event
    ↓
SendReplyToCustomer Listener
    ↓
Queue SendReplyToCustomer Job
    ↓
Queue Worker Processes Job:
    ↓
    Load Mailbox Configuration
    ↓
    Configure SMTP via Mail::setMailDriver($mailbox)
        - Set SMTP host, port, encryption
        - Set credentials (username/password or OAuth)
        - Refresh OAuth token if needed
    ↓
    Build Email with SwiftMailer:
        - From: Mailbox email
        - To: Customer email
        - Cc/Bcc: From conversation
        - Subject: Conversation subject (with RE:)
        - Body: Thread body (HTML + plain text)
        - Attachments: Thread attachments
        - Message-ID: Format: reply-{mailbox_id}-{conv_id}-{time}@{domain}
        - In-Reply-To: Previous message-id
        - References: Email thread chain
    ↓
    Send via SMTP
    ↓
    Log to send_logs Table:
        - Status: Sent/Error
        - SMTP response
        - Queue ID
        - Timestamp
    ↓
    Update Thread send_status:
        - SUCCESS or ERROR
        - Store error details if failed
    ↓
    If Error:
        Queue SendEmailReplyError notification to user
```

**Key Files:**
- Job: `/home/user/freescout/app/Jobs/SendReplyToCustomer.php`
- Mail Class: `/home/user/freescout/app/Mail/ReplyToCustomer.php`
- Helper: `/home/user/freescout/app/Misc/Mail.php`

**SMTP Configuration:**

Per-mailbox SMTP settings:
- **Method:** PHP mail(), Sendmail, SMTP
- **Server:** SMTP hostname
- **Port:** 25, 587 (TLS), 465 (SSL), custom
- **Encryption:** None, SSL, TLS, StartTLS
- **Auth:** Username/password or OAuth

OAuth providers: Microsoft (Office 365), Google (Gmail)

**Message-ID Format:**

FreeScout generates custom Message-IDs for threading:

```
Format: [prefix]-[mailbox_id]-[conversation_id]-[timestamp]@[domain]

Examples:
reply-1-123-1234567890@example.com        (Customer reply)
autoreply-1-123-1234567890@example.com    (Auto-reply)
notify-1-123-1234567890@example.com       (User notification)
```

This allows FreeScout to:
1. Identify which mailbox sent the email
2. Link to the correct conversation
3. Track email threading
4. Handle bounce/error messages

### Email Threading Logic

**Threading Algorithm:**

1. **Incoming Email:**
   - Extract `Message-ID` header
   - Extract `In-Reply-To` header
   - Extract `References` header

2. **Conversation Matching:**
   - Parse FreeScout Message-ID format (if present)
   - Search for existing Thread by message-id
   - Search Threads by In-Reply-To
   - Match by customer email + subject similarity
   - Create new conversation if no match

3. **Thread Linking:**
   - Store message-id in Thread record
   - Store in-reply-to in Thread record
   - Maintain conversation_id relationship

4. **Outgoing Email:**
   - Generate FreeScout Message-ID
   - Set In-Reply-To to last customer message-id
   - Build References chain from conversation history

**Example Threading:**

```
Customer sends: "Help with order #123"
  Message-ID: <abc123@gmail.com>

FreeScout creates Conversation #1, Thread #1

Agent replies:
  Message-ID: reply-1-1-1234567890@freescout.com
  In-Reply-To: <abc123@gmail.com>
  References: <abc123@gmail.com>

Customer replies:
  Message-ID: <def456@gmail.com>
  In-Reply-To: reply-1-1-1234567890@freescout.com
  References: <abc123@gmail.com> reply-1-1-1234567890@freescout.com

FreeScout matches to Conversation #1, creates Thread #3
```

### Auto-Reply System

**Trigger:** New conversation from customer (if auto-reply enabled on mailbox)

**Flow:**

```
CustomerCreatedConversation Event Fired
    ↓
Check Mailbox auto_reply_enabled
    ↓
Queue SendAutoReply Job
    ↓
Job Processes:
    - Load mailbox auto-reply message
    - Compose email with custom subject/body
    - Send via mailbox SMTP
    - Message-ID: autoreply-{mailbox_id}-{conv_id}-{time}@{domain}
```

**Configuration:**
- Subject: Customizable (e.g., "We received your message")
- Body: Customizable HTML/text template
- Trigger: Only on new conversations (not replies)

**Key Files:**
- Job: `/home/user/freescout/app/Jobs/SendAutoReply.php`
- Mail Class: `/home/user/freescout/app/Mail/AutoReply.php`

### Attachment Handling

**Storage Structure:**

```
storage/app/attachments/
    ├── 1/
    │   ├── 2/
    │   │   ├── 3/
    │   │   │   └── file_hash_filename.ext
```

Nested directory structure (3 levels) for file system performance.

**Upload Flow:**

```
User Uploads File via /conversation/upload
    ↓
Validate File:
    - Check MIME type
    - Check file size
    - Sanitize filename
    ↓
Generate Unique Filename
    ↓
Store in Nested Directory Structure
    ↓
Create Attachment Record:
    - thread_id (pending if draft)
    - file_name
    - file_dir (nested path)
    - size
    - mime_type
    - embedded (inline image flag)
    ↓
Return Attachment ID to Frontend
    ↓
On Thread Save:
    Link Attachment to Thread
```

**Download/Access:**

```
Public URL: /storage/attachment/{nested_path}/{filename}
    ↓
OpenController::downloadAttachment()
    ↓
Validate Access (conversation participant)
    ↓
Stream File with Appropriate Headers
```

**Inline Images:**

Marked with `embedded = true` and displayed inline in email body using CID references.

---

## Module System

### Architecture

FreeScout uses `nwidart/laravel-modules` v2.7.0 for a plugin-based architecture.

**Module Structure:**

```
Modules/
    ├── ModuleName/
    │   ├── Config/
    │   ├── Database/
    │   │   └── Migrations/
    │   ├── Entities/         (Models)
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   └── Middleware/
    │   ├── Providers/
    │   │   └── ModuleNameServiceProvider.php
    │   ├── Resources/
    │   │   ├── views/
    │   │   └── assets/
    │   ├── Routes/
    │   │   └── web.php
    │   ├── module.json       (Metadata)
    │   └── composer.json     (Dependencies)
```

### Module Lifecycle

**1. Installation:**
```
Upload module files to Modules/ directory
    ↓
Run: php artisan module:install ModuleName
    ↓
Create record in modules table:
    - alias (unique identifier)
    - active (enabled flag)
    - license (license key if official)
    - activated (activation status)
```

**2. Activation:**
```
Enable in Database (active = 1)
    ↓
Boot Module Service Provider
    ↓
Register Module Routes
    ↓
Load Module Views
    ↓
Run Module Migrations
    ↓
Publish Module Assets
```

**3. Deactivation:**
```
Disable in Database (active = 0)
    ↓
Unregister Module Services
    ↓
Stop Loading Module Routes
```

**4. Updates:**
```
Replace module files
    ↓
Run: php artisan module:update ModuleName
    ↓
Run new migrations
    ↓
Clear cache
    ↓
Republish assets
```

### Module Registration

Modules are registered in `AppServiceProvider::boot()`:

```php
try {
    app('modules')->register();
} catch (\Exception $e) {
    // Log error
    // Deactivate module
    // Flash error to admin
}
```

Error handling prevents broken modules from breaking the entire application.

### Module Extension Points

**1. Event Hooks (Eventy Package):**

```php
// In module code:
Eventy::addFilter('conversation.view', function($html, $conversation) {
    return $html . '<div>Custom content</div>';
}, 20, 2);

Eventy::addAction('conversation.created', function($conversation) {
    // Custom logic
});
```

**2. Service Container:**

Modules can bind custom services:

```php
$this->app->singleton('module.service', function($app) {
    return new CustomService();
});
```

**3. Routes:**

Modules define custom routes in `Routes/web.php`:

```php
Route::group(['middleware' => 'web'], function() {
    Route::get('/custom-module/dashboard', 'DashboardController@index');
});
```

**4. Views:**

Modules can override core views or add new ones:

```php
return view('modulename::custom_view', compact('data'));
```

**5. Migrations:**

Modules have independent database migrations:

```
php artisan module:migrate ModuleName
```

**6. Assets:**

Module assets published to `public/modules/modulename/`:

```
php artisan module:publish-assets ModuleName
```

### Official Module Licensing

**Flow:**

```
Module Requires License
    ↓
Check modules table for license key
    ↓
freescout:module-check-licenses Command (periodic)
    ↓
Call FreeScout License API
    ↓
Validate License Key
    ↓
Update activated status in database
    ↓
If Invalid:
    Deactivate module
    Show warning to admin
```

**License Storage:**

```sql
modules table:
    license         VARCHAR     -- License key
    activated       BOOLEAN     -- Activation status
```

Only modules from freescout.net domain require licensing. Third-party modules are unrestricted.

---

## Frontend Architecture

### View Structure

Located in `/home/user/freescout/resources/views/`

**Layouts:**

```
layouts/
    └── app.blade.php          (Master layout)
        ├── Header
        ├── Sidebar Navigation
        ├── @yield('content')
        └── Footer
```

**Route-Based Views:**

```
secure/                        (Authenticated users)
    ├── dashboard.blade.php
    ├── logs.blade.php
    └── ...

conversations/
    ├── view.blade.php         (Conversation detail)
    ├── create.blade.php
    ├── search.blade.php
    └── partials/
        ├── thread.blade.php
        ├── reply_form.blade.php
        └── ...

mailboxes/
    ├── view.blade.php
    ├── settings.blade.php
    ├── permissions.blade.php
    └── ...

users/
    ├── profile.blade.php
    ├── permissions.blade.php
    └── ...

customers/
    ├── conversations.blade.php
    ├── profile.blade.php
    └── ...

system/
    ├── status.blade.php
    ├── tools.blade.php
    └── ...

emails/                        (Email templates)
    ├── customer/
    │   ├── reply_fancy.blade.php       (HTML email)
    │   ├── reply_fancy_text.blade.php  (Plain text)
    │   ├── auto_reply.blade.php
    │   └── ...
    └── user/
        ├── notification.blade.php
        ├── user_invite.blade.php
        └── ...
```

### JavaScript Architecture

Located in `/home/user/freescout/resources/assets/js/`

**Entry Point:** `app.js`

```javascript
// Load dependencies
require('./bootstrap');

// Application logic
// AJAX handlers
// Real-time polling
// UI interactions
```

**Key Features:**
- jQuery-based AJAX
- Real-time polling for notifications
- Dynamic content loading
- Form validation
- Attachment upload
- Wysiwyg editor integration
- Keyboard shortcuts

**Route Generation:**

`laroute.js` - JavaScript route generation (similar to Laravel's `route()` helper):

```javascript
laroute.route('conversation.view', {id: 123})
// Returns: /conversation/123
```

### CSS/Styling

- **Framework:** Bootstrap (responsive grid, components)
- **Compilation:** Laravel Mix (Webpack)
- **Customization:** SASS files in `resources/assets/sass/`

### Asset Compilation

**Build Commands:**

```bash
npm run dev        # Development build
npm run watch      # Watch for changes
npm run prod       # Production (minified)
```

**Output:**

```
public/
    ├── css/
    │   └── app.css        (Compiled CSS)
    ├── js/
    │   └── app.js         (Compiled JS)
    └── modules/           (Module assets)
```

### Real-Time Updates (Polycast)

**Architecture:** Database-backed polling (no external WebSocket server required)

**How It Works:**

```
Frontend JavaScript (Every 5-10 seconds)
    ↓
POST /polycast/receive
    ↓
PolycastController::receive()
    ↓
Query polycast_events Table:
    - WHERE channel IN (user_channels)
    - WHERE created_at > last_check
    ↓
Return Events as JSON
    ↓
JavaScript Processes Events:
    - New conversation notification
    - Conversation updated
    - User viewing conversation (show indicator)
    - New notification
    ↓
Update UI:
    - Show toast notification
    - Update conversation list
    - Update badge counts
    - Show "User is viewing" indicator
```

**Polycast Events Table:**

```sql
id              INT PRIMARY KEY
channel         VARCHAR         -- Channel name (e.g., private-user-123)
event           VARCHAR         -- Event class name
payload         TEXT (JSON)     -- Event data
created_at      TIMESTAMP

INDEX(channel, created_at)
```

Events auto-delete after 2 minutes (configurable).

**Broadcasting Channels:**

```php
'private-user-{user_id}'           - User-specific notifications
'presence-conversation-{conv_id}'  - Conversation viewers
'private-mailbox-{mailbox_id}'     - Mailbox updates
```

**Key Files:**
- Controller: `/home/user/freescout/app/Http/Controllers/PolycastController.php`
- Broadcaster: `/home/user/freescout/app/Broadcasting/PolycastBroadcaster.php`
- Service Provider: `/home/user/freescout/app/Providers/PolycastServiceProvider.php`

### UI Components

**Conversation View:**
- Thread list (chronological)
- Reply editor (WYSIWYG)
- Attachment upload
- Sidebar (customer info, conversation properties)
- Action buttons (assign, status, merge, etc.)

**Dashboard:**
- Folder list with counts
- Conversation list (paginated)
- Search
- Filters (status, assignment, customer)

**Mailbox View:**
- Folder navigation
- Conversation list
- Bulk actions

**Customer Profile:**
- Conversation history
- Contact information
- Edit capabilities

---

## Background Processing

### Queue System

**Configuration:** `config/queue.php`

**Supported Drivers:**
- `sync` - Synchronous (no queue, immediate processing)
- `database` - Database-backed queue
- `redis` - Redis-backed queue (higher performance)
- `sqs` - Amazon SQS (cloud deployments)

**Database Queue Tables:**

```sql
jobs:
    id              BIGINT PRIMARY KEY
    queue           VARCHAR         -- Queue name
    payload         LONGTEXT        -- Serialized job
    attempts        TINYINT         -- Retry count
    reserved_at     INT UNSIGNED    -- Reserved timestamp
    available_at    INT UNSIGNED    -- Available timestamp
    created_at      INT UNSIGNED

failed_jobs:
    id              BIGINT PRIMARY KEY
    connection      TEXT
    queue           TEXT
    payload         LONGTEXT
    exception       LONGTEXT        -- Error details
    failed_at       TIMESTAMP
```

### Queue Jobs

Located in `/home/user/freescout/app/Jobs/`

#### **SendReplyToCustomer** (25KB)
Sends email reply to customer.

**Properties:**
- `$queue = 'emails'` - Dedicated email queue
- `$tries = 1` - No automatic retry (error logging instead)

**Process:**
1. Load Thread, Conversation, Mailbox
2. Configure SMTP via `Mail::setMailDriver($mailbox)`
3. Refresh OAuth token if needed
4. Build email with attachments
5. Send via SwiftMailer
6. Log to send_logs table
7. Update Thread send_status
8. If error: Queue SendEmailReplyError notification

#### **SendAutoReply** (5KB)
Sends automated responses.

**Trigger:** New conversation from customer (if mailbox has auto-reply enabled)

**Process:**
1. Load Mailbox auto-reply settings
2. Build email from template
3. Send via SMTP
4. Log send

#### **SendNotificationToUsers** (9KB)
Notifies users of conversation activity.

**Properties:**
- `$queue = 'default'`

**Process:**
1. Load Conversation, Mailbox, Users
2. Filter users:
   - Following conversation
   - Assigned to mailbox
   - Not muted
   - Has notification preference enabled
3. For each user:
   - Build notification email
   - Send via queue
   - Create database notification record

#### **SendAlert** (3KB)
Sends administrative alerts.

**Use Cases:**
- System errors
- Queue failures
- Module issues
- License problems

#### **SendEmailReplyError** (3KB)
Notifies user when their reply fails to send.

**Trigger:** SendReplyToCustomer job fails

**Process:**
1. Load failed Thread
2. Build error notification
3. Send to original author
4. Include error details

#### **UpdateFolderCounters** (1KB)
Recalculates conversation counts for folders.

**Trigger:**
- Scheduled hourly
- On-demand via AJAX
- After bulk operations

**Process:**
1. Load all folders for mailbox
2. Count conversations matching folder criteria
3. Update cached counter

#### **TriggerAction** (1KB)
Executes workflow automation actions.

**Extensible:** Modules can add custom actions.

### Queue Worker Management

**Worker Process:**

```bash
php artisan queue:work --queue=emails,default --sleep=3 --tries=1 --timeout=600
```

**Configuration:**

```php
// config/app.php
'queue_work_params' => '--sleep=3 --tries=1 --timeout=600'
```

**Scheduler Integration:**

```php
// Kernel.php schedule()
$schedule->command('queue:work', $params)
         ->everyMinute()
         ->withoutOverlapping()
         ->runInBackground();
```

**Mutex Locking:**

Prevents multiple worker instances:
- Uses framework mutex (cache-based)
- `withoutOverlapping()` prevents duplicate runs
- Kills long-running workers after timeout

**Monitoring:**

`freescout:send-monitor` command checks:
- Queue worker process running
- Job processing rate
- Failed jobs count
- Alerts if worker stopped

### Scheduled Tasks (Cron)

**Cron Setup:**

Two methods:

**Method 1: Laravel Scheduler (Recommended)**

```bash
# Add to crontab:
* * * * * cd /path/to/freescout && php artisan schedule:run >> /dev/null 2>&1
```

**Method 2: HTTP Cron Endpoint**

```bash
# Add to crontab or external cron service:
* * * * * curl https://freescout.example.com/system/cron/{CRON_HASH}
```

**Scheduled Task List:**

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `queue:flush` | Weekly (Sun 00:00) | Remove failed jobs |
| `queue:restart` | Hourly | Restart queue worker |
| `freescout:fetch-emails` | Configurable (1-60 min) | Fetch incoming emails |
| `freescout:send-monitor` | Every 10 minutes | Monitor email sending |
| `freescout:update-folder-counters` | Hourly | Update folder counts |
| `freescout:clean-send-log` | Monthly (1st, 00:00) | Clean old send logs |
| `freescout:clean-notifications-table` | Weekly (Sun 00:00) | Clean notifications |
| `freescout:clean-tmp` | Daily (02:00) | Clean temp files |
| `freescout:check-conv-viewers` | Every minute | Update conversation viewers |
| `freescout:logs-monitor` | Configurable | Monitor application logs |
| `freescout:module-check-licenses` | Based on app key hash | Verify module licenses |
| `queue:work` | Every minute | Process queue jobs |

**Fetch Emails Detail:**

```php
$schedule->command('freescout:fetch-emails')
         ->cron(Mail::fetchSchedule())  // Dynamic cron expression
         ->withoutOverlapping(30)        // Max 30 min execution
         ->runInBackground();
```

Configurable schedules:
- Every minute: `* * * * *`
- Every 5 minutes: `*/5 * * * *`
- Every 10 minutes: `*/10 * * * *`
- Every 15 minutes: `*/15 * * * *`
- Every 30 minutes: `*/30 * * * *`
- Every hour: `0 * * * *`

**Process Monitoring:**

Commands track long-running processes and kill if timeout exceeded:

```php
// Check if process is running
$pid = Option::get('fetch_emails_pid');
$running = Helper::isPidRunning($pid);

if ($running && $timeout_exceeded) {
    posix_kill($pid, SIGKILL);
    Log::error('Killed long-running fetch process');
}
```

---

## Authentication & Authorization

### Authentication Flow

**Login Process:**

```
User Visits /login
    ↓
Display Login Form
    ↓
User Submits Credentials
    ↓
LoginController::login()
    ↓
Validate Input (email, password required)
    ↓
Attempt Authentication:
    Auth::attempt([
        'email' => $email,
        'password' => $password
    ], $remember)
    ↓
On Success:
    - Create Session
    - Fire Login Event
        → RememberUserLocale Listener (set locale)
        → LogSuccessfulLogin Listener (activity log)
        → ActivateUser Listener (activate status)
    - Redirect to intended URL or dashboard
    ↓
On Failure:
    - Increment login attempts
    - Show error message
    - Lockout after 5 attempts (throttle)
```

**Key Files:**
- Controller: `/home/user/freescout/app/Http/Controllers/Auth/LoginController.php`
- Middleware: `/home/user/freescout/app/Http/Middleware/Authenticate.php`

**Session Configuration:**

```php
// config/session.php
'lifetime' => 525600,           // 1 year in minutes
'expire_on_close' => false,     // Persist across browser closes
'encrypt' => true,              // Encrypt session data
'secure' => true,               // HTTPS only (if APP_URL is https)
'http_only' => true,            // Not accessible to JavaScript
'same_site' => 'lax',           // CSRF protection
```

### Alternative Authentication: Token Auth

**TokenAuth Middleware:**

Allows API-style authentication via custom token:

```php
// Request:
Authorization: Bearer {user_token}

// Or query parameter:
?auth_token={user_token}

// Middleware checks:
User::where('auth_token', $token)->first()

// If found:
Auth::login($user)
```

**Use Cases:**
- Mobile app authentication
- Third-party integrations
- Webhook callbacks

### Authorization (Roles & Permissions)

**User Roles:**

```php
ROLE_USER = 1    // Standard agent
ROLE_ADMIN = 2   // Administrator
```

**Admin Capabilities:**
- User management (create, edit, delete users)
- Mailbox creation and configuration
- System settings
- Module management
- Access to all conversations
- System tools and logs

**User Capabilities:**
- Access to assigned mailboxes only
- Conversation management (per permissions)
- Profile settings
- Notification preferences

**Permission Bitfield:**

```php
PERM_DELETE_CONVERSATIONS = 1      // Delete conversations
PERM_EDIT_CONVERSATIONS = 2        // Edit conversation details
PERM_EDIT_SAVED_REPLIES = 3        // Manage saved replies
PERM_EDIT_TAGS = 4                 // Manage tags
PERM_EDIT_CUSTOM_FOLDERS = 5       // Manage custom folders
PERM_EDIT_USERS = 10               // Manage users (admin-like)
```

**Permission Checking:**

```php
// In Controller:
if ($user->isAdmin()) {
    // Admin-only logic
}

if ($user->hasPermission(User::PERM_DELETE_CONVERSATIONS)) {
    // Allow deletion
}

// In Blade:
@if(auth()->user()->isAdmin())
    <button>Delete</button>
@endif
```

**Mailbox-Level Permissions:**

```sql
mailbox_user table:
    user_id         INT FK
    mailbox_id      INT FK
    after_send      TINYINT         -- Conversation status after send
    can_view_drafts BOOLEAN
    can_create      BOOLEAN
    can_delete      BOOLEAN
    ...custom permissions
```

Users can have different permissions per mailbox.

### Authorization Policies

Located in `/home/user/freescout/app/Policies/`

**ConversationPolicy:**

```php
view($user, $conversation)
    - Check user has access to mailbox
    - Check conversation belongs to accessible mailbox

update($user, $conversation)
    - Check view permission
    - Check PERM_EDIT_CONVERSATIONS

delete($user, $conversation)
    - Check admin or PERM_DELETE_CONVERSATIONS
```

**MailboxPolicy:**

```php
view($user, $mailbox)
    - Check user assigned to mailbox or admin

update($user, $mailbox)
    - Check admin only

access($user, $mailbox)
    - Check mailbox_user relationship or admin
```

**UserPolicy:**

```php
update($user, $targetUser)
    - Check admin or editing self (profile only)

delete($user, $targetUser)
    - Check admin
    - Prevent self-deletion
```

**Usage in Controllers:**

```php
$this->authorize('view', $conversation);
$this->authorize('update', $mailbox);

// Or:
if (Gate::denies('update', $conversation)) {
    abort(403);
}
```

### Password Reset Flow

```
User Visits /password/reset
    ↓
Enter Email Address
    ↓
ForgotPasswordController::sendResetLinkEmail()
    ↓
Generate Password Reset Token
    ↓
Store in password_resets Table:
    - email
    - token (hashed)
    - created_at
    ↓
Queue Password Reset Email
    ↓
User Clicks Link in Email
    ↓
ResetPasswordController::showResetForm()
    ↓
User Enters New Password
    ↓
Validate Token (not expired, matches email)
    ↓
Update User Password (hashed)
    ↓
Delete Token from password_resets
    ↓
Fire PasswordReset Event
    ↓
SendPasswordChanged Listener
    ↓
Queue Password Changed Notification Email
    ↓
Redirect to Login
```

**Token Expiration:** 60 minutes (configurable in `config/auth.php`)

### Security Features

**CSRF Protection:**

```blade
@csrf
<!-- Generates: -->
<input type="hidden" name="_token" value="{csrf_token}">
```

All POST/PUT/DELETE requests validated via `VerifyCsrfToken` middleware.

**Password Hashing:**

```php
// Uses bcrypt by default
Hash::make($password)        // Hash password
Hash::check($plain, $hash)   // Verify password
```

**Logout Deleted Users:**

`LogoutIfDeleted` middleware checks user status and logs out if deleted/disabled:

```php
if ($user->isDeleted() || $user->isDisabled()) {
    Auth::logout();
    return redirect('/login')->with('error', 'Account disabled');
}
```

**HTTPS Enforcement:**

`HttpsRedirect` middleware forces HTTPS if `APP_URL` starts with `https://`.

**Session Fixation Protection:**

Laravel regenerates session ID on login to prevent session fixation attacks.

---

## API & Integration Points

### Web Routes

All routes defined in `/home/user/freescout/routes/web.php`

**Route Structure:**

```
/ or /{dashboard_path}              Dashboard
/login, /register, /password/*      Authentication

/mailbox/{id}                       Mailbox view
/mailbox/{id}/{folder_id}           Mailbox by folder
/conversation/{id}                  Conversation view
/conversation/ajax                  AJAX operations
/customers/{id}/conversations       Customer view
/users                              User management
/app-settings/{section}             Settings
/system/status                      System status
/system/cron/{hash}                 Cron endpoint (public)

/polycast/connect                   Real-time connect
/polycast/receive                   Real-time polling
```

**Route Middleware Groups:**

```php
Route::group(['middleware' => 'web'], function() {
    // Web routes (session, CSRF, etc.)
});

Route::group(['middleware' => ['web', 'auth']], function() {
    // Authenticated routes
});

Route::group(['middleware' => ['web', 'auth', 'roles:admin']], function() {
    // Admin-only routes
});
```

### AJAX Endpoints

FreeScout is heavily AJAX-driven for better UX.

**Primary AJAX Endpoints:**

#### **Conversation AJAX** (`POST /conversation/ajax`)

**Parameters:**
- `action` (required) - Action identifier
- Additional params per action

**Actions:**

| Action | Description | Required Params |
|--------|-------------|-----------------|
| `saveReply` | Save and send reply | `body`, `conversation_id` |
| `saveDraft` | Save draft | `body`, `conversation_id` |
| `updateStatus` | Change status | `conversation_id`, `status` |
| `assign` | Assign to user | `conversation_id`, `user_id` |
| `addFollower` | Add follower | `conversation_id`, `user_id` |
| `removeFollower` | Remove follower | `conversation_id`, `user_id` |
| `mark` | Star/unstar | `conversation_id` |
| `merge` | Merge conversations | `conversation_id`, `merge_into_id` |
| `move` | Move to mailbox | `conversation_id`, `mailbox_id` |
| `delete` | Delete conversation | `conversation_id` |
| `updateSubject` | Edit subject | `conversation_id`, `subject` |
| `updateCustomer` | Change customer | `conversation_id`, `customer_id` |

**Response Format:**

```json
{
    "status": "success",
    "msg": "Reply sent successfully",
    "msg_success": true,
    "thread_id": 123,
    "conversation": {
        "id": 1,
        "subject": "...",
        "status": 1
    }
}
```

#### **Mailbox AJAX** (`POST /mailbox/ajax`)

**Actions:**
- `testConnection` - Test IMAP/SMTP connection
- `getSettings` - Fetch mailbox configuration
- `saveSettings` - Update mailbox settings

#### **Customer AJAX** (`GET /customers/ajax-search`)

**Parameters:**
- `q` - Search query (name, email, company)
- `exclude_email` - Exclude specific email

**Response:**

```json
{
    "results": [
        {
            "id": 1,
            "text": "John Doe (john@example.com)"
        }
    ]
}
```

#### **System AJAX** (`POST /system/ajax`)

**Actions:**
- `checkUpdates` - Check for system updates
- `clearCache` - Clear application cache
- `runCommand` - Execute artisan command (admin)

### OAuth Integration

**Supported Providers:**

1. **Microsoft (Office 365/Outlook.com)**
   - OAuth 2.0
   - IMAP/SMTP via OAuth
   - Refresh token handling

2. **Google (Gmail)**
   - OAuth 2.0
   - IMAP/SMTP via OAuth
   - Refresh token handling

**OAuth Flow:**

```
User Initiates OAuth Connection
    ↓
GET /mailbox/oauth/{mailbox_id}/{in_out}/{provider}
    ↓
Redirect to Provider Authorization URL:
    - Microsoft: login.microsoftonline.com
    - Google: accounts.google.com
    ↓
User Authorizes
    ↓
Provider Redirects to Callback URL with Auth Code
    ↓
Exchange Auth Code for Tokens
    ↓
Store Tokens Encrypted in Mailbox Record:
    {
        "access_token": "...",
        "refresh_token": "...",
        "expires_at": 1234567890
    }
    ↓
Use Access Token for IMAP/SMTP
    ↓
On Expiry:
    Refresh Access Token using Refresh Token
    Update Stored Tokens
```

**Token Refresh:**

```php
// Before fetching or sending
if ($mailbox->isOAuthTokenExpired()) {
    $mailbox->refreshOAuthToken();
}

// Set in IMAP connection
$client->setOauthToken($mailbox->getOAuthAccessToken());
```

### Cron Endpoint (Public API)

**Endpoint:** `GET /system/cron/{hash}`

**Purpose:** Allow external cron services to trigger scheduled tasks without SSH access.

**Security:**

```php
// Hash generated from:
$hash = sha1(config('app.key') . config('app.url'));

// Validation:
if ($hash !== $provided_hash) {
    abort(403);
}

// HTTPS enforcement (production)
if (app()->environment('production') && !request()->secure()) {
    abort(403);
}
```

**Usage:**

```bash
# In external cron service (e.g., cron-job.org)
GET https://freescout.example.com/system/cron/abc123def456...
```

**Execution:**

```php
Artisan::call('schedule:run');
```

Runs all scheduled tasks defined in Console Kernel.

### Webhook Handlers

**Module-Extensible:**

Modules can register webhook routes:

```php
// In module routes/web.php
Route::post('/webhook/custom-service', 'WebhookController@handle');
```

**Use Cases:**
- Payment webhooks (subscription modules)
- Chat integrations (Slack, Teams)
- CRM integrations (Salesforce, HubSpot)
- Ticketing integrations (Jira, Trello)

### API Limitations

**Current State:**

FreeScout **does not have a REST API** in the core application.

**What's Available:**
- AJAX endpoints (session-based auth only)
- OAuth integrations (for email)
- Cron endpoint (hash-based auth)
- Module-extensible webhook handlers

**What's Missing:**
- REST API routes (`/api/*`)
- API token authentication (besides basic TokenAuth)
- Rate limiting
- API versioning
- Webhook management UI
- API documentation

**Potential for Rebuild:**

A modern rebuild should include:
- RESTful API (`/api/v1/*`)
- API key management
- OAuth 2.0 for third parties
- GraphQL endpoint (optional)
- Webhook CRUD via UI
- API rate limiting
- Comprehensive API docs (OpenAPI/Swagger)

---

## Configuration & Deployment

### Environment Configuration

**Environment File:** `.env`

**Critical Variables:**

```bash
# Application
APP_NAME=FreeScout
APP_ENV=production              # local, staging, production
APP_DEBUG=false                 # NEVER true in production
APP_URL=https://support.example.com
APP_KEY=base64:...              # Encryption key (CRITICAL)
APP_TIMEZONE=UTC
APP_LOCALE=en

# Database
DB_CONNECTION=mysql             # mysql, pgsql, sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freescout
DB_USERNAME=freescout_user
DB_PASSWORD=secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_TABLE_PREFIX=                # Optional prefix

# Email (Global Defaults)
MAIL_DRIVER=smtp                # mail, sendmail, smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls             # null, tls, ssl

# Caching
CACHE_DRIVER=file               # file, redis, database
SESSION_DRIVER=file             # file, redis, database
QUEUE_DRIVER=database           # sync, database, redis

# Broadcasting
BROADCAST_DRIVER=polycast       # polycast, pusher, redis

# Application Paths
APP_LOGIN_PATH=/login
APP_DASHBOARD_PATH=/mailboxes
SUBDIRECTORY=                   # If installed in subdirectory

# Security
FORCE_HTTPS=true
```

### Database Configuration

**Recommended Setup:**

```
MySQL 5.7+ or MariaDB 10.3+
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
InnoDB storage engine
Foreign key constraints enabled
```

**Configuration:** `config/database.php`

```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'freescout'),
    'username' => env('DB_USERNAME', 'freescout'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => env('DB_TABLE_PREFIX', ''),
    'strict' => false,          // Compatibility mode
    'engine' => 'InnoDB',
],
```

**Connection Pooling:**

For high-traffic deployments, configure persistent connections:

```php
'options' => [
    PDO::ATTR_PERSISTENT => true,
],
```

**SSL Connection:**

```php
'options' => [
    PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca-cert.pem',
],
```

### Cache Configuration

**Drivers:** `config/cache.php`

**File Cache (Default):**
```php
'file' => [
    'driver' => 'file',
    'path' => storage_path('framework/cache/data'),
],
```

**Redis Cache (Recommended for Production):**
```php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
],

// In .env:
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DATABASE=1
```

**Cache Usage:**

```php
Cache::remember('user.'.$id, 60, function() use ($id) {
    return User::find($id);
});

Cache::forget('key');
Cache::flush(); // Clear all
```

### Queue Configuration

**Database Queue (Default):**

Simple, no extra dependencies:

```bash
QUEUE_DRIVER=database
```

**Redis Queue (Recommended for Production):**

Higher performance, better for high-volume:

```bash
QUEUE_DRIVER=redis
REDIS_QUEUE=default
```

**Worker Process:**

**Supervisor Configuration:**

```ini
[program:freescout-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/freescout/artisan queue:work redis --sleep=3 --tries=3 --timeout=600
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/freescout/storage/logs/worker.log
```

**Queue Monitoring:**

```bash
# Check queue size
php artisan queue:work --once

# Restart workers
php artisan queue:restart

# Retry failed jobs
php artisan queue:retry all

# List failed jobs
php artisan queue:failed
```

### File Storage

**Configuration:** `config/filesystems.php`

**Local Disk (Default):**

```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],

'attachments' => [
    'driver' => 'local',
    'root' => storage_path('app/attachments'),
],
```

**Cloud Storage (S3-Compatible):**

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

**Attachment Storage Structure:**

```
storage/app/attachments/
    ├── 1/
    │   ├── 2/
    │   │   ├── 3/
    │   │   │   └── {hash}_{filename}.ext
```

Three-level nested directory for file system performance at scale.

### Web Server Configuration

#### **Apache (with mod_rewrite):**

```apache
<VirtualHost *:443>
    ServerName support.example.com
    DocumentRoot /var/www/freescout/public

    <Directory /var/www/freescout/public>
        AllowOverride All
        Require all granted
    </Directory>

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem

    # Security Headers
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

#### **Nginx:**

```nginx
server {
    listen 443 ssl http2;
    server_name support.example.com;
    root /var/www/freescout/public;

    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Security Headers
    add_header X-Frame-Options "DENY";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Max upload size
    client_max_body_size 50M;
}
```

### PHP Configuration

**php.ini Requirements:**

```ini
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300

; Required extensions
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=tokenizer
extension=xml
extension=ctype
extension=json
extension=imap

; Recommended
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### File Permissions

**Correct Permissions:**

```bash
# Ownership
chown -R www-data:www-data /var/www/freescout

# Directories
find /var/www/freescout -type d -exec chmod 755 {} \;

# Files
find /var/www/freescout -type f -exec chmod 644 {} \;

# Writable directories
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Environment file (restrict)
chmod 600 .env
```

### Deployment Checklist

**Pre-Deployment:**

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate `APP_KEY` (`php artisan key:generate`)
- [ ] Configure database connection
- [ ] Configure email settings
- [ ] Set correct `APP_URL` (with https://)
- [ ] Review `.env` for sensitive data
- [ ] Set up SSL certificate
- [ ] Configure cron jobs
- [ ] Set up queue worker (supervisor)
- [ ] Configure file permissions
- [ ] Test email sending/receiving
- [ ] Set up backups

**Post-Deployment:**

- [ ] Run migrations (`php artisan migrate`)
- [ ] Clear cache (`php artisan cache:clear`)
- [ ] Clear config cache (`php artisan config:clear`)
- [ ] Compile assets (`npm run prod`)
- [ ] Test login/authentication
- [ ] Test mailbox connection
- [ ] Test conversation creation
- [ ] Monitor logs for errors
- [ ] Set up monitoring (uptime, errors)

### Backup Strategy

**What to Back Up:**

1. **Database:**
```bash
mysqldump -u user -p freescout > backup_$(date +%Y%m%d).sql
```

2. **Attachments:**
```bash
tar -czf attachments_$(date +%Y%m%d).tar.gz storage/app/attachments/
```

3. **Environment File:**
```bash
cp .env .env.backup_$(date +%Y%m%d)
```

4. **Custom Modules:**
```bash
tar -czf modules_$(date +%Y%m%d).tar.gz Modules/
```

**Automated Backup Script:**

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/backups/freescout/$DATE

mkdir -p $BACKUP_DIR

# Database
mysqldump -u user -p freescout | gzip > $BACKUP_DIR/database.sql.gz

# Files
tar -czf $BACKUP_DIR/attachments.tar.gz /var/www/freescout/storage/app/attachments/
tar -czf $BACKUP_DIR/modules.tar.gz /var/www/freescout/Modules/
cp /var/www/freescout/.env $BACKUP_DIR/.env

# Retention: Keep 30 days
find /backups/freescout/ -type d -mtime +30 -exec rm -rf {} \;
```

### Monitoring & Logging

**Application Logs:**

```
storage/logs/laravel-YYYY-MM-DD.log
```

**Log Levels:**
- Emergency
- Alert
- Critical
- Error
- Warning
- Notice
- Info
- Debug

**Log Viewing:**

```
# Via command line
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Via web interface
/app-logs/app
```

**Error Reporting:**

Production configuration (`config/app.php`):

```php
'debug' => env('APP_DEBUG', false),
'log_level' => env('APP_LOG_LEVEL', 'error'),
```

**External Monitoring:**

Recommended tools:
- **Uptime:** UptimeRobot, Pingdom
- **Error Tracking:** Sentry, Bugsnag, Rollbar
- **Performance:** New Relic, Datadog
- **Log Aggregation:** Papertrail, Loggly

---

## Architecture Patterns

### Design Patterns Used

#### **1. MVC Pattern (Model-View-Controller)**

```
User Request
    ↓
Route → Controller (Business Logic)
    ↓
Model (Data Access)
    ↓
View (Blade Template)
    ↓
Response to User
```

#### **2. Observer Pattern**

Model observers for lifecycle hooks:

```php
// AppServiceProvider::boot()
Conversation::observe(ConversationObserver::class);

// ConversationObserver
public function created($conversation) {
    // Auto-assign conversation number
    $conversation->number = Conversation::getNextNumber();
}
```

#### **3. Event-Listener Pattern**

Decoupled event processing:

```php
// Fire event
event(new UserReplied($thread, $conversation));

// Multiple listeners
SendReplyToCustomer::class
SendNotificationToUsers::class
RefreshConversations::class
```

#### **4. Repository Pattern (via Eloquent)**

Models act as repositories:

```php
$conversation = Conversation::where('mailbox_id', $id)
    ->where('status', Conversation::STATUS_ACTIVE)
    ->with('customer', 'threads')
    ->get();
```

#### **5. Service Provider Pattern**

Bootstrapping services:

```php
class AppServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton('service', function($app) {
            return new CustomService();
        });
    }
}
```

#### **6. Middleware Pipeline Pattern**

Request filtering:

```
Request
    → CheckForMaintenanceMode
    → TrimStrings
    → EncryptCookies
    → StartSession
    → Authenticate
    → Authorize
    → Controller
```

#### **7. Command Pattern**

Console commands:

```php
php artisan freescout:fetch-emails
```

Each command is a self-contained class with `handle()` method.

#### **8. Factory Pattern**

Model factories for testing:

```php
factory(User::class)->create([
    'email' => 'test@example.com'
]);
```

#### **9. Policy Pattern**

Authorization policies:

```php
$this->authorize('view', $conversation);
```

#### **10. Queue Pattern**

Asynchronous processing:

```php
SendReplyToCustomer::dispatch($thread, $conversation);
```

### Architectural Layers

```
┌─────────────────────────────────────┐
│         Presentation Layer          │
│  (Blade Views, JavaScript, CSS)     │
└────────────────┬────────────────────┘
                 │
┌────────────────▼────────────────────┐
│        Application Layer            │
│  (Controllers, Middleware, Forms)   │
└────────────────┬────────────────────┘
                 │
┌────────────────▼────────────────────┐
│         Domain Layer                │
│  (Events, Listeners, Policies)      │
└────────────────┬────────────────────┘
                 │
┌────────────────▼────────────────────┐
│      Infrastructure Layer           │
│  (Models, Database, External APIs)  │
└─────────────────────────────────────┘
```

### Data Flow Patterns

#### **Incoming Email Flow:**

```
External Email Server
    ↓ IMAP/POP3
IMAP Client (webklex/php-imap)
    ↓
FetchEmails Command
    ↓
Mail Helper (parsing)
    ↓
Customer Model (find/create)
    ↓
Conversation Model (find/create)
    ↓
Thread Model (create)
    ↓
Attachment Model (create)
    ↓
CustomerReplied Event
    ↓
Listeners (auto-reply, notifications)
    ↓
Queue Jobs
    ↓
Queue Worker
    ↓
External Email Server (SMTP)
```

#### **Outgoing Email Flow:**

```
User Interface (Conversation View)
    ↓ AJAX
ConversationsController::ajax('saveReply')
    ↓
Thread Model (create/update)
    ↓
UserReplied Event
    ↓
SendReplyToCustomer Listener
    ↓
SendReplyToCustomer Job (queued)
    ↓
Queue Worker
    ↓
Mail Helper (build email)
    ↓
SwiftMailer (SMTP send)
    ↓
External Email Server
```

#### **Real-Time Update Flow:**

```
User Action (assign conversation)
    ↓
Controller fires Broadcasting Event
    ↓
PolycastBroadcaster::broadcast()
    ↓
Insert into polycast_events Table
    ↓
Frontend JavaScript polls /polycast/receive
    ↓
PolycastController::receive()
    ↓
Query polycast_events for user channels
    ↓
Return events as JSON
    ↓
JavaScript processes events
    ↓
Update UI (show notification, update list)
```

### Performance Optimizations

**1. Query Caching (Rememberable Trait):**

```php
// Automatic query caching
$user = User::remember(60)->find($id);

// Cache invalidated on model update
$user->update(['name' => 'New Name']); // Auto-clears cache
```

**2. Eager Loading:**

```php
// Prevents N+1 queries
$conversations = Conversation::with('customer', 'threads', 'mailbox')->get();
```

**3. Database Indexing:**

Critical indexes:
- `conversations(folder_id, status)`
- `conversations(mailbox_id, customer_id)`
- `threads(conversation_id)`
- `threads(message_id)`
- `polycast_events(channel, created_at)`

**4. Batch Processing:**

Email fetching in pages of 300 to avoid memory exhaustion.

**5. Mutex Locking:**

Prevents duplicate cron runs:

```php
$schedule->command('freescout:fetch-emails')
    ->withoutOverlapping(30);
```

**6. Async Processing:**

Email sending, notifications queued for background processing.

**7. Schema String Limit:**

```php
Schema::defaultStringLength(191);
```

Ensures MySQL key length compatibility (utf8mb4).

**8. Selective Field Loading:**

```php
$users = User::select('id', 'email', 'name')->get();
```

**9. Cache Configuration:**

```php
Config::set('cache.default', 'redis'); // Faster than file
```

**10. Process Monitoring:**

Kills long-running processes to prevent resource exhaustion.

### Security Measures

**1. CSRF Protection:**

All forms include CSRF tokens validated by middleware.

**2. SQL Injection Prevention:**

Eloquent uses parameterized queries:

```php
User::where('email', $input)->first(); // Safe
```

**3. XSS Prevention:**

Blade automatically escapes output:

```blade
{{ $user->name }}  <!-- Escaped -->
{!! $html !!}      <!-- Raw HTML, use with caution -->
```

**4. Password Hashing:**

Bcrypt hashing with automatic salt:

```php
Hash::make($password);
```

**5. Encryption:**

Sensitive data encrypted at rest:

```php
Crypt::encrypt($data);  // Mailbox passwords, OAuth tokens
```

**6. HTTPS Enforcement:**

Middleware redirects HTTP to HTTPS in production.

**7. Security Headers:**

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
```

**8. Authentication Throttling:**

Rate limiting on login attempts (5 attempts, 1 minute lockout).

**9. Email Validation:**

```php
// Uses egulias/email-validator
Email::validate($email);
```

**10. HTML Purification:**

```php
// Uses mews/purifier
clean($html, 'default');
```

**11. SVG Sanitization:**

```php
// Uses enshrined/svg-sanitize
$sanitizer = new SVG_Sanitizer();
$clean_svg = $sanitizer->sanitize($svg);
```

**12. File Upload Validation:**

```php
$request->validate([
    'attachment' => 'required|file|max:10240|mimes:jpg,png,pdf,doc,docx'
]);
```

### Extensibility Mechanisms

**1. Module System:**

Full plugin architecture via `nwidart/laravel-modules`.

**2. Event Hooks:**

```php
Eventy::addFilter('conversation.view', function($html) {
    return $html . '<div>Custom</div>';
});
```

**3. Service Container:**

```php
app()->bind('service', CustomService::class);
```

**4. Middleware:**

```php
$kernel->pushMiddleware(CustomMiddleware::class);
```

**5. Route Registration:**

Modules can add routes dynamically.

**6. Database Migrations:**

Modules have independent migrations.

**7. Views Override:**

Modules can override core views.

**8. Configuration Publishing:**

```php
php artisan vendor:publish --tag=config
```

**9. Observer Pattern:**

Custom observers for model lifecycle.

**10. Policy Pattern:**

Custom authorization logic.

---

## Recommendations for Rebuild

### Architecture Recommendations

If rebuilding FreeScout from scratch, consider these modern approaches:

#### **1. Eliminate Override System**

**Current Problem:** 279 override files (3.9MB) modifying core Laravel/Symfony/packages.

**Recommendation:**
- Use **Laravel Events** for customization
- Use **Service Container Binding** for replacements
- Use **Macros** for extending classes
- Contribute bug fixes upstream to packages
- Create proper **Laravel Packages** for reusable code
- Target: **0 core overrides**, <10 custom package bindings

#### **2. Implement Service Layer**

**Current:** Business logic in controllers (3,600-line ConversationsController).

**Recommendation:**

```
Controllers (HTTP layer)
    ↓
Services (Business logic)
    ↓
Repositories (Data access)
    ↓
Models (Data representation)
```

**Example:**

```php
// Controller
public function assign(Request $request, ConversationService $service) {
    $service->assignToUser($request->conversation_id, $request->user_id);
}

// Service
class ConversationService {
    public function __construct(
        private ConversationRepository $repo,
        private EventDispatcher $events
    ) {}

    public function assignToUser(int $convId, int $userId) {
        $conversation = $this->repo->find($convId);
        $conversation->user_id = $userId;
        $this->repo->save($conversation);
        $this->events->dispatch(new ConversationAssigned($conversation));
    }
}

// Repository
class ConversationRepository {
    public function find(int $id): Conversation {
        return Conversation::with('customer', 'mailbox')->findOrFail($id);
    }
}
```

**Benefits:**
- Testable business logic
- Reusable across controllers/commands
- Clear separation of concerns
- Easier to maintain

#### **3. Add Comprehensive Testing**

**Current:** Only 6 test files, minimal coverage.

**Recommendation:**

```
tests/
    ├── Unit/
    │   ├── Models/
    │   ├── Services/
    │   └── Helpers/
    ├── Feature/
    │   ├── Email/
    │   ├── Conversations/
    │   └── API/
    └── Integration/
        └── IMAP/
```

**Coverage Targets:**
- Unit tests: 80%+ coverage
- Feature tests: Critical user flows
- Integration tests: Email fetching/sending
- Browser tests: UI workflows (Laravel Dusk)

**Example:**

```php
// Feature test
public function test_user_can_reply_to_conversation() {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();

    $response = $this->actingAs($user)->post('/conversation/ajax', [
        'action' => 'saveReply',
        'conversation_id' => $conversation->id,
        'body' => 'Test reply'
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('threads', [
        'conversation_id' => $conversation->id,
        'body' => 'Test reply'
    ]);
}
```

#### **4. Build REST API**

**Current:** No REST API, only AJAX endpoints.

**Recommendation:**

```php
routes/api.php:

Route::middleware('auth:sanctum')->group(function() {
    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::patch('/conversations/{id}', [ConversationController::class, 'update']);

    // Threads
    Route::get('/conversations/{id}/threads', [ThreadController::class, 'index']);
    Route::post('/conversations/{id}/threads', [ThreadController::class, 'store']);

    // Customers
    Route::resource('customers', CustomerController::class);

    // Mailboxes
    Route::resource('mailboxes', MailboxController::class);
});
```

**Features:**
- Laravel Sanctum for API authentication
- API versioning (`/api/v1/*`)
- Rate limiting (100 requests/minute)
- JSON:API or GraphQL format
- Webhook management
- OpenAPI documentation (Swagger)

#### **5. Modernize Real-Time (WebSockets)**

**Current:** Polycast (database polling every 5-10 seconds).

**Recommendation:**

Use **Laravel Reverb** (built-in WebSocket server) or **Pusher**:

```php
// Broadcasting
broadcast(new ConversationUpdated($conversation))->toOthers();

// Frontend (Laravel Echo)
Echo.private('conversation.' + conversationId)
    .listen('ConversationUpdated', (e) => {
        updateUI(e.conversation);
    });
```

**Benefits:**
- Instant updates (no polling delay)
- Lower database load
- Better UX
- Presence channels (see who's online)

#### **6. Improve Email Architecture**

**Current:** IMAP polling, SwiftMailer (abandoned).

**Recommendation:**

**Incoming:**
- Keep IMAP polling for backward compatibility
- Add **Webhook support** (SendGrid, Mailgun, Postmark)
- Webhooks are instant (no polling delay)

**Outgoing:**
- Upgrade to **Symfony Mailer** (Laravel 9+)
- Support modern email APIs (SendGrid, Mailgun, SES)
- Better deliverability tracking
- Bounce handling

**Example:**

```php
// Webhook receiver
Route::post('/webhooks/email/inbound', [EmailWebhookController::class, 'inbound']);

// Parse webhook
public function inbound(Request $request) {
    $email = $this->parser->parse($request->all());

    $this->emailService->processIncoming(
        from: $email->from,
        subject: $email->subject,
        body: $email->body,
        attachments: $email->attachments
    );
}
```

#### **7. Add Queue System Monitoring**

**Current:** Basic queue:work with manual monitoring.

**Recommendation:**

Use **Laravel Horizon** (for Redis queues):

```
Features:
- Web dashboard for queue monitoring
- Real-time job throughput
- Failed job management
- Job metrics and trends
- Automatic process scaling
```

Or **Laravel Pulse** for application monitoring:

```
Features:
- Request performance
- Slow queries
- Exception tracking
- Job failures
- User requests
```

#### **8. Implement Observability**

**Current:** File-based logging only.

**Recommendation:**

```php
// Logging (structured)
Log::channel('stack')->info('Email sent', [
    'conversation_id' => $conversation->id,
    'to' => $customer->email,
    'duration_ms' => 234
]);

// APM Integration
Sentry::captureException($exception);

// Metrics
Prometheus::increment('emails_sent_total', [
    'mailbox' => $mailbox->id,
    'status' => 'success'
]);
```

**Tools:**
- **Error Tracking:** Sentry, Bugsnag
- **APM:** New Relic, Datadog
- **Logging:** Papertrail, Loggly, Elasticsearch
- **Metrics:** Prometheus + Grafana

#### **9. Modernize Frontend**

**Current:** Blade templates, jQuery, minimal JavaScript structure.

**Recommendation:**

**Option A: Keep Blade + Livewire (simpler)**

```blade
@livewire('conversation-view', ['id' => $conversation->id])
```

```php
class ConversationView extends Component {
    public $conversation;

    #[On('thread-added')]
    public function refresh() {
        $this->conversation->refresh();
    }

    public function render() {
        return view('livewire.conversation-view');
    }
}
```

**Benefits:**
- Minimal JavaScript
- Real-time updates
- Laravel-native
- Good UX

**Option B: API + SPA (more complex)**

```javascript
// Vue 3 or React
<template>
  <ConversationView :id="conversationId" />
</template>

// Fetch via API
const { data } = await fetch(`/api/v1/conversations/${id}`);
```

**Benefits:**
- Modern UX
- Mobile app code reuse
- Faster perceived performance

**Recommendation:** Start with Livewire, migrate to SPA if needed.

#### **10. Database Schema Improvements**

**Current:** Some denormalization (preview, threads_count cached).

**Recommendation:**

**Keep Denormalization for Performance:**
- Cached counters are good
- Add database triggers for auto-update

**Add Full-Text Search:**

```sql
ALTER TABLE conversations ADD FULLTEXT INDEX ft_subject_preview (subject, preview);

-- Query:
SELECT * FROM conversations
WHERE MATCH(subject, preview) AGAINST ('search term' IN NATURAL LANGUAGE MODE);
```

**Or use Elasticsearch/Meilisearch:**

```php
Conversation::search('urgent issue')->get();
```

**Add Soft Deletes:**

```php
class Conversation extends Model {
    use SoftDeletes;
}

// Recover deleted conversations
$conversation->restore();
```

**Add Audit Trail:**

```php
// Use spatie/laravel-activitylog (already included)
activity()
    ->performedOn($conversation)
    ->causedBy($user)
    ->log('Updated status to closed');
```

#### **11. Security Enhancements**

**Add:**

1. **Two-Factor Authentication (2FA)**

```php
// Laravel Fortify
Route::post('/user/two-factor-authentication');
```

2. **API Rate Limiting**

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()->id);
});
```

3. **Content Security Policy (CSP)**

```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'");
```

4. **CORS Configuration**

```php
'paths' => ['api/*'],
'allowed_origins' => ['https://app.example.com'],
```

5. **Input Sanitization**

```php
// Already using mews/purifier
clean($html, ['a', 'b', 'strong', 'em']); // Whitelist tags
```

6. **Subresource Integrity (SRI)**

```blade
<script src="https://cdn.example.com/script.js"
        integrity="sha384-..."
        crossorigin="anonymous"></script>
```

#### **12. Deployment & DevOps**

**Current:** Manual deployment.

**Recommendation:**

**CI/CD Pipeline (GitHub Actions):**

```yaml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run tests
        run: php artisan test
      - name: Deploy to production
        run: ./deploy.sh
```

**Containerization (Docker):**

```dockerfile
FROM php:8.3-fpm
RUN docker-php-ext-install pdo_mysql imap
COPY . /var/www
CMD ["php-fpm"]
```

**Docker Compose:**

```yaml
services:
  app:
    build: .
    volumes:
      - ./:/var/www
  mysql:
    image: mysql:8.0
  redis:
    image: redis:7
  nginx:
    image: nginx:alpine
```

**Kubernetes (for scale):**

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: freescout
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: app
        image: freescout:latest
```

**Infrastructure as Code (Terraform):**

```hcl
resource "aws_instance" "freescout" {
  ami           = "ami-..."
  instance_type = "t3.medium"
}
```

#### **13. Module System Enhancement**

**Current:** `nwidart/laravel-modules` v2.7.0

**Recommendation:**

Upgrade to latest version (v11.x for Laravel 12):

```bash
composer require nwidart/laravel-modules
```

**Add Module Marketplace:**

```
- Module discovery UI
- One-click installation
- Automatic updates
- Rating/review system
- Module dependency management
```

**Module Sandboxing:**

```php
// Isolate module code
$sandbox = new ModuleSandbox($module);
$sandbox->execute(); // Run in isolated context
```

**Module Versioning:**

```json
{
  "name": "CustomModule",
  "version": "2.0.0",
  "requires": {
    "freescout": "^1.8",
    "php": "^8.1"
  }
}
```

#### **14. Performance at Scale**

**Caching Strategy:**

```php
// Multi-layer caching
L1: APCu (in-memory, per-server)
L2: Redis (shared across servers)
L3: Database (fallback)
```

**Database Read Replicas:**

```php
'read' => [
    'host' => ['read-replica-1', 'read-replica-2'],
],
'write' => [
    'host' => ['primary'],
],
```

**CDN for Assets:**

```php
ASSET_URL=https://cdn.example.com
```

**Horizontal Scaling:**

```
Load Balancer
    ├── App Server 1
    ├── App Server 2
    └── App Server 3
        ↓
    Shared Redis
        ↓
    Database Primary + Replicas
```

**Queue Optimization:**

```php
// Separate queue workers by priority
queue:work --queue=critical,high,default,low
```

**Email Fetching Optimization:**

```php
// Parallel IMAP connections
$mailboxes->each(function($mailbox) {
    dispatch(new FetchMailbox($mailbox))->onQueue('fetch');
});
```

---

## Conclusion

FreeScout is a well-architected Laravel application with comprehensive email management capabilities. However, it has significant technical debt in the form of 279 override files that make framework upgrades challenging.

### Strengths

1. **Solid Laravel Foundation** - Built on proven framework
2. **Comprehensive Features** - Full help desk functionality
3. **Module System** - Extensible architecture
4. **Real-time Updates** - Polycast system works well
5. **Email Threading** - Robust email parsing and threading
6. **Multi-tenancy** - Multiple mailbox support
7. **Internationalization** - 18 languages supported

### Weaknesses

1. **Override System** - 279 files modifying core packages (major blocker)
2. **Large Controllers** - 3,600-line ConversationsController
3. **Minimal Tests** - Only 6 test files
4. **No REST API** - Only AJAX endpoints
5. **Abandoned Dependencies** - SwiftMailer, fzaninotto/faker
6. **Old PHP/Laravel** - Currently on Laravel 5.7, PHP 7.1+
7. **Database Polling** - Polycast uses polling instead of WebSockets

### Path Forward

**For Current Upgrade:**
- Jump to Laravel 8.x (skip 5.8, 6.x, 7.x)
- Refactor override system (279 → <50 files)
- Comprehensive testing after each upgrade
- Incremental upgrades: 8 → 9 → 10 → 11 → 12

**For Future Rebuild:**
- Eliminate override system entirely
- Implement service layer architecture
- Build REST API from start
- Add comprehensive test coverage
- Use modern real-time (WebSockets)
- Upgrade to Symfony Mailer
- Add observability (logging, metrics, APM)
- Containerize for easy deployment
- Implement CI/CD pipeline

---

**Document Version:** 1.0
**Last Updated:** 2025-11-22
**Author:** System Architecture Analysis

This document serves as a comprehensive reference for understanding FreeScout's architecture and provides a foundation for future development, whether continuing with the current codebase or rebuilding from scratch.
