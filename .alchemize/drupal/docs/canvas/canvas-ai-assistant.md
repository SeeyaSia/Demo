# Canvas AI Assistant

## Purpose

Documents the Canvas AI page building feature — a multi-agent AI system that creates Canvas pages and components from natural-language prompts. Covers setup requirements, agent architecture, prompt patterns, token cost management, and known limitations. This feature is NOT currently enabled in this project.

## System Overview

The Canvas AI assistant (`canvas_ai` submodule) integrates AI-powered page building into the Canvas visual editor. It uses a multi-agent orchestrator that analyzes user prompts and dispatches to specialized agents that build pages, create components, or assemble templates using the project's available Canvas components.

**Note:** The `canvas_ai` submodule requires the `ai_agents` module and an AI provider that supports function calling (e.g., OpenAI GPT-4).

## Prerequisites

1. **`canvas_ai` submodule** — Ships with Canvas, currently hidden (`hidden: true` in info.yml)
2. **`ai_agents` module** — Drupal module providing the AI agent framework
3. **AI provider** — Must support function calling (e.g., OpenAI GPT-4, Anthropic Claude)
4. **AI provider configuration** — Configure for all Chat operation types at `/admin/config/ai/settings`

### Enabling
```bash
composer require drupal/ai_agents
ddev drush en canvas_ai -y
# Configure AI provider at /admin/config/ai/settings
ddev drush cex -y
```

## Agent Architecture

When a user enters a prompt in the Canvas editor (via the ✨ AI icon), the system works as follows:

### Orchestrator
The Canvas AI orchestrator receives the prompt, determines intent, and routes to the appropriate specialized agent.

### Specialized agents

| Agent | Machine name | What it does |
|-------|-------------|--------------|
| **Page Builder** | `canvas_page_builder_agent` | Assembles pages using **existing** SDC/Block/JS components — adds, arranges, and configures components in the page layout |
| **Component Creator** | `canvas_component_agent` | Creates or updates custom **code components** (JSX/React/Preact). Writes actual component code |
| **Title Generator** | `canvas_title_generation_agent` | Generates or edits SEO-friendly titles for `canvas_page` entities based on page content or user instructions |
| **Metadata Generator** | `canvas_metadata_generation_agent` | Generates SEO-friendly meta descriptions for `canvas_page` entities based on page content or user instructions |

The orchestrator also has access to `ai_agent:verify_task_completion` to ensure title/description generation wasn't skipped after page building.

### Orchestrator routing logic

The orchestrator uses these rules to decide which agent to invoke:

1. **Entity type validation** — Before calling Page Builder, Title Generator, or Metadata Generator, the orchestrator checks that the user is on a `canvas_page` entity (not a node or empty context). The Component Creator is exempt from this check.
2. **Page Builder vs Component Creator** — The Component Creator is invoked only when the user explicitly asks for a "code component," "JavaScript component," or "React component," or uploads an image to generate a component. All other page building requests go to the Page Builder.
3. **Proactive SEO generation** — When the Page Builder is invoked and the page title is empty/default ("Untitled page") or the meta description is empty, the orchestrator automatically calls the Title Generator and/or Metadata Generator in parallel.
4. **Ambiguity handling** — If a request is ambiguous (e.g., "Create an Our Services section"), the orchestrator asks the user to clarify: build from existing components or create a new code component?
5. **Image-to-component** — When a user uploads an image, the orchestrator describes the image in detail and passes it to the Component Creator.

### How agents use components

1. Agent retrieves all **enabled** components and their metadata
2. Component descriptions, props, slots, and usage guidance are sent as AI context
3. Agent constructs a component tree based on the prompt
4. Result is applied to the Canvas editor

## Prompt Patterns

### Forcing existing component usage (preventing new component creation)
Avoid keywords like "code component," "JavaScript component," or "React component" in prompts when you want to use existing components. The orchestrator routes to the Component Creator only for explicit code component requests. For general page building with existing components, use action words like "add," "create a section," or "build a layout":

```
Create a hero section with a heading and image.
Add a three-column card layout below the header.
```

If the orchestrator is unsure, it will ask whether you want a new code component or to use existing components.

### Positioning
Use directional language for placement:
- "at the top" / "at the bottom"
- "below the heading"
- "inside the card body"

### Iterative prompts work better
Break complex pages into focused requests:
1. "Create a header with site branding and main menu"
2. "Add a hero section below the header with a heading and background image"
3. "Create a three-column card grid in the content area"

Single complex prompts produce less reliable results.

## Component Documentation for AI Quality

The quality of component descriptions directly impacts AI output quality. For each component:

- Write clear **descriptions** of when and how to use the component
- Document **slot restrictions** (e.g., "actions slot accepts buttons and badges only")
- Specify ideal prop values and combinations
- Clarify standalone vs nested usage requirements

Component descriptions can be managed at: **Configuration > AI > Canvas AI Component Description Settings**

## Token Cost Management

Canvas AI context easily exceeds **30,000 tokens** per prompt because all enabled component metadata is sent as context. To reduce costs:

1. **Disable unused components** — Fewer components = smaller context = lower cost
2. **Refine component descriptions** — Concise, relevant descriptions reduce token usage
3. **Select specific component types** — Configure which types (SDC, Block, JS) to include at `/admin/config/ai/canvas-ai`
4. **Choose cost-effective providers** — Select AI providers with lower per-token input costs

## Known Limitations

- **Non-deterministic**: The same prompt may produce different results each time
- **May hallucinate enum values**: AI may reference non-existent prop values or component IDs, causing rendering errors
- **Vague prompts = unreliable results**: Specific, action-oriented prompts work best
- **Component name mismatches**: AI may use incorrect component machine names
- **No undo**: AI-generated changes must be manually reverted if incorrect
- **Cost**: Large component libraries result in significant per-prompt token costs

## Change Surface

- `config/<site>/canvas_ai.settings.yml` — Canvas AI settings (HTTP timeout, file upload size)
- `config/<site>/ai_agents.ai_agent.canvas_ai_orchestrator.yml` — Orchestrator agent config (system prompt, tool routing, max loops)
- `config/<site>/ai_agents.ai_agent.canvas_page_builder_agent.yml` — Page Builder agent config
- `config/<site>/ai_agents.ai_agent.canvas_component_agent.yml` — Component Creator agent config
- `config/<site>/ai_agents.ai_agent.canvas_title_generation_agent.yml` — Title Generator agent config
- `config/<site>/ai_agents.ai_agent.canvas_metadata_generation_agent.yml` — Metadata Generator agent config
- `/admin/config/ai/settings` — AI provider configuration
- `/admin/config/ai/canvas-ai` — Canvas AI component type selection
- Component descriptions — Each component's description and AI guidance text

## Constraints

- Requires a paid AI provider API (OpenAI, etc.)
- The `canvas_ai` submodule is hidden — it must be enabled via Drush, not the UI
- Depends on `ai_agents` module which is a separate Drupal contrib module
- AI agents can only work with **enabled** components
- AI-generated code components may need manual review and refinement

## Notes for Future Changes

- **Enabling AI**: When ready, install `ai_agents`, enable `canvas_ai`, configure an AI provider. Test with simple prompts first.
- **Component descriptions**: Before enabling AI, review all component descriptions and add clear usage guidance. This is the single biggest factor in AI output quality.
- **Cost monitoring**: Set up usage tracking with your AI provider to monitor per-prompt costs.
- **Prompt library**: Consider documenting effective prompts for common page patterns (landing page, article listing, contact page) as part of editorial documentation.
