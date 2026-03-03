# Users and Roles — Permissions, Access Control, and Security

## Purpose

Developer guide for configuring Drupal user roles, permissions, account settings, login security, and access control. Covers how roles interact with content moderation, Canvas permissions, and Webform access.

**Key principle:** Roles define what users can do. Permissions are additive — users with multiple roles get the union of all permissions.

---

## User Roles

Drupal ships with three built-in roles. Custom roles can be added for editorial workflows.

### Built-in roles

| Role | Machine name | `is_admin` | Purpose |
|------|-------------|------------|---------|
| Anonymous | `anonymous` | No | Unauthenticated visitors |
| Authenticated | `authenticated` | No | Base role for all logged-in users |
| Administrator | `administrator` | Yes | Full access to all site features |

**Anonymous** — The implicit role for visitors who aren't logged in. Grant minimal permissions: view published content, view media, use restricted text formats.

**Authenticated** — The base role for any logged-in user. Permissions here apply to ALL authenticated users, including administrators. Typical: commenting, using text formats, accessing shortcuts.

**Administrator** — Has `is_admin: true`, which grants ALL permissions implicitly. The `permissions` array in config is empty because the admin flag bypasses permission checks.

### Creating custom roles

Custom roles are common for editorial workflows:

```php
use Drupal\user\Entity\Role;

if (!Role::load('content_editor')) {
  Role::create([
    'id' => 'content_editor',
    'label' => 'Content Editor',
    'weight' => 2,
    'is_admin' => FALSE,
  ])->save();
}
```

### Granting permissions

```php
$role = Role::load('content_editor');

// Content management
$role->grantPermission('create article content');
$role->grantPermission('edit own article content');
$role->grantPermission('delete own article content');

// Admin access
$role->grantPermission('access content overview');
$role->grantPermission('access administration pages');
$role->grantPermission('access toolbar');
$role->grantPermission('view the administration theme');

// Revisions
$role->grantPermission('view all revisions');
$role->grantPermission('revert all revisions');

// Taxonomy
$role->grantPermission('create terms in tags');
$role->grantPermission('edit terms in tags');

// URL management
$role->grantPermission('administer url aliases');
$role->grantPermission('create url aliases');

$role->save();
```

### Common permission groups

| Permission category | Key permissions | Typical roles |
|-------------------|----------------|---------------|
| **Content viewing** | `access content`, `view media` | Anonymous, Authenticated |
| **Content creation** | `create TYPE content`, `edit own TYPE content` | Content Editor |
| **Content administration** | `edit any TYPE content`, `delete any TYPE content` | Senior Editor, Admin |
| **Taxonomy** | `create terms in VOCAB`, `edit terms in VOCAB` | Content Editor |
| **Administration** | `access toolbar`, `access administration pages` | Content Editor, Admin |
| **User management** | `administer users`, `administer permissions` | Admin |
| **URL aliases** | `create url aliases`, `administer url aliases` | Content Editor |
| **Text formats** | `use text format FORMAT` | Per role as needed |
| **Media** | `view media`, `create media`, `edit own media` | Content Editor |

### Permission naming conventions

Drupal permission machine names follow patterns:
- `create TYPE content` — Create nodes of a content type
- `edit own TYPE content` / `edit any TYPE content` — Edit nodes
- `delete own TYPE content` / `delete any TYPE content` — Delete nodes
- `create terms in VOCAB` — Create taxonomy terms
- `use text format FORMAT` — Use a text format
- `view media` / `create media` / `edit own media` — Media operations

---

## User Account Settings (`user.settings.yml`)

Controls registration, account lifecycle, and email behavior.

### Key settings

| Setting | Values | Purpose |
|---------|--------|---------|
| `register` | `admin_only`, `visitors`, `visitors_admin_approval` | Who can create accounts |
| `verify_mail` | `true`/`false` | Require email verification |
| `cancel_method` | `user_cancel_block`, `user_cancel_delete`, `user_cancel_reassign` | What happens on cancellation |
| `password_reset_timeout` | Seconds (default `86400` = 24h) | Password reset link lifetime |
| `password_strength` | `true`/`false` | Enforce password complexity |

### Registration modes

| Mode | Behavior | Use case |
|------|----------|----------|
| `admin_only` | Only admins create accounts | Controlled-access sites, intranets |
| `visitors` | Anyone can register | Community sites, open registration |
| `visitors_admin_approval` | Anyone registers, admin approves | Moderated community sites |

### Email notifications (`notify.*` keys)

| Key | Default | Sends email when... |
|-----|---------|---------------------|
| `register_admin_created` | `true` | Admin creates an account |
| `register_no_approval_required` | `true` | Self-registration succeeds |
| `register_pending_approval` | `true` | Registration awaits approval |
| `password_reset` | `true` | Password reset requested |
| `status_activated` | `true` | Account activated |
| `status_blocked` | `false` | Account blocked |
| `status_canceled` | `false` | Account cancelled |
| `cancel_confirm` | `true` | Cancellation confirmation |

### Configuring via capability script

```php
$config = \Drupal::configFactory()->getEditable('user.settings');
$config->set('register', 'admin_only');
$config->set('verify_mail', TRUE);
$config->set('cancel_method', 'user_cancel_block');
$config->set('password_reset_timeout', 86400);
$config->set('password_strength', TRUE);
$config->save();
```

---

## Login Flood Control (`user.flood.yml`)

Protects against brute-force login attacks.

### Settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `ip_limit` | `50` | Max login attempts per IP per window |
| `ip_window` | `3600` (1 hour) | IP rate-limit window |
| `user_limit` | `5` | Max failed attempts per user per window |
| `user_window` | `21600` (6 hours) | User rate-limit window |
| `uid_only` | `false` | Rate-limit by IP+user (false) or user only (true) |

**Behavior:** After exceeding limits, login attempts are blocked until the time window expires. Administrators can unblock at `/admin/config/people/accounts` or via Drush:

```bash
ddev drush user:flood-unblock           # Unblock all
ddev drush user:flood-unblock user@example.com  # Unblock specific user
```

---

## Content Moderation and Roles

The editorial workflow (`workflows.workflow.editorial`) provides draft → published → archived states. Roles determine who can perform which transitions.

### Workflow permissions

| Permission | Allows |
|-----------|--------|
| `use editorial transition create_new_draft` | Create drafts from any state |
| `use editorial transition publish` | Publish content |
| `use editorial transition archive` | Archive published content |
| `use editorial transition archived_draft` | Restore archived content to draft |
| `use editorial transition archived_published` | Restore archived content to published |

### Typical role assignments

| Role | Transitions | Rationale |
|------|------------|-----------|
| Content Editor | `create_new_draft`, `publish` (own content) | Create and publish their work |
| Senior Editor | All transitions | Full editorial control |
| Administrator | All transitions (via `is_admin`) | Implicit full access |

### Assigning workflow to content types

Workflow must be assigned via capability script — editing the config YAML directly is fragile:

```php
$workflow = \Drupal::entityTypeManager()
  ->getStorage('workflow')
  ->load('editorial');

$type_plugin = $workflow->getTypePlugin();
$type_plugin->addEntityTypeAndBundle('node', 'article');
$workflow->save();
```

After assignment, export config: `ddev drush cex -y`

---

## Canvas Access

Canvas has its own permission model for editing pages, regions, and templates.

| Canvas entity | Who can edit | Notes |
|--------------|-------------|-------|
| **Canvas Pages** | Users with Canvas permissions | Per-page access may vary |
| **Canvas PageRegions** | Administrators | Site-wide chrome (header, footer) |
| **Canvas ContentTemplates** | Administrators | Entity rendering templates |
| **Canvas Components** | N/A (config, not per-user) | Enabled/disabled via `status` field |

Canvas permissions are module-level permissions (not configuration entities). Grant them like any other Drupal permission:

```php
$role->grantPermission('administer canvas pages');
$role->grantPermission('edit canvas pages');
$role->save();
```

See `canvas/canvas-system-overview.md` for the complete Canvas permission model.

---

## Webform Submission Access

Webform has its own per-form access control, separate from Drupal's node access system.

### Access configuration structure

Each webform has access settings for these operations:
- `create` — Who can submit the form
- `view_any` / `view_own` — Who can view submissions
- `update_any` / `update_own` — Who can edit submissions
- `delete_any` / `delete_own` — Who can delete submissions
- `administer` — Who can configure the form
- `test` — Who can test the form
- `configuration` — Who can change form settings

### Typical configuration

```yaml
# In webform.webform.*.yml
access:
  create:
    roles:
      - anonymous
      - authenticated
    users: []
    permissions: []
  view_any:
    roles: []
    users: []
    permissions: []
  # ... other operations
```

**Common pattern:** Allow anonymous + authenticated to create submissions. Restrict viewing/editing to administrators (who have implicit access via `is_admin`).

---

## Configuration Files

| File | Contents |
|------|----------|
| `config/<site>/user.role.*.yml` | Role definitions and permissions |
| `config/<site>/user.settings.yml` | Registration, cancellation, email settings |
| `config/<site>/user.flood.yml` | Login flood control settings |
| `config/<site>/workflows.workflow.editorial.yml` | Editorial workflow (states, transitions, assigned types) |
| `config/<site>/webform.webform.*.yml` | Per-webform access configuration |

---

## Gotchas

- **`is_admin: true` bypasses all permission checks.** The administrator role doesn't list individual permissions — it has implicit access to everything. Don't test permissions as admin.
- **Workflow not assigned ≠ workflow not configured.** The workflow config can exist with states and transitions defined, but content moderation won't work until the workflow is assigned to specific content types.
- **Permissions are per content type.** `create article content` and `create page content` are separate permissions. When adding a new content type, remember to grant permissions to relevant roles.
- **Canvas permissions are module permissions.** They're granted via `user.role.*.yml` like any permission, but they're defined by the Canvas module, not by config entities.
- **Webform access is per-form.** Each webform has its own access settings. Module-level Webform permissions also exist but are separate from per-form access.
- **Role weight affects display order only.** It doesn't affect permission priority (permissions are always additive).

---

## Related Documentation

| Document | Relevance |
|---|---|
| `data-model/entity-types.md` | User entity fields and roles |
| `data-model/content-types.md` | Content types that need role permissions |
| `canvas/canvas-system-overview.md` | Canvas permission model |
| `integrations/site-services.md` | Webform access configuration |
| `global/drush-config-workflow.md` | Config export after permission changes |
