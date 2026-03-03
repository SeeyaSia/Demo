# Text Formats and CKEditor 5 — Configuration Reference

## Purpose

Developer guide for Drupal text formats and CKEditor 5: what formats exist, how they control allowed HTML, how the editor toolbar is configured, and Canvas-specific locked formats. Text formats are the security layer between user input and rendered HTML.

---

## How Text Formats Work

Text formats define:
1. **Allowed HTML tags** — Which tags survive when content is saved
2. **Filters** — Processing applied to content (e.g., lazy loading images, converting URLs to links)
3. **Editor** — Which WYSIWYG editor (CKEditor 5) configuration to use
4. **Access** — Which roles can use the format (controlled by permissions)

Every text field that uses a formatted text type (`text_long`, `text_with_summary`) is associated with a text format. The format determines what HTML the editor can produce and what HTML is allowed in the output.

---

## Standard Text Formats

Drupal provides these text formats, ordered from most restrictive to least:

| Format | Machine name | Editor | Purpose |
|--------|-------------|--------|---------|
| **Restricted HTML** | `restricted_html` | None | Minimal HTML for untrusted users |
| **Basic HTML** | `basic_html` | CKEditor 5 | General content editing |
| **Full HTML** | `full_html` | CKEditor 5 | Trusted users, admin content |
| **Plain Text** | `plain_text` | None | No HTML at all |

### Restricted HTML

- **No WYSIWYG editor** — raw text input
- **Minimal tags allowed**: `<a> <em> <strong> <cite> <blockquote> <code> <ul> <ol> <li> <dl> <dt> <dd>`
- **Use case**: Anonymous users, untrusted input
- **Role access**: Typically anonymous + authenticated

### Basic HTML

- **CKEditor 5** with standard toolbar
- **Allowed HTML**: `<br> <p> <h2-h6> <cite> <dl> <dt> <dd> <a> <blockquote> <ul> <ol> <strong> <em> <code> <li> <img>`
- **Toolbar**: Bold, Italic, Link, Bulleted List, Numbered List, Block Quote, Insert Image, Heading (H2-H6), Code, Source Editing
- **Filters**: HTML filter, align, caption, image security, lazy loading
- **Image upload**: Enabled (stores in `inline-images` directory)
- **Use case**: Content editors creating articles, pages

### Full HTML

- **CKEditor 5** with extended toolbar
- **No HTML restrictions** — all tags allowed
- **Toolbar**: Everything in Basic HTML plus Strikethrough, Superscript, Subscript, Remove Format, Insert Table, Horizontal Line, Code Block (with syntax highlighting)
- **Code Block languages**: C, C#, C++, CSS, Diff, HTML, Java, JavaScript, PHP, Python, Ruby, TypeScript, XML
- **Image upload**: Enabled
- **Use case**: Administrators, trusted content with tables, code blocks, etc.

### Plain Text

- **No editor** — simple textarea
- **All HTML escaped** — tags displayed as text, not rendered
- **Use case**: Machine-readable fields, comments (if restricted)

---

## Canvas Text Formats (Do Not Modify)

Canvas provides two locked text formats for its component editor:

| Format | Machine name | Purpose |
|--------|-------------|---------|
| Canvas Inline | `canvas_html_inline` | Inline text editing in Canvas components |
| Canvas Block | `canvas_html_block` | Block-level text editing in Canvas components |

**`canvas_html_inline`** — Allows only `<strong> <em> <u> <a href>`. Used for simple inline text props.

**`canvas_html_block`** — Allows block-level elements. Used for rich text areas in Canvas components.

**⚠️ Do not modify these formats.** They are managed by the Canvas module and may be overwritten during updates. Canvas depends on their specific configuration for the component editor.

---

## CKEditor 5 Configuration

Each text format with a WYSIWYG editor has a companion editor config:
- Text format: `filter.format.basic_html.yml`
- Editor config: `editor.editor.basic_html.yml`

### Editor toolbar configuration

The toolbar is defined as a list of items in the editor config:

```yaml
# editor.editor.basic_html.yml (simplified)
editor: ckeditor5
settings:
  toolbar:
    items:
      - bold
      - italic
      - link
      - bulletedList
      - numberedList
      - blockQuote
      - drupalInsertImage
      - heading
      - code
      - sourceEditing
  plugins:
    ckeditor5_heading:
      enabled_headings:
        - heading2
        - heading3
        - heading4
        - heading5
        - heading6
    ckeditor5_sourceEditing:
      allowed_tags:
        - '<cite>'
        - '<dl>'
        - '<dt>'
        - '<dd>'
```

### Available CKEditor 5 toolbar items

| Item | Plugin | Description |
|------|--------|-------------|
| `bold` | Core | Bold text |
| `italic` | Core | Italic text |
| `strikethrough` | Core | Strikethrough |
| `superscript` / `subscript` | Core | Super/subscript |
| `link` | Core | Insert/edit links |
| `bulletedList` / `numberedList` | List | Lists |
| `blockQuote` | Core | Block quotes |
| `heading` | Heading | Heading levels |
| `code` | Code | Inline code |
| `codeBlock` | Code Block | Code blocks with syntax highlighting |
| `drupalInsertImage` | Drupal Image | Insert images (via upload) |
| `insertTable` | Table | Insert tables |
| `horizontalLine` | Core | Horizontal rule |
| `removeFormat` | Core | Clear formatting |
| `sourceEditing` | Source Editing | Raw HTML editing |

### Image upload settings

When image upload is enabled for a format:

```yaml
image_upload:
  status: true
  scheme: public              # public:// or private://
  directory: inline-images    # Subdirectory under files/
  max_size: ''                # Empty = use PHP upload_max_filesize
  max_dimensions:
    width: null               # No width limit
    height: null              # No height limit
```

---

## Format Permissions

Access to text formats is controlled by permissions:
- `use text format basic_html`
- `use text format full_html`
- `use text format restricted_html`

Grant format permissions to roles:

```php
$role = \Drupal\user\Entity\Role::load('content_editor');
$role->grantPermission('use text format basic_html');
$role->grantPermission('use text format full_html');
$role->save();
```

**Security note:** Full HTML allows arbitrary HTML including `<script>` tags. Only grant to trusted roles. Basic HTML is safe for content editors — it strips dangerous tags.

---

## Creating Custom Text Formats

For specialized needs (e.g., a format that allows embedded media but not images):

```php
use Drupal\filter\Entity\FilterFormat;

FilterFormat::create([
  'format' => 'custom_format',
  'name' => 'Custom Format',
  'weight' => 5,
  'filters' => [
    'filter_html' => [
      'status' => TRUE,
      'settings' => [
        'allowed_html' => '<p> <br> <strong> <em> <a href> <ul> <ol> <li> <h2> <h3>',
      ],
    ],
    'filter_html_image_secure' => [
      'status' => TRUE,
    ],
  ],
])->save();
```

---

## Webform Text Format

Webform provides its own text format (`webform_default`) for form element markup. It has CKEditor 5 enabled with a configuration appropriate for form descriptions and confirmation messages.

---

## Configuration Files

| File pattern | Contents |
|-------------|----------|
| `config/<site>/filter.format.*.yml` | Text format definitions (allowed HTML, filters) |
| `config/<site>/editor.editor.*.yml` | CKEditor 5 editor configurations (toolbar, plugins) |

---

## Gotchas

- **Canvas formats are locked.** Modifying `canvas_html_inline` or `canvas_html_block` may break the Canvas editor. Canvas module updates may overwrite your changes.
- **Format determines allowed output, not just input.** Even if a user pastes HTML with `<script>` tags, the format's filter strips them on save. This is the security layer.
- **CKEditor 5 ≠ CKEditor 4.** Drupal 10+ uses CKEditor 5 exclusively. CKEditor 4 configs don't apply.
- **Source Editing tags must match allowed HTML.** If you add a tag to CKEditor 5's Source Editing plugin but don't allow it in `filter_html`, the tag will be stripped on save.
- **Format fallback.** If a field's format is changed to one that doesn't allow existing tags, those tags are stripped on the next save.
- **Image upload is per-format.** Each format independently controls whether image uploads are allowed and where they're stored.

---

## Related Documentation

| Document | Relevance |
|---|---|
| `configuration/users-and-roles.md` | Format permissions per role |
| `canvas/canvas-system-overview.md` | Canvas locked formats |
| `integrations/site-services.md` | Webform text format |
