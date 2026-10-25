# dcore — Integration

## Split

- Package: the models, entities, queries, scopes, services, helpers, attributes, the 25 migrations, the config-plugin files, the schema label translations.
- Application: the database connection, the container services listed below, `Injector::init($container)`, the migration namespace in its params, the HTTP layer (`blackcube/ssr` for the front, `blackcube/dboard` for the admin), the `PROJECT` parameters.

## Yii (config-plugin)

`config/configuration.php`: `params` → `common/params.php` (returns `[]`), `di` → `common/di.php`, `bootstrap` → `common/bootstrap.php`, source directory `config`.

DI (`config/common/di.php:22-27`):

| Interface | Implementation |
|---|---|
| `Interfaces\SlugGeneratorInterface` | `Services\HazeltreeSlugGenerator` |
| `Interfaces\PreviewManagerInterface` | `Services\PreviewManager` (needs `PreviewContextInterface`) |
| `Interfaces\PreviewContextInterface` | `Services\PreviewContext` (needs `Yiisoft\Session\SessionInterface`) |
| `Interfaces\JsonLdBuilderInterface` | `Services\JsonLdBuilder` |

Bootstrap (`config/common/bootstrap.php:15-21`): `QueryExpressions::register($container->get(ConnectionInterface::class))` when the container has a connection.

## What the container must provide

| Service | Used by |
|---|---|
| `Yiisoft\Db\Connection\ConnectionInterface`, plus `ConnectionProvider::set()` for `yiisoft/active-record` | every model, `ReusableDependency`, `PublishableScope`, `ImportService` |
| `Blackcube\Injector\Injector::init($container)` | `ScopableQuery` (cache), the scopes (preview), `ReusableDependency`, `FileFormatter`, `ElasticMdService` (logger, `FileService`), `CacheFile` of `blackcube/fileprovider` |
| `Yiisoft\Cache\CacheInterface` (optional) | cached Entity queries; without it every query runs uncached |
| `Yiisoft\Session\SessionInterface` | `PreviewContext` |
| `Blackcube\FileProvider\Interfaces\FileProviderInterface` (config-plugin of `blackcube/fileprovider`) | `FileService` |
| `Interfaces\SsrRouteProviderInterface` (implemented by `blackcube/ssr`) | `HandlerDescriptorFactory` |
| `Psr\Log\LoggerInterface` (optional) | `ImportService`, `ElasticMdService` |
| `Services\FileService`, `MapperService`, `ImportService`, `ElasticMdService`, `XeoRefreshService`, `HandlerDescriptorFactory`, `Services\Xeo\*` | autowired, no definition in the package |

Migrations: add `Blackcube\Dcore\Migrations` to `yiisoft/db-migration` `sourceNamespaces` and run `./yii migrate/up` (`docs/installation.md:29-45`). Parameters: `PROJECT` / `NAME` and `PROJECT` / `SUMMARY` rows in `parameters` feed `llms.txt`.

## Who uses what

| Consumer | Layer |
|---|---|
| `blackcube/ssr` (front) | `Entities\*`, `Helpers\Xeo`, `HandlerDescriptorFactory`, `Services\Xeo\*`, `JsonLdBuilder`, `Element::getUrlsFromRoutes()` |
| `blackcube/dboard` (admin) | `Models\*`, `FileService`, `XeoRefreshService`, `ImportService`, `ElasticMdService`, `MapperService`, `HazeltreeSlugGenerator`, `PreviewManager::SESSION_KEY` |
| `blackcube/mcp` | `Models\*`, `MapperService::describe()` / `export()`, `ElasticMdService`, `XeoRefreshService`, `Helpers\Element` with `ModelKind::Model` |
| `blackcube/graphql` | `Entities\*` |

## Tests

Three Codeception suites, `Unit`, `Models`, `Migration` (`tests/*.suite.yml`, `Asserts` module), run with `vendor/bin/codecept run`; the database connection and the query expression builders come from `tests/_bootstrap.php`.
