# Elementor MCP Composer

Reusable Composer library that coordinates a **single unified Elementor MCP server** across multiple host plugins. Host plugins register WordPress abilities, contribute their slugs to a shared registry, and optionally mount a shared admin page.

The package follows the same multi-plugin version arbitration pattern as [`elementor/wp-one-package`](https://github.com/elementor/wp-one-package): when bundled by more than one plugin, the highest version wins.

Agents adding a host plugin, or changing this package's public API, follow [.cursor/skills/host-mcp-composer/SKILL.md](.cursor/skills/host-mcp-composer/SKILL.md). The same steps are on Confluence: [elementor-mcp-composer: add a host plugin](https://elementor.atlassian.net/wiki/spaces/RDDEP/pages/3124199425). `.cursor/rules/keep-mcp-composer-docs-current.mdc` requires those three to stay in sync when the host contract changes.

## Requirements

- PHP 7.4+
- WordPress with the [Abilities API](https://make.wordpress.org/core/2025/11/10/abilities-api/) (`wp_register_ability`)
- [`wordpress/mcp-adapter`](https://github.com/WordPress/mcp-adapter) (pulled in automatically as a dependency)

## Installation

Add the package to your plugin's `composer.json`:

```json
{
  "require": {
    "elementor/elementor-mcp-composer": "^1.0"
  }
}
```

Then run `composer install`. The host plugin must load Composer's autoloader (`vendor/autoload.php`) on boot — same as any other Composer dependency.

No manual `require` of package files is needed. `runner.php` is registered via Composer `autoload.files` and hooks into `plugins_loaded` to register the package version and initialize the winning copy.

After initialization, these constants are available:

- `ELEMENTOR_MCP_COMPOSER_VERSION`
- `ELEMENTOR_MCP_COMPOSER_PATH`
- `ELEMENTOR_MCP_COMPOSER_URL`

## How it works

```text
Host plugin loads vendor/autoload.php
        │
        ▼
runner.php registers version on plugins_loaded (-20)
        │
        ▼
Versions picks highest registered version on plugins_loaded (-15)
        │
        ▼
Loader::init() defines constants and boots Server_Bootstrap
        │
        ▼
Server_Bootstrap registers registry slugs on the MCP Adapter default server and the legacy Elementor MCP server
```

Each host plugin is responsible for:

1. **Registering abilities** via `wp_register_ability()` (the actual tool/resource implementations).
2. **Contributing slugs** to the shared `Registry` so the unified server exposes them.
3. **Mounting the admin page** (optional) by delegating to `Admin\Page` from the host's menu system.

### While the Elementor abilities are disabled

When the `elementor_mcp_enabled` setting is off, the registry slugs are not exposed. The default server lists a single tool, `elementor/enable-elementor-abilities`, instead. It has one required boolean input, `consent`, which the model must set to `true` only after asking the user and getting their explicit agreement in chat. For an administrator (`manage_options`) and a strict boolean `true`, the tool enables the abilities through the same code as the settings route (`McpSettingsController::set_enabled()`) and tells the model to reload its tool list. Any other value (missing, `false`, a string, a number) or a user without the capability changes nothing. The tool is only registered while the setting is off, is not exposed through the Abilities REST API or the adapter's discover/execute tools, is not registered on the legacy `elementor-mcp-server`, and is gone on the next request after enabling.

---

## Registering tools and resources

### Step 1 — Register the ability

Register your ability with WordPress first. The slug you use here is what the MCP server will expose.

```php
wp_register_ability(
    'elementor/my-tool',
    [
        'label'       => 'My Tool',
        'description' => 'Does something useful.',
        'execute_callback' => [ $this, 'run_my_tool' ],
        // ... ability schema ...
    ]
);
```

Do this on the `wp_abilities_api_init` action (or your plugin's equivalent bootstrap hook).

#### Or extend `Abstract_Ability`

The package ships the base class Elementor abilities use. Subclass it and call `register()`:

```php
use Elementor\MCP\Composer\Mcp\Abstract_Ability;
use Elementor\MCP\Composer\Mcp\Ability_Definition;

class My_Tool extends Abstract_Ability {

    protected function get_ability_id(): string {
        return 'elementor/my-tool';
    }

    protected function get_definition(): Ability_Definition {
        return new Ability_Definition(
            'My Tool',
            'Does something useful.',
            'elementor',
            [ 'type' => 'object' ],                   // output schema
            [ 'mutates_page' => true ],               // meta
            fn() => current_user_can( 'edit_posts' ), // permission callback
            [ 'type' => 'object', 'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] ]
        );
    }

    public function execute( $input = [] ) {
        return [ 'status' => 'ok' ];
    }
}

add_action( 'wp_abilities_api_init', fn() => ( new My_Tool() )->register() );
```

`register()` wires `execute_guarded()` as the execute callback, sets `meta.mcp.public` from the `elementor_mcp_enabled` setting and sets `meta.show_in_rest`. `execute_guarded()` runs these hooks, so host plugins add their own behavior without changing the base class:

| Hook | Type | Arguments |
| --- | --- | --- |
| `elementor/mcp/ability/is_available` | filter | `true\|WP_Error`, `$id`, `$ability` |
| `elementor/mcp/pre_execute_guard` | filter | `null\|WP_Error`, `$input` (page-mutating tools only) |
| `elementor/mcp/ability/before_execute` | action | `$ability`, `$input` |
| `elementor/mcp/ability/after_execute` | action | `$ability`, `$input`, `$result`, `$context` |

`$context` holds `is_mutating`, `is_failed`, `is_dry_run`, `post_id` and `record_capability` (false for errors and `dry_run`). Override `supports_dry_run()` to accept `dry_run: true`; otherwise a dry run returns a `dry_run_unsupported` error. A `description_notice` in an `is_available` error's data is appended to the ability description.

### Step 2 — Contribute the slug to the shared registry

Tell the package to include that ability on the unified Elementor MCP server:

```php
use Elementor\MCP\Composer\Mcp\Registry;

$registry = Registry::instance();

// Single slug
$registry->register_tool( 'elementor/my-tool' );

// Or in bulk
$registry->register_tools( [
    'elementor/get-page-structure',
    'elementor/create-page',
] );

$registry->register_resources( [
    'elementor/style-best-practices',
    'elementor/global-classes-resource',
] );

$registry->register_prompts( [
    'elementor/my-prompt',
] );
```

Register slugs on `mcp_adapter_init` at a priority **lower than 10** (Core uses 5, Angie 1), so `Server_Bootstrap::register_server` (default priority 10) picks them up when creating the server.

### Step 3 — Extend via filters (optional)

Third parties can append slugs without touching the registry directly:

```php
add_filter( 'elementor/mcp/server/tools', function ( array $tools ): array {
    $tools[] = 'my-plugin/extra-tool';
    return $tools;
} );

add_filter( 'elementor/mcp/server/resources', function ( array $resources ): array {
    $resources[] = 'my-plugin/extra-resource';
    return $resources;
} );

add_filter( 'elementor/mcp/server/prompts', function ( array $prompts ): array {
    $prompts[] = 'my-plugin/extra-prompt';
    return $prompts;
} );
```

Filters are applied inside `Registry` getters — consumers should not re-apply them.

---

## Mounting the admin page

`Admin\Page` is **passive**: it exposes metadata and a render callback but does **not** register itself in any WordPress menu. The host plugin mounts it.

```php
use Elementor\MCP\Composer\Admin\Page;

$page = Page::instance();

// Available getters:
$page->get_slug();         // 'elementor-mcp'
$page->get_page_title();   // 'Elementor MCP'
$page->get_label();        // 'MCP'
$page->get_capability();   // 'manage_options'
$page->get_icon();         // 'eicon-code'
$page->render();            // outputs server status + client config JSON
```

### Example — Elementor Editor One menu

Elementor Core wraps the page in a menu item class and registers it on the Editor One menu hook:

```php
use Elementor\MCP\Composer\Admin\Page;
use Elementor\Core\Admin\Menu\Interfaces\Admin_Menu_Item_With_Page;

class My_Mcp_Menu implements Admin_Menu_Item_With_Page {

    private Page $page;

    public function __construct() {
        $this->page = Page::instance();
    }

    public function get_slug(): string {
        return $this->page->get_slug();
    }

    public function get_page_title() {
        return $this->page->get_page_title();
    }

    public function get_label(): string {
        return $this->page->get_label();
    }

    public function get_capability(): string {
        return $this->page->get_capability();
    }

    public function get_icon(): string {
        return $this->page->get_icon();
    }

    public function render() {
        $this->page->render();
    }

    // ... parent slug, position, visibility, etc.
}
```

Then register the menu item from the host module:

```php
add_action( 'elementor/editor-one/menu/register', function ( $menu_data_provider ) {
    $menu_data_provider->register_menu( new My_Mcp_Menu() );
} );
```

See [`elementor/modules/mcp/admin-menu-items/editor-one-mcp-menu.php`](https://github.com/elementor/elementor/blob/master/modules/mcp/admin-menu-items/editor-one-mcp-menu.php) for the production implementation.

The rendered page shows:

- **Registered abilities** — JSON snapshot of tools, resources, and prompts on the unified server.
- **MCP client config** — ready-to-paste Cursor / Claude Desktop JSON pointing at `/wp-json/mcp/mcp-adapter-default-server` (WordPress MCP Adapter default server).

---

## Full consumer example

Minimal pattern for a host plugin module:

```php
use Elementor\MCP\Composer\Mcp\Registry;

class Module {

    public function __construct() {
        add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
        add_action( 'init', [ $this, 'register_registry_slugs' ], 5 );
        add_action( 'my_plugin/admin_menu/register', [ $this, 'register_menu' ] );
    }

    public function register_abilities(): void {
        wp_register_ability( 'my-plugin/do-thing', [ /* ... */ ] );
    }

    public function register_registry_slugs(): void {
        Registry::instance()->register_tool( 'my-plugin/do-thing' );
    }

    public function register_menu( $menu_provider ): void {
        $menu_provider->register_menu( new My_Mcp_Menu() );
    }
}
```

Real-world reference: [`elementor/modules/mcp/module.php`](https://github.com/elementor/elementor/blob/master/modules/mcp/module.php).

---

## Development

### PHP Development

```bash
composer install
composer test      # PHPUnit
composer run lint  # PHPCS
```

### React Admin UI Development

The admin page UI is built with React, TypeScript, and webpack. Assets live in `assets/dev/` and compile to `assets/build/`.

**Requirements:**
- Node.js 24 (pinned in `.nvmrc`)

**Setup:**

```bash
npm install
```

**Available scripts:**

```bash
npm run build           # Production build (minified, no source maps)
npm run scripts:watch   # Development build with watch mode
npm run lint            # ESLint validation
npm run format          # Prettier auto-format
npm run test            # Jest unit tests
```

**Local development workflow:**

1. Run `npm run scripts:watch` in a terminal — webpack rebuilds on file changes
2. Edit files in `assets/dev/js/` and `assets/dev/css/`
3. Refresh the admin page in WordPress to see changes
4. Run `npm run lint` before committing

**Build system:**
- **Entry:** `assets/dev/js/mcp-page/index.tsx` + `assets/dev/css/mcp-page.css`
- **Output:** `assets/build/mcp-page.js`, `mcp-page.css`, `mcp-page.asset.php`
- **Tooling:** TypeScript, webpack 5, ESLint, Prettier, Jest
- **Design system:** React + standard UI libraries
- **i18n:** `@wordpress/i18n` for translations

**Note:** Elementor's private packages (`@elementor/ui`, `@elementor/icons`) require authentication to Google Artifact Registry and are not included by default.

**Coding standards:**
- WordPress spacing style (spaces inside parens, brackets, braces)
- Tabs for indentation
- Trailing commas always
- React Hooks rules enforced
- No unused imports/variables

**Note:** Built assets (`assets/build/`) are **not committed** to git — they're generated in CI and included in release packages.

## License

Proprietary — Elementor.
