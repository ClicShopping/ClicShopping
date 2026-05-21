# TEMPLATES.md — ClicShopping v4.20+

> Rendering architecture — front-office (Shop) and back-office (ClicShoppingAdmin).
> The two contexts have fundamentally different philosophies.
> Agent rules: `AGENTS.md` — Core architecture: `ARCHITECTURE.md`

---

## 1. Fundamental principle

**A template only renders.** No business logic, no DB access, no strings
hardcoded. All data arrives prepared from the controller or module.

Reading note of the current core: certain historical page templates under
`Core/ClicShopping/Sites/*/Pages/*/templates` include orchestration logic.
For any new development, keep the "render-only" rule.

```
✗ Business logic or calculation
✗ Direct access to the database
✗ Hardcoded visible channels
✗ Redirection, session manipulation
✗ LLM call or AI service
✓ Display of prepared variables
✓ Display loops on tables built upstream
✓ Includes sub-components via the engine
✓ Systematic escapement of outputs
```

---

## 2. Overview — voluntary HTML/logical separation

The separation between HTML files and application logic is **architecturally
intentional** to allow the user to create or modify themes freely,
without touching the business PHP code.

```
Core/ClicShopping/Sites/Shop/ ← controllers and page templates 
(Pages/*/templates)

sources/template/{Theme}/ ← HTML files of the theme chosen by the user 
totally separated from logic

Core/ClicShopping/Apps/{Vendor}/{App}/ ← an App can expose Shop pages 
via Sites/Shop/ (declared in JSON)
```
json example:


```
{
  "title": "Categories App",
  "app": "Categories",
  "vendor": "Catalog",
  "version": "1.0.3",
  "req_core_version": "4.0",
  "license": "GPL-2",
  "authors": [
    {
      "name": "ClicShopping",
      "company": "ClicShopping",
      "email": "admin@clicshopping.org",
      "website": "https://www.clicshopping.org"
    }
  ],
  "modules": {
    "HeaderTags": {
      "Categories": "Module\\HeaderTags\\Categories"
    },
    "Hooks": {
      "ClicShoppingAdmin/Langues": {
        "DeleteConfirm": "Module\\Hooks\\ClicShoppingAdmin\\Langues\\DeleteConfirm",
        "Insert": "Module\\Hooks\\ClicShoppingAdmin\\Langues\\Insert"
      },
      "ClicShoppingAdmin/Stats": {
        "StatsCategories": "Module\\Hooks\\ClicShoppingAdmin\\Stats\\StatsCategories"
      },
      "ClicShoppingAdmin/Products": {
        "ProductsContentTab1": "Module\\Hooks\\ClicShoppingAdmin\\Products\\ProductsContentTab1",
        "Insert": "Module\\Hooks\\ClicShoppingAdmin\\Products\\Insert",
        "Update": "Module\\Hooks\\ClicShoppingAdmin\\Products\\Update"
      },
      "ClicShoppingAdmin/DashboardShortCut": {
        "DashboardShortCut": "Module\\Hooks\\ClicShoppingAdmin\\DashboardShortCut\\DashboardShortCutCategories"
      }
    }
  },
  "routes": {
    "ClicShoppingAdmin": "Sites\\ClicShoppingAdmin\\Pages\\Home"
  }
}
```

**Never move theme templates (`sources/template/`) to `Core/ClicShopping/Sites/Shop/`.**
**Never change the code inside Default  theme templates (`sources/template/Default`). Use always a Template from a subDirectory and copy the files from Default (`sources/template/MyTemplate`).**
**Never put application logic in `sources/template/`.**

---

## 3. Front office (Shop) — detailed architecture

### 3.1 Role of `sources/template/` — Default and custom themes

`Default` is the **basic theme delivered with core**. Any other theme **overload
`Default` file by file**: if a file does not exist in the custom theme,
the system automatically uses the corresponding `Default` file.
`Default` therefore becomes the **fallback** as soon as a custom theme is active.

The choice of active theme is controlled by the user via the admin interface.

```
sources/template/
├── Default/ ← basic theme — universal fallback
│ ├── header.php # Global header ← root of Default/
│ ├── footer.php # Footer global ← root of Default/
│ ├── css/ # CSS organized by language
│ │ └── {lang}/ # Directory by language (e.g.: en/, fr/)
│ │ ├── compressed_css.php # Entry point — reads subdirectories and compresses
│ │ ├── general/ # Global styles: stylesheet, bootstrap, general calls
│ │ └── modules_{module_name}/ # Module-specific CSS — named according to the module
│ │ └── {module_name}.css # ex: pi_products_info_also_purchased.css
│ ├── javascript/ # Default Theme JavaScript
│ │ └── *.js
│ ├── files/ # Page template (global HTML structure)
│ │ └── *.php
│ ├── modules/ # HTML templates for modules
│ │ ├── {module_name}/
│ │ │ ├── content/ # HTML of the module (fixed template)
│ │ │ │ └── {module_name}.php
│ │ │ └── template_html/ # HTML listing — only if the module displays a list
│ │ │ └── {module_name}.php
│ │ └── ...
│ └── images/
│
└── {CustomTheme}/ ← custom theme chosen by the user 
├── header.php # If present, overload Default/header.php 
├── footer.php # If present, overload Default/footer.php 
├── css/ # Override Default CSS by language 
├── javascript/ # Override Default JS 
├── files/ # Overrides the Default template 
└── ... index.php must copied
└── ...index_default.php must copied
├── modules/ # Override Default modules 
│ └── ... # Any file missing → fallback to Default/ 
└── images/
```
**Resolution rule:**
```
1. sources/template/{CustomTheme}/{file} ← priority — active custom theme. Copy the necessary files from Default template before to change inside the custom Template.
2. sources/template/Default/{file} ← automatic fallback if missing from custom
3. All the css directory must copied
3. The modules contains some parameters can be applied and customized the module behavior.
```

A custom theme should only contain the files that it actually overloads.
Do not copy the entire `Default/` into a custom theme, Just the files you need to update.

### 3.2 Critical distinction: fixed template vs listing

Each module in `modules/` has its own subdirectory, with two subfolders
depending on the type of rendering — do not confuse them.

| Type | Subdirectory | Usage |
|---|---|---|
| **Header / Footer** | `Default/header.php` and `Default/footer.php` | At the **root of `Default/`** — outside `modules/` |
| **Fixed template** | `modules/{module_name}/content/` | Module HTML — single block rendering |
| **Template listing** | `modules/{module_name}/template_html/` | HTML listing — only when the module displays a list |

The `template_html/` subdirectory is only present if the module manages a listing.
A simple module only has `content/`. Do not create `template_html/` by default.

An agent that places listing HTML in `content/` instead of `template_html/` will break rendering.
An agent that places header.php in `modules/` instead of the root of `Default/` will break rendering.

### 3.3 Role of modules in `Apps/`

The modules in `Core/ClicShopping/Apps/{Vendor}/{AppName}/Module/` define:
- **settings** and module configuration
- The **installation/uninstallation** script
- The **PHP functions** that prepare the data
- The **call to the corresponding HTML template** in `sources/template/{Theme}/modules/`

The PHP module **calls** the HTML template — it does not contain it.

```
Core/ClicShopping/Apps/{Vendor}/{AppName}/Module/{Type}/{ModuleName}/
├── {ModuleName}.php ← settings, install, functions, call the HTML template
└── languages/ ← language constants used by the module
```

The rendered HTML file is resolved in this order:
1. `sources/template/{CustomTheme}/modules/{module_name}/content/{module_name}.php` if present
2. `sources/template/Default/modules/{module_name}/content/{module_name}.php` otherwise (fallback)

For a listing, the same order applies with `template_html/` instead of `content/`.

### 3.4 HTML Templates for an App via Sites/Shop/ and JSON

An App can also expose its own HTML templates directly from its directory,
without going through `sources/template/`. This case is declared in JSON in the App.

```
Core/ClicShopping/Apps/{Vendor}/{AppName}/
├── Sites/Shop/ ← pages and templates specific to the App
│ └── Pages/{PageName}/templates/
└── clicshopping.json ← declares the pages/templates exposed by the App
```

Rule: if an App exposes its templates via `Sites/Shop/` + `clicshopping.json`, these templates
follow the same rules as all other HTML templates (no business logic,
no DB access, escaping required).

### 3.5 Controllers and classes — where they are

```
Core/ClicShopping/Sites/Shop/
├── Pages/ ← page controllers (implement PagesInterface)
│ └── {PageName}/
│ ├── {PageName}.php
│ └── Actions/
└── Classes/ ← job classes from the Shop
```

These files contain **no HTML**. They prepare the data and pass it
to the template engine.

### 3.6 Full Rendering Flow (Shop)

```
HTTP request 
→ Core/ClicShopping/Sites/Shop/Pages/{ModuleName}/{ModuleName}.php (controller — logic, prepares data) 
→ Core/ClicShopping/Sites/Shop/Pages/{ModuleName}/Actions/ (if POST action) 
→ Core/ClicShopping/Apps/*/Module/{Type}/{Module}.php (module — settings, functions, template call) 
→ sources/template/{Custom}/modules/{mod}/content/{mod}.php ← if present 
or sources/template/Default/modules/{mod}/content/{mod}.php ← fallback 
→ sources/template/{Custom or Default}/files/ (page template) 
→ sources/template/{Custom or Default}/header.php (global header — root Default/) 
→ sources/template/{Custom or Default}/footer.php (global footer — root Default/)
```

### 3.7 SEO

- `<title>` and `<meta>` tags — injected via **dedicated modules** (e.g. module header tags)
  who push their code into the header or footer via the module system
  — never hardcoded directly in template files
- unique `<h1>` per page, hierarchy `h1 > h2 > h3` respected
- `alt` attribute on all images — value from prepared data via `HTML::image()`
- URLs via existing routing helpers — never hardcoded
- Static cache active on catalog pages
- The Core/modules/header.tgs modules contains all the modules can be displayed in the footer or de header about meta tag.
---
## 4. Back office (ClicShoppingAdmin) — separate architecture

### 4.1 Principle

The admin is **entirely managed by Apps**. There is no overall theme
interchangeable, no `sources/template/` for the admin.

The logic and admin templates are in `Core/ClicShopping/Sites/ClicShoppingAdmin/`.

### 4.2 Structure of `Sites/`

Both contexts have their own tree under `Sites/`:

```
Core/ClicShopping/Sites/
├── ClicShoppingAdmin/
│ └── Pages/
│ └── {PageName}/ ← ex: Home
│ ├── Actions/ # Controller actions (POST, processing)
│ ├── templates/ # HTML templates of the admin page
│ └── {PageName}.php # Main page controller
│
└── Shop/ 
└──Pages/ 
└── {ModuleName}/ ← module/page name 
├── Actions/ # Controller actions 
├── templates/ # Shop page HTML templates 
└── {ModuleName}.php # Main controller
```

**The main front-office templates remain in `sources/template/Default/` or the custom theme.**
`Core/ClicShopping/Sites/*/Pages/*/templates` contains page templates; aim for minimal logic on the template side.

### 4.3 Admin philosophy

- Productivity oriented: tables, CRUD forms, confirmations, status returns
- Data received already validated and prepared by the controller
- No business decisions in the template
- Session verification in the **controller**, never in the template
- CSRF token required on each form via existing helper
- No static cache — fresh data on every render

---

## 5. Languages — common rules


| Layer | Path | Scope |
|---|---|---|
| **App / Module** | `Core/ClicShopping/Apps/*/languages/{lang}/` | High priority for Apps (Shop and Admin) |
| **Admin core** | `ClicShoppingAdmin/Core/languages/{lang}/` | Back office global labels |
| **Overall / Theme** | `sources/languages/{lang}/` | Transversal texts and front-office fallback |


- No visible hardcoded string — always via `getDef()`
- Minimum compatibility: EN + FR
- Keys follow the existing format in the target scope

```php
// ✓ Correct — reading via getDef()
echo $CLICSHOPPING->getDef('text_add_to_cart');

// ✗ Prohibited — hardcoded channel
echo 'Add to cart';
```

---

## 6. Rules common to both contexts

### Forms — CSRF

The CSRF token is mandatory on all forms, **Shop (catalog) and Admin**.
It is managed via the `['tokenize' => true]` parameter in `HTML::form()`:

```php
$form = HTML::form('cart_quantity', CLICSHOPPING::link(null, 'Cart&Add'), 'post', 'class="justify-content-center"', ['tokenize' => true]) . "\n";
```

Never build a raw HTML `<form>` form without going through `HTML::form()` with `tokenize`.

```php
echo HTML::outputProtected($variable); // HTML string
echo HTML::link('Configure&Process&module=' . $current_module); //url 
echo CLICSHOPPING::link(null, 'A&Tools\ActionsRecorder&Configure'); //url
```

Never display a variable without escape, even in admin context.

### What a template should never contain

```php
// ✗ DB access
Registry::get('Db')->query('SELECT ...');

// ✗ Business logic
if ($price * $tax > $threshold) { ... }

// ✗ Hardcoded channel
echo 'Add to cart';

// ✗ Session or redirection
header('Location: ...');
$_SESSION['key'] = 'value';

// ✗ LLM call
$ai->generate('...');
```

---

## 7. Checklist before submitting a template

```
[ ] No business logic or DB access in the template execpt modules
[ ] All output escaped (HTML::outputProtected)
[ ] No visible hardcoded string — always via $CLICSHOPPING->getDef('key')
[ ] URLs via routing helpers, not hardcoded
[ ] Correct location of the template: 
header/footer → root of Default/ (not in modules/) 
fixed module → modules/{module_name}/content/ 
module listing → modules/{module_name}/template_html/ (if the module manages a list)
[ ] template_html/ created only if the module actually manages a listing
[ ] Custom theme: only contains overloaded files — no copy of Default/
[ ] New template intended for all themes → create it in Default/
[ ] SEO tags (title, meta) → via dedicated modules header_tag which inject into header/footer
[ ] Front: unique h1 per page, alt on all images
[ ] Front: Core/ClicShopping/Work/ cache invalidation planned via the administration
[ ] CSRF token on each Shop and Admin form — HTML::form() with ['tokenize' => true]
[ ] Admin: session verification in the controller (Core/ClicShopping/Sites/ClicShoppingAdmin/Pages/), not in the template
[ ] App with Sites/Shop/ + clicshopping.json: templates declared correctly in JSON
[ ] index.php and index_default.php are copied
[ ] all css are copied

```
---

## 8. References
- Architecture core, controllers, routing: `ARCHITECTURE.md`
- Security and escape: `SECURITY.md`
- Wiki front office display template: https://github.com/ClicShopping/ClicShopping/wiki/How-to-display-information-inside-a-template
- DeepWiki templates: https://deepwiki.com/ClicShopping/ClicShopping/8-template-and-module-system