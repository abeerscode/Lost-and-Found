# University Lost & Found System — Project Documentation

## 1. Purpose

This is a PHP and MySQL web application for a university community to report lost or found items, search reports, communicate privately, make ownership claims, and manage the platform through a separate administrator panel.

The system supports university members with active accounts. It has separate public-user and admin login sessions, a moderation workflow for high-value items, in-app notifications, comments, direct messages, photo uploads, and audit logging for item-status changes.

## 2. Technology and runtime configuration

| Area | Implementation |
|---|---|
| Server-side language | PHP |
| Database | MySQL, accessed through PDO |
| Client-side behavior | Vanilla JavaScript |
| Styling | Shared CSS (`css/style.css`) |
| Character set | UTF-8 / `utf8mb4` |
| Database name | `lost_and_found` |
| Default database host/user/password | `localhost` / `root` / empty password |
| Application base URL | `/lost-and-found` |
| Approved registration domain | `@university.edu` |
| User-session timeout | 30 minutes of inactivity |
| Admin-session timeout | 20 minutes of inactivity |
| Maximum image upload | 5 MB |
| Allowed image formats | JPEG, PNG, WEBP, GIF |

The database connection is created in `config/db.php`. If it fails, the application returns HTTP 500 with a generic error message.

## 3. Project structure

```text
Lost & Found/
├── index.php                         Public entry page
├── config/
│   ├── config.php                    Global URL, session and upload constants
│   └── db.php                        PDO connection setup
├── database/
│   └── lost_and_found.sql            Full database schema and sample data
├── includes/
│   ├── header.php / footer.php        Shared public layout
│   ├── admin_header.php / admin_footer.php
│   ├── session.php                   Public session startup
│   ├── admin_session.php             Separate admin session startup
│   ├── auth_check.php                User access and session guard
│   ├── admin_auth_check.php          Admin access and session guard
│   └── functions.php                 Shared helpers
├── auth/                             Registration, login and account pages
├── posts/                            Item reporting, feed, search and detail pages
├── messages/                         Inbox and direct conversation pages
├── notifications/                    Notification centre
├── claims/                           Ownership claim workflow
├── admin/                            Administrator pages
├── css/style.css                     Global visual styles
├── js/main.js                        Flash-message behavior
├── js/search.js                      Feed search/filter behavior
├── js/claims.js                      AJAX comments and claim confirmation
└── uploads/                          User-uploaded item photos
```

## 4. Roles and session model

### Public user

A public user can register with the configured university email domain, log in, update their profile, create and manage their own posts, search all posts, comment, message other users, make claims, and receive notifications.

### Admin

An admin is a `users` record whose `role` is `admin`. Admin access requires a distinct login at `admin/login.php`; a public login with an admin-role account does **not** unlock admin actions.

### Separate sessions

The public site uses PHP’s default session cookie. The admin panel uses a separate `LNF_ADMIN_SESSID` cookie. Therefore a browser can be logged in to the public site and the admin console simultaneously without the sessions replacing one another.

Both protected areas re-check the account’s current status on every request. A suspended or banned public user is logged out immediately; an admin who is no longer active or no longer has the `admin` role is also logged out immediately.

## 5. Data model

### `users`

Stores accounts: `id`, `name`, `university_id`, `email` (unique), `password_hash`, `role` (`user` or `admin`), `phone`, `department`, `account_status` (`active`, `suspended`, `banned`), and `created_at`.

### `categories`

Stores item categories: `id`, `name` (unique), and `is_high_value_default`. The supplied categories are Electronics, Documents, Bags, Keys, Accessories, Clothing, and Others. Electronics and Documents are high-value by default.

### `posts`

Stores lost/found reports: `id`, `user_id`, `type` (`lost` or `found`), `title`, `description`, `category_id`, `location`, `item_datetime`, `photo_url`, `status` (`open`, `claimed`, `resolved`), `is_high_value`, `created_at`, and `updated_at`.

Posts belong to a user and category. Deleting a user cascades to their posts. The schema includes a full-text index on `title` and `description`, although the current search endpoint uses `LIKE` queries.

### `claims`

Stores requests to claim an item: `id`, `post_id`, `claimant_id`, `proof_description`, `status` (`pending`, `approved`, `rejected`), `verified_by_admin`, `created_at`, and `updated_at`.

### `comments`

Stores public post comments: `id`, `post_id`, `user_id`, `message`, and `created_at`.

### `messages`

Stores private messages: `id`, `sender_id`, `receiver_id`, optional `post_id`, `content`, `is_read`, and `created_at`.

### `notifications`

Stores in-app notifications: `id`, `user_id`, `type`, `message`, optional `link`, `is_read`, and `created_at`.

### `password_resets`

Stores password-reset tokens: `id`, `user_id`, unique `token`, `expires_at`, `used`, and `created_at`.

### `post_status_log`

Audit trail for item status: `id`, `post_id`, optional `old_status`, `new_status`, optional `changed_by`, and `created_at`.

## 6. Complete functional workflows

### Account creation and access

1. A visitor registers with name, optional university ID, university email, optional phone, optional department, password, and confirmation.
2. The email must be valid and end in `@university.edu`; passwords must be at least eight characters and match.
3. Existing email addresses cannot be registered again.
4. Login verifies the password and requires an active account. On success, the session ID is regenerated and the user is sent to the item feed.
5. Logout clears the public session and returns to login with a flash message.
6. Profile editing changes name, university ID, phone, and department. Email is displayed but cannot be edited on the profile page.

### Password reset

1. The user submits an email address on the forgotten-password page.
2. The page always shows the same success message, preventing account enumeration.
3. If the account exists, a random 64-character hexadecimal token is stored with a one-hour expiry.
4. This project has no SMTP integration. In demo mode, the reset link is displayed on screen.
5. A valid unused, unexpired token permits a new password of at least eight characters. On success, the password hash is updated and that reset token is marked used.

### Creating a lost/found report

1. A logged-in user chooses `lost` or `found` (the default is `found`, unless `?type=lost` is used).
2. Required inputs are title, description, category, location, and date. Time is optional and defaults to midnight.
3. Optional photos are MIME-checked, size-checked, randomly named, and stored under `uploads/`.
4. A report becomes `open` when created and a corresponding `post_status_log` entry is added.
5. A report is high-value when the user checks the high-value box **or** its category is high-value by default.

### Feed, filtering, and search

The authenticated feed loads results through JavaScript from `posts/search.php`. It supports:

- Keyword search across title and description
- Type: lost or found
- Category
- Report status: open, claimed, or resolved
- Location substring
- Date-from and date-to filters, based on item date/time
- Sorting: newest first, oldest first, or item date nearest to today
- Pagination at 12 results per page

The endpoint returns JSON with each item’s metadata, shortened description, image URL where available, human-readable post age, the item-detail URL, total count, current page, per-page value, and total pages. Search input is debounced by 350 milliseconds.

### Viewing and managing a post

The item detail page shows the type, status, high-value indicator, title, photo, category, location, date/time, owner, time posted, description, comments, and relevant claim actions.

Only the owner may edit, delete, or set the post status to `open`, `claimed`, or `resolved`. Deleting a post also removes its uploaded image if it exists. Every manual status change is logged.

### Comments

Any signed-in user may add a non-empty comment to a post. Comments are stored with the author and time. If another user comments on a post, the owner receives a notification.

`js/claims.js` submits comments asynchronously when JavaScript is available; it appends the new comment and increments the visible comment count without reloading the page. The PHP page remains a non-JavaScript fallback.

### Ownership claims

1. A user cannot claim their own post.
2. A claim needs a non-empty description of proof of ownership.
3. A user cannot have more than one pending claim for the same post.
4. Adding the first claim to an open post changes the post to `claimed` and logs that status change.
5. The post owner receives an in-app claim-request notification.
6. The post owner sees all claims; a non-owner sees only their own claim(s).

#### Standard-value item claim

The post owner can approve or reject a pending claim. Approval changes the post status to `resolved`, logs the change, and automatically rejects every other pending claim on the same post. The claimant receives an approval/rejection notification.

#### High-value item claim

The post owner cannot decide a high-value claim. It appears in the admin verification queue instead. An active admin approves or rejects it; approval stores the admin ID in `verified_by_admin`, resolves the post, logs the change, and rejects competing pending claims. The claimant is notified of the admin decision.

### Private messaging

Users can begin a conversation with another user from an item page. A conversation may optionally carry the originating `post_id`.

The inbox displays the most recent message for each conversation partner. Opening a conversation marks incoming messages from that partner as read. Sending a non-empty message stores it and creates a notification for the recipient. Users cannot create a conversation with themselves; invalid or unknown recipients return an error.

### Notifications

The header displays unread counts for messages and notifications. The notification page marks all of the current user’s unread notifications as read, then displays their latest 50 notifications in descending date order. Notification types currently created by the code are `comment`, `message`, `claim_request`, `claim_approved`, and `claim_rejected`.

## 7. Public routes and endpoints

| Route | Access | Function |
|---|---|---|
| `/index.php` | Public | Landing page; sends logged-in users to feed through login page flow |
| `/auth/register.php` | Public | Register a university member |
| `/auth/login.php` | Public | Sign in an active user |
| `/auth/logout.php` | Logged-in user | End public session |
| `/auth/forgot_password.php` | Public | Create reset token and display demo reset link |
| `/auth/reset_password.php?token=…` | Public with valid token | Set a new password |
| `/auth/profile.php` | Logged-in user | View/update profile data |
| `/posts/feed.php` | Logged-in user | Search/filter item-feed UI |
| `/posts/search.php` | Logged-in user | JSON search and pagination endpoint |
| `/posts/create.php` | Logged-in user | Create lost/found report and upload photo |
| `/posts/view.php?id=…` | Logged-in user | View item, claims, comments, and actions |
| `/posts/edit.php?id=…` | Post owner | Edit existing report and optionally replace photo |
| `/posts/update_status.php` | Post owner, POST | Set report status and audit-log it |
| `/posts/delete.php` | Post owner, POST | Delete report and its local image |
| `/claims/create_claim.php?post_id=…` | Logged-in non-owner | Submit a proof-of-ownership claim |
| `/claims/respond_claim.php` | Post owner, POST | Approve/reject a non-high-value claim |
| `/messages/inbox.php` | Logged-in user | Conversation list |
| `/messages/conversation.php?with=…&post_id=…` | Logged-in user | Read/send direct messages |
| `/notifications/list.php` | Logged-in user | Mark notifications read and list latest 50 |

## 8. Administrator routes and endpoints

| Route | Access | Function |
|---|---|---|
| `/admin/login.php` | Public | Start separate admin session for an active admin-role account |
| `/admin/logout.php` | Admin | End only the admin session |
| `/admin/dashboard.php` | Active admin | Counts posts, lost/found reports, resolved rate, users, pending claims, high-value pending claims, and posts by category |
| `/admin/manage_posts.php` | Active admin | List every post and remove a post with its image |
| `/admin/manage_users.php` | Active admin | List users; suspend, ban, or reactivate other users; cannot change own status |
| `/admin/manage_categories.php` | Active admin | Add categories, mark them high-value by default, and delete unused categories |
| `/claims/admin_verify_claim.php` | Active admin | List pending high-value claims |
| `/claims/admin_respond_claim.php` | Active admin, POST | Approve/reject high-value claims and record reviewer |

## 9. Shared helper-function reference

`includes/functions.php` provides the application-wide functions below.

| Function | Responsibility |
|---|---|
| `e()` | Escapes output with `htmlspecialchars` using UTF-8 and quoted attributes |
| `redirect()` | Redirects using the configured base URL and terminates execution |
| `is_logged_in()` / `current_user_id()` | Read public-user session state and ID |
| `account_role_is_admin()` | Display-only check for public-session admin role; never gates admin actions |
| `is_admin_logged_in()` / `current_admin_id()` | Read separate admin-session state and ID |
| `flash_set()` / `flash_get()` | Store and retrieve one-time success/error messages |
| `csrf_token()` / `csrf_field()` / `csrf_verify()` | Create, render, and validate CSRF protection for POST forms |
| `is_university_email()` | Checks the allowed email-domain suffix |
| `time_ago()` | Formats a timestamp as “just now”, minutes, hours, days, or date |
| `status_badge()` | Converts supported status values to reusable badge markup |
| `handle_photo_upload()` | Validates, randomly names, and stores accepted uploaded images |
| `log_status_change()` | Writes a post-status audit record |
| `create_notification()` | Stores an in-app notification |
| `unread_notification_count()` | Counts unread notifications for a user |
| `unread_message_count()` | Counts unread received messages for a user |

## 10. Front-end behavior and shared presentation

### Shared layouts

`includes/header.php` and `includes/footer.php` wrap public pages. The header links to the feed, new-report page, inbox, notifications, profile, logout, and, for admin-role users, the separate admin login. It also fetches unread counts.

`includes/admin_header.php` and `includes/admin_footer.php` wrap administrator pages and retain their independent session context.

### JavaScript

- `js/main.js`: fades and removes visible flash messages after five seconds.
- `js/search.js`: serializes filters, requests the JSON search endpoint, renders cards from a `<template>`, shows total results, handles empty/error states, and creates pagination buttons.
- `js/claims.js`: posts comments using `fetch`, renders successful comments without page reload, uses browser alerts for comment failures, and requests confirmation before rejecting a claim.

### CSS

`css/style.css` defines the shared layout, forms, cards, item feed, details, claims, comments, messages, notifications, admin layout, tables, badges, and responsive rules. It is presentation-only; it does not contain business logic.

## 11. Security and integrity controls implemented

- PDO prepared statements are used for database queries that take user input.
- Database PDO emulated prepares are disabled.
- Output is HTML-escaped through `e()` in templates.
- State-changing forms use CSRF tokens.
- User and admin sessions are regenerated on login.
- User and admin areas have separate session cookies.
- Session inactivity is enforced.
- Account status and admin role are rechecked on protected requests.
- Authorization is checked before editing/deleting a post, deciding claims, or performing admin actions.
- Public users cannot decide high-value claims.
- Uploaded photos are size- and MIME-validated, given random filenames, and stored outside SQL data.
- Passwords are stored and checked through PHP password hashing APIs.
- Password reset pages avoid exposing whether an email exists.
- Item-status changes are audit logged.

## 12. Important implementation notes and current boundaries

- There is no SMTP/email service. Password-reset links are intentionally displayed in the browser in demo mode.
- There is no external API, payment flow, map service, real-time socket messaging, or file-storage provider.
- Notifications are in-app only; they are not sent by email or push service.
- Search requires login and uses `LIKE`, not the database full-text index currently defined in the schema.
- Admin moderation removes posts but does not include a restore/archive workflow.
- User profile management does not include changing email or password directly; password changes use the reset flow.
- Category deletion is blocked when a category is still referenced by posts.
- The schema includes two sample accounts. The SQL file documents their initial password as `Password123!`; change it before any real deployment.

## 13. Complete source-file index

| Location | Files and responsibility |
|---|---|
| Root | `index.php` — public landing page; `.gitignore` — excludes local/temporary files; `uploads/.gitkeep` — preserves upload directory in version control |
| Configuration | `config/config.php` — application constants; `config/db.php` — PDO database connection |
| Shared includes | `includes/session.php`, `includes/admin_session.php`, `includes/auth_check.php`, `includes/admin_auth_check.php`, `includes/functions.php`, `includes/header.php`, `includes/footer.php`, `includes/admin_header.php`, `includes/admin_footer.php` |
| Authentication | `auth/register.php`, `auth/login.php`, `auth/logout.php`, `auth/forgot_password.php`, `auth/reset_password.php`, `auth/profile.php` |
| Posts | `posts/feed.php`, `posts/search.php`, `posts/create.php`, `posts/view.php`, `posts/edit.php`, `posts/update_status.php`, `posts/delete.php` |
| Messaging | `messages/inbox.php`, `messages/conversation.php` |
| Notifications | `notifications/list.php` |
| Claims | `claims/create_claim.php`, `claims/respond_claim.php`, `claims/admin_verify_claim.php`, `claims/admin_respond_claim.php` |
| Administration | `admin/login.php`, `admin/logout.php`, `admin/dashboard.php`, `admin/manage_posts.php`, `admin/manage_users.php`, `admin/manage_categories.php` |
| Client assets | `css/style.css`; `css/responsive.css` (responsive navigation refinement where present); `js/main.js`; `js/search.js`; `js/claims.js` |
| Database | `database/lost_and_found.sql` — schema, categories, and sample accounts |
| Tooling metadata | `.claude/settings.local.json` — local Claude-tool setting; it is not part of the application runtime |

## 14. Setup checklist

1. Place the project in the web server’s document root using the folder name `lost-and-found`, or update `BASE_URL` in `config/config.php` to match the deployed folder.
2. Create/import the `lost_and_found` database using `database/lost_and_found.sql`.
3. Set the database values in `config/db.php` for the local/server environment.
4. Ensure PHP can write to the `uploads/` directory.
5. Browse to `http://localhost/lost-and-found/`.
6. Change the sample credentials and replace the demo password-reset behavior before production use.
