# Phlo CMS

Reusable CRUD and admin layer for the [Phlo](https://github.com/q-ainl/phlo) framework. Define a model schema and get list, record, create and edit views, a JSON REST API, dashboard widgets, a layout and a set of themes out of the box.

Phlo CMS lives in the application layer of the [Phlo platform](https://phlo.tech/ecosystem): the same `.phlo` language and resource model as your app, mounted as a resource path through Composer. The [Phlo Dashboard](https://github.com/q-ainl/phlo-dashboard) is built on it.

## Features

- **Schema driven CRUD**: one model definition yields list, record, create and edit views.
- **Field types**: text, email, password, number, price, bool, date, datetime, select, multiselect, parent, child, many, token, virtual, file, image and wysiwyg.
- **REST API**: create, update, patch and delete over JSON, with CSRF protection.
- **UI**: dashboard, layout, widgets (bar, gauge, line, list, pie) and themes.

## Requirements

- PHP 8.3 or newer
- [phlo/tech](https://github.com/q-ainl/phlo)

## Install

```
composer require phlo/cms
```

Wire it into your Phlo app config (`data/app.json`):

1. Add `"%app/vendor/phlo/cms/"` to `paths.resources`.
2. List the CMS resources you use (for example `CMS`, `CMS.layout`, `CMS.list`, `CMS.API`, `widgets/bar`) in `resources`.
3. Set `"icons"` to `"%app/vendor/phlo/cms/icons/"`.

The action buttons in the record, create and change views call `button()`, and async forms (file/image uploads included) rely on the form submit handler, so the host app must also list `tags.form` and `DOM/form` in `resources`.

## Optional field styling

The base markup tags every rendered field with its view and type, e.g. `class="label datetime"` or `class="input image"` (see `CMS::field()`). The default look leaves those untouched, so nothing changes unless you opt in.

For a more polished record/edit view, add the per-type styling resources you want under `styles/` to your `resources` list. They are theme-variable based, so they follow whichever theme is active:

| Resource | Effect |
|---|---|
| `styles/bool` | Checkbox rendered as a toggle switch |
| `styles/image` | Centered, clickable image preview |
| `styles/file` | File row with icon, hidden native input |
| `styles/datetime` | Freshness clock icon aligned with the timestamp |
| `styles/number` | Right-aligned values and inputs |
| `styles/child` | Scrollable list of linked child records |
| `styles/many` | Wrapped list of linked records |
| `styles/multiselect` | Chosen values rendered as pills |
| `styles/wysiwyg` | Framed editor with a toolbar |

These live in a subdirectory, so they are never auto-loaded — list only the ones you want. Your own field markup and styling keep working unchanged whether or not you opt in.

## View or edit on click

By default a click in a list opens the record in view mode (with an Edit button). To open records straight in edit mode (`/change/...`) instead, set `recordMode`:

- App-wide: `"recordMode": "edit"` in your app config (or `%app->recordMode = 'edit'`).
- Per model: `static recordMode = 'edit'` on the model, which overrides the app default.

Values are `'view'` (default) or `'edit'`. The view route stays reachable either way (e.g. for child drill-down); only the default click target changes.

## Defining a model

A model is a Phlo class (usually `extends model`) with a `static schema()` returning `field()` definitions. Everything else is optional and overrides a sensible default.

### Schema and fields

```phlo
static schema => arr (
    id:    field (type: 'number',   list: false, record: false),
    title: field (type: 'text',     title: 'Title', length: 160, required: true, search: true),
    author: field (type: 'parent',  obj: 'author', title: 'Author'),
    body:  field (type: 'wysiwyg',  title: 'Body', list: false),
    tags:  field (type: 'many',     obj: 'tag', table: 'article_tag', title: 'Tags'),
)
```

Common field options: `title`, `required`, `length`, `default`, `placeholder`, `search` (adds the field to the list search box), `prefix`/`suffix`, and per-view visibility `list` / `record` / `create` / `change` (set to `false` to hide, or to `'label'`/`'input'` to force a renderer). Relations use `obj` (target model); `many` also takes `table` (pivot), `parent`/`child` accept an explicit `key`.

`create:false` / `change:false` hide a field from that form **and** stop it being written from a posted payload, so they double as write-protection (a field with its own `parse()`, like `created`/`token`, still manages itself server-side). `handle: true` marks a field that must run on every save and on any single-field PATCH (used by `date`/`datetime` to auto-stamp `created`/`changed`); set it on a field whose value the model derives rather than the form.

### Model statics

| Static | Purpose |
|---|---|
| `$table` | Database table name |
| `$order` | Default `ORDER BY` clause |
| `$pp` | Rows per page (default 20) |
| `$uriList` / `$uriRecord` | URL segments for the list / record (default: table / class) |
| `$titleList` / `$titleRecord` | Display titles |
| `canCreate` / `canChange` / `canDelete` | Permission flags or methods (`canChange($record)`) |
| `$recordMode` | `'view'` (default) or `'edit'` — see above |
| `$recordView` / `$listView` / … | Load a `CMS.<mode>.<variant>` layout variant instead of the default |

### List and dashboard hooks

These carry the `obj` prefix on purpose. A model `extends model`, whose magic accessor also exposes data columns, so anything the framework reads generically across every model lives in the reserved `obj*` namespace to guarantee it never collides with a column (`objFilters`, `objSorts`, `objWidgets`, alongside the ORM's `objParents` / `objChildren` / `objValidate`). The plain-named statics above (`canCreate`, `uriList`, `titleList`, `recordMode`, `order`, `pp`) are simple per-model config values, not column-shaped, so they stay unprefixed. Each `obj*` hook may be a `static` property or a `static` method — the framework accepts either.

- `static objFilters()` — named `WHERE` snippets shown as a filter dropdown: `['open' => ['title' => 'Open', 'filter' => "status='open'"]]`.
- `static objSorts()` — named `ORDER BY` snippets shown as a sort dropdown.
- `static objWidgets()` — dashboard widgets keyed by title: `obj(type: 'pie', data: static::pair(...))`. Widget types: `bar`, `gauge`, `line`, `list`, `pie` (list them in `resources`, and add `chart.js` for charts).
- `static subNav()` — extra sidebar links under the model.

### Lifecycle hooks

Define any of these instance methods on the model to run logic around writes: `beforeSave` / `afterSave`, `beforeCreate` / `afterCreate`, `beforeChange($old)` / `afterChange($old)`, `beforeDelete` / `afterDelete`.

## License

MIT. See [LICENSE](LICENSE).
