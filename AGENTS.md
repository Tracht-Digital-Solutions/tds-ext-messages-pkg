# Agent notes — tds-ext-messages

The customer↔owner message thread. Read `tds-frontend-contract-pkg`'s AGENTS.md
first (extension contract) and `tds-ext-support-tickets-pkg` as the reference
full port — this extension follows the same shape.

## Architecture

- **Backend** (`php/src/MessagesModule.php`, namespace `Tds\Ext\Messages`) — extends
  `AbstractModule`. Routes: `GET /messages` (thread, marks counterpart msgs read),
  `POST /messages`, `PATCH /messages/{id}`, `GET /messages/summary` (widget unread
  count). Auth is entirely the core `UserContext`: `messages:read`/`messages:write`
  (admins bypass), scoped by `activeCompanyId()`. `author_type` = `owner` for admin,
  else `customer`. Data via the core shared `PDO`; repository in `Domain/`.
- **Ownership on edit** — admins edit any message; a customer only their own
  `author_type='customer'` rows scoped to their company (`rowCount()==0` → 404, so
  ids aren't leakable). Ported verbatim from the legacy `Message\UpdateAction`.
- **Frontend** (`src/index.ts` manifest + `pages/Index.astro` + `islands/*`) — nav
  entry, `/messages` route (`MessageThread`), and the `messages-unread` widget.

## Gotchas

- **No customer/project FK** — those entities live in another domain (auth /
  customer management), so `customer_id`/`project_id` are loose unsigned refs;
  `customer_id` = the JWT active company id (nullable = admin all-company view).
- **Migration class name AND numeric prefix are extension-unique** (shared phinxlog
  across all composed extensions): `CreateMessagesMessage`, `20260722000001`.
- **Depends on the published contract** VCS-only (Composer `type:vcs`), npm `^1.0.0`
  from GitHub Packages — never a `path` repo.
- **Extension routes are Layout-wrapped by the host** (`panelHost({ layout })`); the
  page renders only its `<section>`, never a full `<html>`.
