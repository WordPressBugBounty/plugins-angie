# WP Abilities

**Goal:** WordPress-side MCP for `angie/*` — register abilities, run the scoped `/mcp/angie` server (discover → get-info → execute adapter), and author MCP instruction text for external clients.

**Scope:** `modules/wp-abilities/` only. Concrete abilities are implemented in feature modules (`modules/code-snippets`, `modules/elementor-core`, `modules/super-admin`) in a follow-up PR.

**Adjacent folders — different job, don't duplicate:**
- `wp-abilities-mcp-server` — iframe client; mirrors the WP catalog as per-category tool servers, not `/mcp/angie`.

When changing Angie MCP behavior or instructions, stay in this module unless the work is explicitly about the iframe bootstrap or the chat API.
