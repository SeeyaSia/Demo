# Tier 1 SDC Worked Example — Hero Carousel

## Purpose

Step-by-step walkthrough of creating a Tier 1 SDC component from design intent through to Canvas integration. The Hero Carousel is the project's **gold-standard reference** — all new SDCs should follow this pattern. For the generic file template and conventions, see `component-strategy.md` → "SDC Gold Standard Pattern". This document tells the **story** of how one component was designed, built, and wired into Canvas.

> **Companion doc:** `canvas-build-example.md` covers Tier 2 primitive composition (building a page from Wrapper, Heading, Card, etc.). This doc covers Tier 1 SDC creation. Both patterns coexist on real pages.

---

## Phase 0: Design Analysis — Should This Be an SDC?

### The Design Intent

The design calls for a full-viewport hero section with:
- Multiple background images that crossfade between each other
- An optional overlay heading and subheading
- A bottom info bar showing the current slide's title and stat
- Prev/next navigation controls with slide counter
- Auto-rotation with pause-on-hover

### The Decision

Run through the decision reference from `component-strategy.md`:

| Signal | Applies? | Reason |
|--------|----------|--------|
| Appears on 2+ pages? | Yes | Hero on homepage, could appear on landing pages |
| Custom CSS beyond presets/utilities? | **Yes** | Full-bleed viewport, crossfade transitions, z-index layering, gradient overlays, absolute positioning |
| Client-side behavior? | **Yes** | Auto-rotation, slide transitions, keyboard/click navigation |
| Enforces brand consistency? | Yes | Gradient colors, typography, bar styling must match brand |

**Verdict: Create a Tier 1 SDC.** This pattern cannot be composed from Tier 2 primitives — it needs custom CSS, JavaScript behavior, and a specific DOM structure.

### Parent + Child Decomposition

The carousel has two distinct concerns:
1. **The container** — owns the viewport, gradient, content overlay, info bar, navigation, and auto-rotation behavior
2. **Each slide** — provides a background image and metadata (title, stat)

This maps to a **parent + child SDC pair**: `hero-carousel` (container with a `slides` slot) + `carousel-slide` (item dropped into that slot).

> **Rule of thumb:** If a component has a repeatable item (slide, tab, accordion item), split it into parent + child SDCs. The parent owns the slot; the child is the repeatable item placed into that slot via the Canvas editor.

---

## Phase 1: File Structure

Create the component directory inside the theme:

```
web/themes/custom/alchemize_forge/components/
├── hero-carousel/
│   ├── hero-carousel.component.yml    # Metadata: props, slots
│   ├── hero-carousel.twig             # Template
│   ├── hero-carousel.scss             # Styles
│   ├── hero-carousel.js               # Auto-rotation + navigation behavior
│   └── hero-carousel.css              # Compiled by Webpack (not hand-edited)
│
└── carousel-slide/
    ├── carousel-slide.component.yml   # Metadata: props (title, stat, image)
    ├── carousel-slide.twig            # Template
    ├── carousel-slide.scss            # Styles
    └── carousel-slide.css             # Compiled by Webpack
```

**Naming convention:** kebab-case directory and file names matching the component machine name. All files in the directory share the same base name.

---

## Phase 2: Component YAML — Props and Slots

### hero-carousel.component.yml

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/modules/sdc/src/metadata.schema.json

name: Hero carousel
description: A full-viewport hero carousel with crossfade transitions, auto-rotation, and a bottom info bar. Add Carousel Slide components into the slides slot.
type: component
status: experimental
group: Canvas Bootstrap

libraryOverrides:
  dependencies:
    - core/drupal

props:
  type: object
  properties:
    heading:
      type: string
      title: Heading
      description: Optional overlay heading text.
    subheading:
      type: string
      title: Subheading
      description: Optional overlay subheading text.
    auto_rotate_interval:
      type: integer
      title: Auto-rotate interval (ms)
      description: Milliseconds between auto-transitions. Set to 0 to disable.
      default: 5000

slots:
  slides:
    title: Slides
    description: Add Carousel Slide components here. Each slide provides a background image, title, and stat.
```

**Key conventions applied:**
- `title` on every prop — **required** for Canvas editor visibility (without it, the prop won't appear in the sidebar)
- `description` explains what the prop does and how to use it
- `default` provides a sensible starting value
- `libraryOverrides.dependencies` includes `core/drupal` because the JS uses `Drupal.behaviors`
- The `slides` slot uses a clear description telling editors exactly what to drop in

### carousel-slide.component.yml

```yaml
$schema: https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/modules/sdc/src/metadata.schema.json

name: Carousel slide
description: A single slide for the Hero Carousel. Provides a full-bleed background image with title and stat metadata exposed via data attributes for the parent carousel to read.
type: component
status: experimental
group: Canvas Bootstrap

props:
  type: object
  required:
    - title
  properties:
    title:
      type: string
      title: Title
      description: Slide title displayed in the carousel info bar.
      examples:
        - "Digital Transformation"
    stat:
      type: string
      title: Stat
      description: A metric or statistic for this slide (e.g., "98% Client Retention").
    background_image:
      $ref: json-schema-definitions://canvas.module/image
      title: Background Image
      description: Full-bleed background image for this slide.
      type: object
```

**Key conventions applied:**
- `required: [title]` — the slide must have a title for the info bar
- `examples` on required props — the first example becomes the default in the Canvas editor
- Image prop uses `$ref: json-schema-definitions://canvas.module/image` — Canvas's standard image reference pattern (see `data-model/media-handling.md`)
- No slots — the slide is a leaf component with no children

---

## Phase 3: Twig Template

### hero-carousel.twig

```twig
{% set carousel_id = 'hero-carousel-' ~ random() %}
{% set interval = auto_rotate_interval|default(5000) %}

<section{{ attributes.addClass('hero-carousel').setAttribute('id', carousel_id) }}
  data-auto-rotate="{{ interval }}"
>
  {# Slot block — MUST be a direct child of the root element.
     Canvas maps the slot drop zone to the parent element of {% block %},
     so the block must be a direct child of the component root. #}
  {% block slides %}{% endblock %}

  {# Gradient overlay — ensures text readability over images #}
  <div class="hero-carousel__gradient"></div>

  {# Content overlay — optional heading/subheading #}
  {% if heading or subheading %}
    <div class="hero-carousel__content">
      <div class="container">
        {% if heading %}
          <h1 class="hero-carousel__heading">{{ heading }}</h1>
        {% endif %}
        {% if subheading %}
          <p class="hero-carousel__subheading">{{ subheading }}</p>
        {% endif %}
      </div>
    </div>
  {% endif %}

  {# Bottom info bar — populated by JS from slide data attributes #}
  <div class="hero-carousel__bar">
    <div class="container">
      <div class="hero-carousel__bar-inner">
        <div class="hero-carousel__info">
          <span class="hero-carousel__slide-title"></span>
          <span class="hero-carousel__slide-stat"></span>
        </div>
        <div class="hero-carousel__nav">
          <button type="button" class="hero-carousel__prev" aria-label="Previous slide">
            <!-- SVG arrow icon -->
          </button>
          <span class="hero-carousel__counter">
            <span class="hero-carousel__current">1</span>
            <span class="hero-carousel__separator">/</span>
            <span class="hero-carousel__total">1</span>
          </span>
          <button type="button" class="hero-carousel__next" aria-label="Next slide">
            <!-- SVG arrow icon -->
          </button>
        </div>
      </div>
    </div>
  </div>
</section>
```

**Key conventions applied:**

1. **Root element has BEM class** — `attributes.addClass('hero-carousel')` ensures the component class is always present. Canvas uses `attributes` to inject its own classes/data attributes.

2. **Slot block is a direct child of root** — This is a **critical Canvas requirement**. Canvas discovers slot drop zones by finding the parent element of `{% block %}`. If the block is nested deeper (e.g., inside a `<div>`), Canvas won't correctly target the slot.

3. **BEM naming throughout** — `.hero-carousel__gradient`, `.hero-carousel__heading`, `.hero-carousel__bar-inner`. No Bootstrap utility classes in the template — all styling is in SCSS.

4. **Data attributes for JS communication** — `data-auto-rotate` on the root; `data-slide-title` / `data-slide-stat` on children. The JS reads these to populate the info bar. This is the preferred pattern for passing data from Twig to JS (not inline scripts or global variables).

5. **Semantic HTML** — `<section>` root, `<h1>` heading, `<button>` for navigation with `aria-label`. Accessibility matters.

### carousel-slide.twig

```twig
<div{{ attributes.addClass('carousel-slide') }}
  data-slide-title="{{ title }}"
  {% if stat %}data-slide-stat="{{ stat }}"{% endif %}
>
  {% if background_image and background_image.src %}
    {% include 'canvas:image' ignore missing with {
      src: background_image.src,
      alt: background_image.alt|default(''),
      class: 'carousel-slide__image',
      width: background_image.width,
      height: background_image.height
    } only %}
  {% endif %}
  <div class="carousel-slide__overlay"></div>
</div>
```

**Key conventions applied:**

1. **Image rendering via Canvas include** — `{% include 'canvas:image' %}` uses Canvas's image rendering pipeline, which handles responsive images and lazy loading. This is preferred over a raw `<img>` tag.

2. **Data attributes expose metadata to parent** — The parent carousel's JS reads `data-slide-title` and `data-slide-stat` from each slide to populate the info bar. This decouples parent and child — the child doesn't know about the parent's DOM structure.

3. **No slots** — This is a leaf component. All content comes from props.

---

## Phase 4: SCSS — Design Token Integration

### hero-carousel.scss

```scss
@import "component-base";  // Variables + mixins, ZERO CSS output

.hero-carousel {
  position: relative;
  height: 600px;
  overflow: hidden;
  background-color: $brand-900;       // ← Design token, not hardcoded hex

  // Full-bleed — break out of any parent container
  width: 100vw;
  left: 50%;
  right: 50%;
  margin-left: -50vw;
  margin-right: -50vw;

  @media (max-width: 991.98px) { height: 450px; }
  @media (max-width: 575.98px) { height: 350px; }
}

.hero-carousel__gradient {
  position: absolute;
  inset: 0;
  z-index: 2;
  background: linear-gradient(
    to bottom,
    rgba($brand-900, 0.1) 0%,      // ← Token for gradient color
    rgba($brand-900, 0.3) 50%,
    rgba($brand-900, 0.8) 100%
  );
  pointer-events: none;
}

.hero-carousel__heading {
  font-size: 2.5rem;
  font-weight: 700;
  color: $neutral-0;                 // ← Token for white
  // ...responsive breakpoints
}

.hero-carousel__bar {
  background-color: rgba($brand-900, 0.6);  // ← Token
  backdrop-filter: blur(8px);
  border-top: 1px solid rgba($neutral-0, 0.1);
}

.hero-carousel__slide-stat {
  color: $brand-200;                 // ← Token for accent color
}
```

**Key rules demonstrated:**

1. **`@import "component-base"`** — Always the first line. Provides `$brand-*`, `$neutral-*`, `$type-scale`, `$font-weight-*`, elevation mixins, Bootstrap variables. Emits **zero CSS** — safe to import in every component.

2. **Design tokens, not hardcoded values** — Every color references `$brand-*` or `$neutral-*` from `_tokens.scss`. If the brand palette changes, rebuild and this component updates automatically.

3. **Full-bleed pattern** — The `width: 100vw; left: 50%; margin-left: -50vw` trick breaks out of the parent container. Documented in `css-strategy.md` → "Full-Bleed Pattern".

4. **No role/preset class imports** — SDC SCSS uses tokens and variables directly. The `.role-*` and `.preset-*` classes (from `_typography-roles.scss` and `_layout-presets.scss`) are compiled once in `style.css` and used by Twig preset maps. Importing them in component SCSS would duplicate those classes in every component's compiled CSS.

5. **No Bootstrap utility classes in SCSS** — The component doesn't use `.bg-dark` or `.text-white`. It uses `$brand-900` and `$neutral-0` directly. Bootstrap utilities are for Canvas prop overrides in Twig, not for component SCSS.

### carousel-slide.scss

```scss
@import "component-base";

.carousel-slide {
  position: absolute;
  inset: 0;
  z-index: 1;
  opacity: 0;
  transition: opacity 0.8s ease;     // Crossfade transition
}

.carousel-slide--active {
  opacity: 1;                        // JS toggles this BEM modifier
}

.carousel-slide__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    135deg,
    rgba($brand-900, 0.4) 0%,       // ← Token
    rgba($brand-900, 0.1) 100%
  );
}
```

**Key rule:** The `--active` BEM modifier is toggled by JavaScript. CSS handles the visual transition; JS handles the state change. This separation keeps the component testable and maintainable.

---

## Phase 5: JavaScript — Drupal Behavior

### hero-carousel.js

```javascript
(function (Drupal) {
  'use strict';

  Drupal.behaviors.heroCarousel = {
    attach: function (context) {
      var carousels = context.querySelectorAll('.hero-carousel');

      carousels.forEach(function (carousel) {
        // Prevent duplicate processing on AJAX / partial re-renders
        if (carousel.dataset.heroCarouselProcessed) return;
        carousel.dataset.heroCarouselProcessed = 'true';

        var slides = carousel.querySelectorAll('.carousel-slide');
        if (slides.length === 0) return;

        var currentIndex = 0;
        var autoRotateInterval = parseInt(carousel.dataset.autoRotate, 10) || 5000;
        var timer = null;

        // DOM refs for info bar
        var titleEl = carousel.querySelector('.hero-carousel__slide-title');
        var statEl = carousel.querySelector('.hero-carousel__slide-stat');
        // ... counter, prev/next buttons

        function goToSlide(index) {
          // Wrap around
          if (index < 0) index = slides.length - 1;
          else if (index >= slides.length) index = 0;

          slides[currentIndex].classList.remove('carousel-slide--active');
          currentIndex = index;
          slides[currentIndex].classList.add('carousel-slide--active');

          // Update info bar from slide data attributes
          titleEl.textContent = slides[currentIndex].dataset.slideTitle || '';
          statEl.textContent = slides[currentIndex].dataset.slideStat || '';
        }

        // Auto-rotation, navigation, pause-on-hover...
        goToSlide(0);
        startAutoRotate();
      });
    }
  };
})(Drupal);
```

**Key conventions:**

1. **`Drupal.behaviors` pattern** — Required for Drupal integration. The `attach` function is called on initial page load and after any AJAX partial re-renders (e.g., Canvas editor updates).

2. **Duplicate processing guard** — `dataset.heroCarouselProcessed` prevents re-initialization when `attach` is called multiple times on the same element.

3. **Parent queries child DOM** — The hero carousel finds `.carousel-slide` elements within itself. It reads their `data-slide-title` and `data-slide-stat` attributes to populate the info bar. This is how parent and child SDCs communicate at runtime.

4. **BEM modifier toggling** — JS adds/removes `.carousel-slide--active` to control which slide is visible. CSS handles the visual transition via `opacity` and `transition`.

5. **`core/drupal` dependency** — The component YAML declares `libraryOverrides.dependencies: [core/drupal]` so that the `Drupal` global is available.

---

## Phase 6: Build and Register in Canvas

### Build the CSS

```bash
ddev theme-build   # or: cd web/themes/custom/alchemize_forge && npm run build
```

Webpack compiles `hero-carousel.scss` → `hero-carousel.css` and `carousel-slide.scss` → `carousel-slide.css` using the `includePaths` configuration that resolves `@import "component-base"` to the theme's `scss/` directory.

### Register in Canvas

```bash
ddev drush cr   # Clear cache — Drupal discovers new SDC components
```

After cache rebuild, run the Canvas component regeneration if the component doesn't appear:

```bash
ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-regenerate-components.drush.php
```

Verify the component is registered:

```bash
ddev drush php:script .alchemize/drupal/capabilities/diagnostics/canvas-component-status.drush.php
```

The hero carousel should appear as `sdc.alchemize_forge.hero-carousel` with status **enabled**.

### Use in Canvas Editor

1. Open the Canvas editor on a page
2. Add a **Hero Carousel** component
3. Set props: heading, subheading, auto-rotate interval
4. Drop **Carousel Slide** components into the `slides` slot
5. On each slide: set title, stat, and upload a background image

### Use Programmatically (Capability Script)

```php
require_once __DIR__ . '/../lib/canvas-lib.php';
[$theme, $components, $versions] = canvas_lib_init(['hero-carousel', 'carousel-slide']);

$tree = [];

$tree[] = canvas_tree_item(
  canvas_uuid('hero'),
  'sdc.alchemize_forge.hero-carousel',
  $versions['hero-carousel'],
  [
    'heading' => 'Building Intelligent Systems',
    'subheading' => 'AI-powered tools for modern development teams.',
    'auto_rotate_interval' => 5000,
  ],
  $slot_uuid, $slot
);

$tree[] = canvas_tree_item(
  canvas_uuid('slide1'),
  'sdc.alchemize_forge.carousel-slide',
  $versions['carousel-slide'],
  [
    'title' => 'Alchemize Dev',
    'stat' => '10x Faster Development',
    // background_image requires media entity reference — see media-handling.md
  ],
  canvas_uuid('hero'), 'slides'
);
```

---

## Phase 7: What Happens When the Brand Changes

This is the payoff of the 8-layer design system. If the brand palette changes (e.g., `$brand-900` moves from `#000D4D` to a new dark color):

1. Edit `_tokens.scss` — change `$brand-900`
2. Run `npm run build`
3. **Automatically updated:**
   - Hero carousel background color (`$brand-900`)
   - Gradient overlays (`rgba($brand-900, ...)`)
   - Info bar background (`rgba($brand-900, 0.6)`)
   - Slide overlays (`rgba($brand-900, ...)`)
   - Stat accent color (`$brand-200`)
   - Every layout preset that references `$brand-900` (`.preset-section-hero-dark`, `.preset-card-dark`)
   - Every typography role that references brand tokens
   - All Bootstrap overrides (`$dark`, `$primary`)

**Zero Twig edits. Zero JS edits. One token change → complete visual update.**

---

## Anatomy Summary

| File | Purpose | Design System Layer |
|------|---------|-------------------|
| `hero-carousel.component.yml` | Props, slots, metadata for Canvas | Schema definition |
| `hero-carousel.twig` | DOM structure, slot placement, data attributes | Layer 8 (composition) |
| `hero-carousel.scss` | Visual styling using brand tokens | Layer 7 (SDC styles) |
| `hero-carousel.js` | Auto-rotation, navigation, state management | Behavior |
| `hero-carousel.css` | Compiled output (Webpack) | Build artifact |
| `carousel-slide.component.yml` | Props for individual slide | Schema definition |
| `carousel-slide.twig` | Image rendering, data attribute exposure | Layer 8 (composition) |
| `carousel-slide.scss` | Crossfade transition, overlay | Layer 7 (SDC styles) |

## Lessons Learned

1. **Slot blocks must be direct children of the component root element.** Canvas discovers slots by finding the parent of `{% block %}`. Nesting the block inside a wrapper `<div>` breaks slot detection.
2. **Parent + child is the right pattern for repeatable items.** Don't try to cram multiple slides into props — use a slot and a separate child SDC.
3. **Use data attributes for parent/child communication.** The parent JS reads `data-slide-title` from children. This is cleaner than DOM scraping or global state.
4. **`@import "component-base"` not `_default`** — Component SCSS imports `component-base` (variables + mixins only, zero CSS) not `_default` (which would pull in `_semantic.scss` and emit CSS custom properties in every component's compiled output).
5. **Full-bleed needs the CSS escape hatch** — `width: 100vw; left: 50%; margin-left: -50vw` breaks out of the container. This can't be done with Bootstrap utilities.
6. **`Drupal.behaviors` with duplicate guard** — Always use the processed-flag pattern to prevent re-initialization on AJAX re-renders.
7. **Image props use Canvas's `$ref` pattern** — `$ref: json-schema-definitions://canvas.module/image` gives you Canvas's image picker UI. See `data-model/media-handling.md`.

## Related Documentation

| Document | Relevance |
|----------|-----------|
| `component-strategy.md` → "SDC Gold Standard Pattern" | Generic template and conventions this example follows |
| `component-strategy.md` → "Decision Reference" | When to create an SDC vs use primitives |
| `canvas-sdc-components.md` → Tier 1 table | Hero Carousel prop/slot reference |
| `canvas-build-example.md` | Companion: Tier 2 primitive composition example |
| `canvas-build-guide.md` → Phase 0 | SDC identification methodology |
| `css-strategy.md` → "Full-Bleed Pattern" | The CSS trick used for viewport-width sections |
| `data-model/media-handling.md` | Image prop `$ref` pattern and media entity handling |
| `infrastructure/developer-tools.md` | Build commands and Canvas regeneration scripts |
