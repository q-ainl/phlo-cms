# Phlo CMS

Reusable CRUD and admin layer for the [Phlo](https://github.com/q-ainl/phlo) framework. Define a model schema and get list, record, create and edit views, a JSON REST API, dashboard widgets, a layout and a set of themes out of the box.

Phlo CMS lives in the application layer of the [Phlo platform](https://phlo.tech/ecosystem): the same `.phlo` language and resource model as your app, mounted as a resource path through Composer. The [Phlo Dashboard](https://github.com/q-ainl/phlo-dashboard) is built on it.

## Features

- **Schema driven CRUD**: one model definition yields list, record, create and edit views.
- **Field types**: text, email, password, number, bool, date, datetime, select, multiselect, parent, child, token and virtual.
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

## License

MIT. See [LICENSE](LICENSE).
