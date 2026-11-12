# Changelog

All notable changes to this project will be documented in this file.

## [1.0.3] - 2026-11-12

### Added

- The add-on registry. An add-on declares itself in its own params (`'blackcube/dcore' => ['addons' => [id => class]]`) and carries `Addons\AddonInterface`: its name, its Composer package, the namespace of its migrations. The `addons` table (`M000000000025CreateAddons`, model `Addon`) holds the active ones, a row is an active add-on. `Services\AddonService` reads the declared, the active and the orphan add-ons, and activates, updates, deactivates or forgets one: activation plays the migrations of its namespace in order then writes its row with the installed version, deactivation reverts them most recent first and is refused before anything is touched when one of them is not revertible.
- Database upgrades. A module declares its upgrades in its params (`'blackcube/dcore' => ['upgrades' => [class]]`), each an `Interfaces\UpgradeInterface` whose `apply()` is idempotent. `Services\DatabaseUpgrade` keeps the applied ones in the `upgrades` table (model `Upgrade`) it creates itself, since `upgrade` runs before `migrate:up`. `Upgrades\MigrationRenameUpgrade` rewrites the migration history after migration classes were renamed in a package, without running any migration. The console command `upgrade` (`Commands\UpgradeCommand`, group `params-console`) applies the pending upgrades and prints `ok` for the recorded ones.
- `yiisoft/yii-console` and `yiisoft/injector` are dependencies of the package.

### Changed

- `Interfaces\SsrRouteProviderInterface` carries `getErrorHandlerInfo(int $statusCode): ?array`, the error handler behind a status code. An implementation outside `blackcube/ssr` adds the method.

## [1.0.2] - 2026-10-28

### Fixed
- JSON-LD `WebSite` carries `name` (label of the GlobalXeo entry, no longer the site name of the host) and `url` (base URL of the host).

## [1.0.1] - 2026-10-27

### Fixed
- JSON-LD `Organization` carries `name` (label of the GlobalXeo entry) and `url` (base URL of the host).

## [1.0.0] - 2026-10-25

Initial release.
