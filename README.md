# relations
Relations for Codeigniter 4

[![Build status](https://github.com/daycry/relations/actions/workflows/php.yml/badge.svg?branch=master)](https://github.com/daycry/relations/actions/workflows/php.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/relations/badge.svg?branch=master)](https://coveralls.io/github/daycry/relations?branch=master)
[![Downloads](https://poser.pugx.org/daycry/relations/downloads)](https://packagist.org/packages/daycry/relations)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/relations)](https://packagist.org/packages/daycry/relations)
[![GitHub stars](https://img.shields.io/github/stars/daycry/relations)](https://packagist.org/packages/daycry/relations)
[![GitHub license](https://img.shields.io/github/license/daycry/relations)](https://github.com/daycry/relations/blob/master/LICENSE)

## Quick Start

1. Install with Composer: `> composer require daycry/relations`
2. Add the trait to your model: `use \Daycry\Relations\Traits\ModelTrait`
3. Load relations: `$users = $userModel->with('groups')->findAll();`
4. Add the trait to your entity: `use \Daycry\Relations\Traits\EntityTrait`
5. Load relations: `foreach ($user->groups as $group)`

(See also Examples at the bottom)

## Installation

Install easily via Composer to take advantage of CodeIgniter 4's autoloading capabilities
and always be up-to-date:
```shell
    > composer require daycry/relations
```

Or, install manually by downloading the source files and adding the directory to
**app/Config/Autoload.php***.

## Configuration (optional)

The library's default behavior can be altered by extending its config file. Copy
**examples/Relations.php** to **app/Config/** and follow the instructions
in the comments. If no config file is found in **app/Config** the library will use its own.

### Schemas

All the functionality of the library relies on the generated database schema. The schema comes from
[Daycry\Schemas](http://github.com/daycry/schemas) and can be adjusted
based on your needs (see the **Schemas** config file). If you want to use the auto-generated
schema your database will have follow conventional naming patterns for foreign keys and
pivot/join tables; see [Daycry\Schemas](http://github.com/daycry/schemas)
for details.

## Usage

Relation loading is handled by traits that are added to their respective elements.

### Eager/Model

**ModelTrait** adds relation loading to your models by extending the default model `find*`
methods and injecting relations into the returned results. Because this happens at the model
level, related items can be loaded ahead of time in batches ("eager loading").

Add the trait to your models:
```php
	use \Daycry\Relations\Traits\ModelTrait
```

Related items can be requested by adding a `$with` property to your model:
```php
	protected $with = 'groups';
	// or
	protected $with = ['groups', 'permissions'];
```

... or by requesting it on-the-fly using the model `with()` method:

```php
$users = $userModel->with('groups')->findAll();
foreach ($users as $userEntity)
{
	echo "User {$user->name} has " . count($user->groups) . " groups.";
...
```

As you can see the related items are added directly to their corresponding object (or array)
returned from the framework's model.

### Lazy/Entity

**EntityTrait** adds relation loading to individual items by extending adding magic `__get()`
and `__call()` methods to check for matching database tables. Because this happens on each
item, related items can be retrieved or updated on-the-fly ("lazy loading").

Add the trait and its necessary properties to your entities:
```php
	use \Daycry\Relations\Traits\EntityTrait
	
	protected $table      = 'users';
	protected $primaryKey = 'id';
```

Related items are available as faux properties:
```php
	$user = $userModel->find(1);
	
	foreach ($user->groups as $group)
	{
		echo $group->name;
	}
```

... and can also be updated directly from the entity:
```php
	$user->addGroup(3);
	
	if ($user->hasGroups([1, 3]))
	{
		echo 'allowed!';
	}
	
	$user->setGroups([]);
```

Available magic method verbs are: `has`, `set`, `add`, and `remove`, and are only applicable
for "manyToMany" relationships. For `hasMany` now `set/add/remove` are supported (actualizan la FK del hijo poniendo null cuando se quita).

### Interface & Extensibility (>= next release)

To explicitly mark participating models/entities implement:
```php
use Daycry\Relations\Contracts\RelatableInterface;
class UserModel extends Model implements RelatableInterface { use ModelTrait; }
class User extends Entity implements RelatableInterface { use EntityTrait; }
```
If the interface is missing an exception (`Relations.notRelatable`) is thrown.

Optional extension hooks (define only if needed):
```php
protected function afterRelations(array &$rows, array $loadedTables): void { /* mutate or log */ }
protected function afterEntityRelations(string $table, mixed &$result, bool $keysOnly): void { /* transform */ }
```

### Instrumentation
Static metrics are tracked while the process lives:
```php
$metrics = \Daycry\Relations\Traits\BaseTrait::getRelationMetrics();
// ['calls' => 12, 'tables' => ['groups' => 5, 'permissions' => 7]]
\Daycry\Relations\Traits\BaseTrait::getRelationMetrics(true); // resets
```
Use in tests or profiling to detect N+1 patterns.
Enable via config (`collectMetrics = true`). Disabled by default to avoid overhead in producción.

### Introspección de Relaciones & Carga Forzada (Nuevo)

Para descubrir y trabajar dinámicamente con las relaciones sin revisar el esquema manualmente:

```php
$model = new UserModel();
$names = $model->listRelations();
// p.ej: ['groups','permissions','profile']

$details = $model->relationDetails(); // todas
/* Ejemplo de $details['groups']:
[
	'type' => 'manyToMany',
	'singleton' => false,
	'pivots' => [ ['groups_users','group_id','groups_users','user_id'], ... ],
	'model' => App\Models\GroupModel::class,
]
*/

// Solo una relación concreta
$groupMeta = $model->relationDetails('groups');
```

En entidades puedes forzar ("eagerizar") la carga de una o varias relaciones después de instanciarla usando `load()`:

```php
$user = $userModel->find(1); // todavía sin 'groups'
$user->load(['groups','permissions']); // fuerza carga
echo count($user->groups);
```

`load()` normaliza nombres singulares/plurales/camelCase y evita volver a cargar relaciones ya presentes; devuelve la propia entidad para encadenar.

### CLI Debug / Inspección

Se incluye un comando para inspeccionar relaciones detectadas por el esquema:

```bash
php spark relations:inspect model=App\\Models\\UserModel
php spark relations:inspect model=App\\Models\\UserModel --details
php spark relations:inspect model=App\\Models\\UserModel --details --only=groups,permissions
php spark relations:inspect model=App\\Models\\UserModel --details --json > relations.json
```

Flags:
- `--details` imprime tipo, singleton, pivots y modelo asociado.
- `--only=lista` filtra por relaciones concretas.
- `--json` salida en JSON (útil para scripts / CI).

Esto facilita auditar el esquema cargado y depurar problemas de configuración.

### Performance & Caching (Nuevo)

Config flags adicionales:

```php
public bool $collectMetrics = false;        // métricas de carga
public bool $cacheRelationResults = false;  // cache en memoria por request de resultados reindexados
public string $belongsToStrategy = 'join';  // reservado para futura estrategia 'in'
```

Activar `cacheRelationResults` reduce queries repetidas del mismo conjunto de IDs/relación en un mismo ciclo de petición:

```php
$config = config('Relations');
$config->cacheRelationResults = true;
$users = $userModel->with('groups')->findAll();
// segunda llamada reutiliza resultados en memoria (sin nueva query)
$again  = $userModel->with('groups')->findAll();
```

Limpiar manualmente (por ejemplo en un Job largo):
```php
\App\Models\UserModel::clearRelationResultCache();
```

Optimizaciones internas implementadas:
- Memoización de schema en la instancia.
- Reutilización del objeto Relation en carga eager.
- Cache de flag `collectMetrics`.
- Soporte inicial de `manyThrough` (encadena pivotes con joins).

### Type Overrides (hasMany -> hasOne) / Runtime Cardinality Promotion (New)

Sometimes the generated schema only differentiates broad categories (`hasMany`, `belongsTo`, `manyToMany`) and you need to treat a specific `hasMany` as a one-to-one at runtime (e.g. there is effectively only one related row, enforced via a UNIQUE index, or by business rule). You can promote the relation to behave like a `hasOne` (or force any other supported type) without modifying the schema generation by declaring a simple override on the model.

Add the optional property `relationTypeOverrides` keyed by target table name:

```php
class ServicerModel extends Model implements RelatableInterface {
	use \Daycry\Relations\Traits\ModelTrait;

	protected $table = 'servicers';
	protected $primaryKey = 'id';

	// Promote the default hasMany (servicers -> lawyers) to a hasOne
	protected array $relationTypeOverrides = [
		'lawyers' => 'hasOne',
	];
}
```

Behavior changes:
* Eager load: `$servicer = (new ServicerModel())->with('lawyers')->find(1);` injects a singular property `$servicer->lawyer` instead of `$servicer->lawyers` (no array wrapper).
* Lazy load (entity path): Accessing `$entity->lawyer` will resolve the promoted singleton (same collapse logic based on `singleton` flag).
* The underlying schema Relation is never mutated: a cloned relation object is created and cached, so other models/entities using the original relation remain unaffected.

Supported override types: `hasOne`, `hasMany`, `belongsTo`, `manyToMany`, `manyThrough`.

Notes & Recommendations:
* Only override when you are certain the cardinality is truly one-to-one (ideally enforced by a UNIQUE constraint) to avoid silent data loss (only the first row will be kept in the singleton collapse).
* Overrides are per-model: two different models referencing the same table can choose different type promotions.
* Current implementation changes `type` and recomputes `singleton` (`hasOne` / `belongsTo` => true). Forcing `hasMany` while setting a singleton is intentionally not supported—declare `hasOne` instead for clarity.
* No inference is performed: you explicitly state the desired type; pivots and keys remain those provided by the schema.
* Introspection (`relationDetails()`) will (future enhancement) be able to expose original vs override type—right now it reflects the effective (overridden) type only.

Minimal example verifying promotion behavior:
```php
$servicer = (new ServicerModel())->with('lawyers')->find(1);
// Access singular promoted relation
echo $servicer->lawyer->name;
// Property 'lawyers' will not be set since the relation is treated as singleton.
```

If you later decide to revert, remove the array entry and the schema's original relation type is used again.

## Returned items

**Schemas** will attempt to associate your database tables back to their models, and if
successful, **Relations** will use each table's model to find the related items. This keeps
consistent the return types, events, and other aspects of your models. In addition to the
return type, **Relations** will also adjust related items for singleton relationships:
```php
// User hasMany Widgets
$user = $userModel->with('widgets')->find($userId);
echo "User {$user->name} has " . count($user->widgets) . " widgets.";

// ... but a Widget belongsTo one User
$widget = $widgetModel->with('users')->find($widgetId);
echo $widget->name . " belongs to " . $widget->user->name;
```

### Nesting

**ModelTrait** supports nested relation calls, but these can be resource intensive so may
be disabled by changing `$allowNesting` in the config. With nesting enabled, any related
items will also load their related items (but not infinitely):
```php
/* Define your models */
class UserModel
{
	use \Daycry\Relations\Traits\ModelTrait;

	protected $table = 'users';
	protected $with  = 'widgets';
...
	
/* Then in your controller */
$groups = $groupModel->whereIn('id', $groupIds)->with('users')->findAll();

foreach ($groups as $group)
{
	echo "<h1>{$group->name}</h1>";
	
	foreach ($group->users as $user)
	{
		echo "{$user->name} is a {$user->role} with " . count($user->widgets) . " widgets.";
	}
}
```

### Soft Deletes

If your target relations correspond to a CodeIgniter Model that uses [soft deletion](https://codeigniter.com/user_guide/models/model.html#usesoftdeletes)
then you may include the table name in the `array $withDeletedRelations` property to include
soft deleted items. This is particularly helpful for tight relationships, like when an item
`belongsTo` another item that has been soft deleted. `$withDeletedRelations` works on both
Entities and Models.

## Performance

*WARNING*: Be aware that **Relations** relies on a schema generated from the **Schemas**
library. While this process is relatively quick, it will cause a noticeable delay if a page
request initiates the load. The schema will attempt to cache to prevent this delay, but
if your cache is not configured correctly you will likely experience noticeable performance
degradation. The recommended approach is to have a cron job generate your schema regularly
so it never expires and no user will trigger the un-cached load, e.g.:
```shell
php spark schemas
```

See [Daycry\Schemas](http://github.com/daycry/schemas) for more details.

### Eager or Lazy Loading

You are responsible for your application's performance! These tools are here to help, but
they still allow dumb things.

Eager loading (via **ModelTrait**) can create a huge performance
increase by consolidating what would normally be multiple database calls into one. However,
the related items will take up additional memory and can cause other bottlenecks or script
failures if used indiscriminately.

Lazy loading (via **EntityTrait**) makes it very easy to work with related items only when
they are needed, and the magic functions keep your code clear and concise. However, each entity
issues its own database call and can really start to slow down performance if used over
over.

A good rule of thumb is to use **ModelTrait** to preload relations that will be handled
repeatedly (e.g. in loops) or that represent a very small or static dataset (e.g. a set of
preference strings from 10 available). Use **EntityTrait** to handle individual items, such
as viewing a single user page, or when it is unlikely you will use relations for most of the
items.
