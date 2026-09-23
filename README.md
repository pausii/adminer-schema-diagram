# Adminer Schema Diagram

An Adminer 5 plugin that replaces the built-in **Database schema** page with a modern,
interactive ER diagram: table cards with PK/FK badges, curved crow's foot relation lines,
automatic layout, click-to-highlight relations, a focus mode for a single table and
PNG/SVG export.

Everything runs in the browser from data Adminer already has. No build step, no external
requests, no extra tables — a single PHP file.

*[Baca dalam Bahasa Indonesia](README.id.md)*

![Schema diagram in a light design](docs/screenshot-light.png)

## Features

- **Table cards** with the column list, `PK` / `FK` badges, column types and a `?` marker
  for nullable columns. The table name links to its structure page, the number on the
  right is the column count, and the table comment is shown as a tooltip.
- **Crow's foot relation lines** drawn as curves between the exact columns that are
  related. The referencing side gets the crow's foot ("many"), the referenced side gets
  "exactly one", or "zero or one" when the foreign key column is nullable.
- **Automatic layered layout.** Referenced tables go left, referencing tables go right,
  and the order inside each column is chosen to reduce crossings. Tables without
  relations are packed into a grid below.
- **Click a table to pin its relations.** Its lines, related tables and the linked columns
  stay highlighted while everything else dims — no need to keep hovering. Click it again,
  click the background or press `Esc` to release.
- **Related only.** With a table selected, hide everything except that table and its
  direct neighbors, arranged around it. Click a neighbor to re-center on it and walk the
  graph one table at a time. Leaving the mode restores your saved layout.
- **Keys only** mode hides non-key columns, which makes large schemas readable.
- **Search** tables by name; press `Enter` to jump to the first match.
- **Export** the visible diagram as **SVG** or as a 2x **PNG**. The file matches what you
  see, including the active mode and theme.
- **Drag, pan, zoom** and **fullscreen**. Table positions and the zoom level are
  remembered per database.
- **Follows the active design.** Colors are read from the rendered page, so light designs,
  dark designs, the `designs` plugin and the `dark-switcher` plugin all work.

| Related only | Dark design |
| --- | --- |
| ![Related only mode](docs/screenshot-only.png) | ![Dark design](docs/screenshot-dark.png) |

## Requirements

- **Adminer 5.0 or newer** (the plugin uses the `Adminer\` namespace introduced in 5.0).
  It does **not** work with Adminer 4.x.
- **PHP 7.2+**
- A current browser. The styling uses CSS `color-mix()`, so Chrome/Edge 111+,
  Firefox 113+ or Safari 16.2+.
- Relation lines need foreign keys reported by the driver. **MySQL/MariaDB** and
  **PostgreSQL** are what the plugin is built and tested against; other drivers show the
  tables and whatever foreign keys they report. Views are not drawn, same as in Adminer's
  own schema page.

## Installation

Download [`schema-diagram.php`](schema-diagram.php) and do **one** of the following.

**Autoloaded plugin directory (simplest).** Put the file into an `adminer-plugins/`
directory next to your `adminer.php`:

```
adminer.php
adminer-plugins/
    schema-diagram.php
```

Adminer 5 loads every plugin in that directory automatically. Nothing else to configure.

**Explicit plugin list.** If you build the plugin list yourself in `adminer-plugins.php`
(or in your own `adminer_object()`), the class name is `AdminerSchemaDiagram`:

```php
return [
    new AdminerSchemaDiagram(),
];
```

Then open any database and click **Database schema**.

## Using the diagram

| Action | Result |
| --- | --- |
| Click a table | Pins its relations, related tables and linked columns |
| Click it again / click the background / `Esc` | Releases the selection |
| Hover a table | Previews its relations |
| Hover a relation line | Highlights the two columns it connects; the tooltip shows the constraint |
| Click a relation line | Centers the referenced table |
| Click the table name | Opens the table structure in Adminer |
| Drag a table | Moves it; the position is saved for this database |
| Drag the background | Pans the canvas |
| Scroll | Zooms around the pointer |
| **Related only** | Shows just the selected table and its direct neighbors |
| **Keys only** | Hides columns that are not part of a key |
| **Auto layout** | Throws away the saved positions and lays the diagram out again |
| **Fit** | Zooms to fit everything on screen |
| **PNG** / **SVG** | Downloads the visible diagram |
| Search + `Enter` | Centers the first matching table |

Exported files are named `schema-<database>.png`, or
`schema-<database>-<table>.png` when exported from **Related only**.

## How it works

On the schema page the plugin's `head()` hook collects tables, columns and foreign keys
(the same sources Adminer's own schema page uses), embeds them as JSON, hides the native
diagram and renders its own. Layout, highlighting and export are plain JavaScript in the
page — the plugin never queries anything on its own beyond the page load, and never talks
to a third-party server.

Layout positions, zoom and the **Keys only** setting are stored in the browser's
`localStorage` under `adminer-erd:<server>|<database>|<schema>`. Clearing your browser
data or pressing **Auto layout** resets them.

PNG/SVG export builds a standalone SVG from the rendered layout (plain shapes and text —
no `foreignObject`), and rasterizes it through a `<canvas>` for the PNG. Colors are read
from the rendered page, so exports match your theme.

## Troubleshooting

**The colors look wrong in my custom design.** The plugin picks up the text color, the
background and the link color from the page. A design that styles links the same as body
text falls back to a default blue accent. Open an issue with the design name and a
screenshot.

**A relation is missing.** Only foreign keys inside the current database and schema are
drawn; keys pointing at another database are skipped, as they are in Adminer's own
diagram. Relations that exist only in your application code — no `FOREIGN KEY` in the
database — cannot be detected.

**A huge schema is slow or cramped.** Turn on **Keys only**, select a table and use
**Related only**, or search for the table you need. Every table is a DOM element, so
several hundred tables will feel heavier than a dozen.

**Nothing changed after installing.** Make sure it is Adminer 5 (Adminer 4 plugins use no
namespace and this file will not load), and that the file sits in a directory Adminer
actually reads. Check the browser console for errors.

## Contributing

Issues and pull requests are welcome. The plugin is deliberately a single self-contained
file — CSS and JS live in heredocs inside the class so that installing stays a one-file
copy. Please keep it that way, follow the surrounding code style (tabs, Adminer's plugin
conventions), and test against both a light and a dark design before sending a change.

## License

Dual licensed, same as Adminer itself: [Apache License 2.0](LICENSE-APACHE) or
[GPL version 2](LICENSE-GPL), at your option.

## Credits

Built for [Adminer](https://www.adminer.org/) by Jakub Vrana. The relation notation
follows standard crow's foot ER diagrams.
