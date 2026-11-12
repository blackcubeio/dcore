# Add-ons and upgrades

An **add-on** is a Composer package that extends the CMS. It is **available** once its config is merged into the application, **active** once its migrations are played and its row exists in the `addons` table. dcore keeps the registry and plays the migrations; the admin (dboard) shows the add-ons and triggers the activation.

## Declaring an add-on

The add-on declares itself in its own `params`, merged by `yiisoft/config`:

```php
return [
    'blackcube/dcore' => [
        'addons' => [
            'mcp' => McpAddon::class,
        ],
    ],
];
```

The key is the **id** of the add-on (32 characters at most, the primary key of the registry), the value its class, which implements `Blackcube\Dcore\Addons\AddonInterface`:

| Method | Role |
|---|---|
| `getName(): string` | Name shown to the administrator. |
| `getPackage(): string` | Composer package name, used to read the installed version. |
| `getMigrationsNamespace(): ?string` | Namespace of its migrations, `null` when it has no table of its own. |

The interfaces of the admin (RBAC, sidebar, routes, OAuth2 population) belong to dboard, in `Blackcube\Dboard\Interfaces\`, and are optional. A package that is not an add-on carries them as a **module**, declared in `'blackcube/dboard' => 'modules'`, always on: no migration, no activation, no row in the registry.

## Migrations of an add-on

- Named `M8` + 11 digits (see the numbering below), in the declared namespace.
- All revertible (`RevertibleMigrationInterface`), translations included: deactivation reverts them.
- Played by dcore at activation, never by `migrate:up`: the namespace is **not** listed in the `sourceNamespaces` of the application.

## Registry

Table `addons`: `id` (string 32, primary key), `version` (installed Composer version at the last run of its migrations), `dateCreate`, `dateUpdate`. A row is an active add-on, there is no `active` column. Models `Blackcube\Dcore\Models\Addon` and `Blackcube\Dcore\Entities\Addon`.

`Blackcube\Dcore\Services\AddonService`, built by the DI with the `addons` list of the params:

| Method | Role |
|---|---|
| `getDeclared(): array` | id => class, as the params carry them. |
| `isDeclared(string $id): bool` | Declared and its class implements `AddonInterface`. A class gone from Composer is not declared. |
| `getClass(string $id): string` | The class of a declared add-on; `InvalidArgumentException` otherwise. |
| `getName(string $id): string` | The name the add-on shows; an orphan row shows its id. |
| `hasRegistry(): bool` | Whether the `addons` table exists. Before the dcore migrations are played, nothing is active. |
| `isActive(string $id): bool` | Declared and present in the registry. |
| `getActive(): array` | id => class of the active add-ons among the declared ones. |
| `getOrphans(): array` | Ids of the rows whose add-on is not declared any more. |
| `getInstalledVersion(string $id): ?string` | From `Composer\InstalledVersions`. |
| `hasPendingMigrations(string $id): bool` | Whether the installed package carries migrations the base has not played. |
| `activate(string $id): void` | Plays the pending migrations of the add-on, in order, then writes its row. |
| `update(string $id): void` | Plays the migrations a newer package brought, then records the installed version. |
| `deactivate(string $id): void` | Reverts its migrations, most recent first, then removes its row. |
| `forget(string $id): void` | Removes the row without touching the tables: for a package gone from Composer. |

Behaviour at the edges:

- A migration failing at activation stops the run: the migrations already played stay in the history, no row is written, a second activation resumes where it stopped.
- Deactivation is refused before anything is touched when one migration of the add-on is not revertible.
- `migrate:down` of the application does not see the migrations of the add-ons.
- The registry is read on each call, nothing is kept in memory.

## Numbering of the migrations

`yiisoft/db-migration` keeps the files named `M` + 12 digits and sorts them on the digits first. The first digit is the **type** of the module:

| digit | module |
|---|---|
| 0 | dcore |
| 1 | dboard |
| 6 | application |
| 8 | add-ons |
| 2 to 5, 7 | free |

Always `M` + type + 11 digits. dcore migrations come first, then dboard, then the application; add-ons are played by their activation.

## Upgrades

An **upgrade** is a change of the base that is not a migration: a rewrite of the migration history after classes were renamed, for example. A module (dcore, dboard, an add-on, the application) declares its upgrades in its params:

```php
return [
    'blackcube/dcore' => [
        'upgrades' => [
            RenameMigrations::class,
        ],
    ],
];
```

An upgrade implements `Blackcube\Dcore\Interfaces\UpgradeInterface`, a single `apply(): void` that stays **idempotent**: applied twice, it leaves the base unchanged.

`Blackcube\Dcore\Services\DatabaseUpgrade` keeps the applied upgrades in the `upgrades` table (`name` string 180, primary key, the class of the upgrade; `applyTime`), model `Blackcube\Dcore\Models\Upgrade`. The service creates the table itself when it is missing: `upgrade` runs before `migrate:up`, the table cannot come from a migration.

| Method | Role |
|---|---|
| `getDeclared(): array` | The declared classes, each once, in declaration order. |
| `getApplied(): array` | class => timestamp. |
| `getPending(): array` | Declared and not applied. |
| `isApplied(string $class): bool` | |
| `apply(string $class): void` | Applies then records; nothing happens when it is recorded already. |

### Renaming migrations

`Blackcube\Dcore\Upgrades\MigrationRenameUpgrade` is the base class of an upgrade that rewrites the history after migration classes were renamed in a package. A module derives it with the map of its renames:

```php
class RenameMigrations extends MigrationRenameUpgrade
{
    protected function getRenames(): array
    {
        return [
            'Blackcube\\Dboard\\Migrations\\M000000000000CreateAdministrators' => M100000000000CreateAdministrators::class,
        ];
    }
}
```

Only the old names still present in the history are replaced, oldest first, through `Migrator::updateHistory()`: no migration runs, a run interrupted before its record is completed by the next one.

### The `upgrade` command

```bash
./yii upgrade
```

Collects the upgrades of every module, prints `ok` for each one recorded in the `upgrades` table, applies and records the others. Sequence on an existing base after a package update:

```bash
composer update
./yii upgrade
./yii migrate/up
```

If `migrate:up` runs first on a base whose migrations were renamed, the first renamed migration is played again and stops on an existing table, nothing is applied: run `upgrade`, then `migrate:up` again.
