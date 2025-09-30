# AI Coding Assistant Instructions

Purpose: This document orients AI agents contributing to `daycry/relations` (a CodeIgniter 4 library that provides automatic database entity relationship loading using generated schemas).

## 1. Big Picture
- Library adds transparent eager (ModelTrait) and lazy (EntityTrait) relationship handling on top of CodeIgniter 4 Models & Entities.
- Relies on an external generated schema (`daycry/schemas`) to discover tables, pivots, relationship types, and model classes.
- Core flow (model eager load): ModelTrait finder -> collect primary keys -> `_getRelations()` builds query per requested related table -> injects related data keyed by originating id -> singletons collapsed to singular property name.
- Core flow (entity lazy load): Unknown property (magic `__get`) -> table name pluralization -> call `relations()` -> `_getRelations()` -> cache result inside entity attributes.
- Relationship types implemented: `hasMany`, `belongsTo`, `manyToMany` (and placeholder for `manyThrough`). Singletons (belongsTo / hasOne) mapped to singular form property.
- Prevents infinite nesting via `without()` list and config `allowNesting` boolean.

## 2. Key Files
- `src/Traits/BaseTrait.php`: Shared low-level mechanics: schema access, relationship resolution, query assembly, reindexing.
- `src/Traits/ModelTrait.php`: Eager loading integration with CI4 Model lifecycle (`find`, `findAll`, `first`). Temp per-query state: `tmpWith`, `tmpWithout`, `tmpReindex`.
- `src/Traits/EntityTrait.php`: Lazy loading & magic verbs (`has|set|add|remove`) for many-to-many. Property/method name parsing & pluralization rules.
- `src/Config/Relations.php`: Config toggles (silent, allowNesting, defaultReturnType).
- `src/Exceptions/RelationsException.php` + `src/Language/en/Relations.php`: Error / language messages.

## 3. Dependencies & Assumptions
- Requires PHP ^8.1, CodeIgniter 4 (dev dependency for tests), and `daycry/schemas` (must have a generated & cached schema for performant relation loading).
- Uses CI4 helpers: `plural()`, `singular()`. Make sure `inflector` helper is loaded (BaseTrait ensures).
- Schema service accessed via `service('schemas')`; ensure application has configured cache so schema generation is not done on every request.

## 4. Public Extension Points / Patterns
- To enable eager loading: add `use \Daycry\Relations\Traits\ModelTrait;` to a CI4 Model, optionally define `$with` (string or array) for default relations.
- To enable lazy loading: add `use \Daycry\Relations\Traits\EntityTrait;` to an Entity plus define `$table` & `$primaryKey` and optionally `$withDeletedRelations`.
- Per-query customization: `$model->with('groups')->without('permissions')->findAll();`
- Disable relations for a query: `$model->with(false)->findAll();`
- Disable reindexing (e.g. when using joins): `$model->join('other_table', '...')->findAll();` (join call sets `tmpReindex=false`). Or explicitly `$model->reindex(false);`
- Access single related item: `$widget->user` (belongsTo) vs. multi: `$user->groups`.
- Magic verbs for many-to-many: `$user->addGroups([3,4]); $user->removeGroup(2); $user->setGroups([1,5]); if ($user->hasGroups([1,5])) {...}` (method name parsing: verb + StudlySingular|Plural target -> plural snake table name).

## 5. Internal Mechanics Highlights
- `_getRelationship($table)` validates: schema loaded, target table exists, relation exists, pivots defined.
- `_getRelations($table, $ids)` builds selective query: chooses model if defined in schema to trigger model events & return correct returnType; else generic builder with `defaultReturnType`.
- Reindexing: For eager loads results indexed by originating primary key unless disabled.
- Singletons marked in schema -> stored in entity/model result as singular property (using `singular($table)`).
- Caching: Results of lazy load stored in entity attributes so subsequent access doesn't query again unless mutated.
- Mutation operations (`_set`, `_add`, `_remove`) only implemented for `manyToMany`; `hasMany` cases currently placeholders (WIP). Code should throw for invalid operations via language strings.

## 6. Error & Edge Case Conventions
- Missing `$table` or `$primaryKey` in consumer triggers `RelationsException::forMissingProperty`.
- Unknown requested relation/table triggers `RelationsException` variants.
- If schema service or data absent: runtime exception with `Relations.noSchemas` message.
- Empty eager load result: relations logic short-circuits and resets temp state.
- Empty singleton wrapper pattern: when calling `find($id)` result is wrapped into array, relations added, then unwrapped.

## 7. Testing & CI Workflow
- Run full test suite: `composer test` (executes `vendor/bin/phpunit`).
- Static analysis & style (composite): `composer analyze` (phpstan + psalm + rector dry run), `composer cs` (dry-run), `composer cs-fix` to apply.
- Mutation testing: `composer mutate` (uses Infection with existing coverage in `build/phpunit`).
- Dependency graph inspection: `composer inspect` (Deptrac), duplication: `composer deduplicate` (phpcpd).
- CI pipeline (`.github/workflows/php.yml`) runs PHPUnit & Coveralls on PHP 8.2.

## 8. Performance Considerations
- First schema load may be slow; recommend generating & caching via cron: `php spark schemas` (in host app).
- Use eager loading for loops/multiple entities; use lazy loading for occasional access. Avoid mixing deep nesting unless `allowNesting` enabled intentionally.
- Prevent N+1 by batching relations via `with()` on models.

## 9. Adding New Features Safely
- When adding a new relation type (e.g. `hasOne` or completing `manyThrough`): update schema interpretation in `BaseTrait::_getRelations` switch and mark singleton appropriately.
- If implementing `hasMany` mutation support, reflect on current TODO placeholders in `_set`, `_add`, `_remove` in `EntityTrait`.
- Keep language strings updated for new exceptions; add tests under `tests/` mirroring existing style (see entity & model tests directories for patterns).

## 10. Common Pitfalls (Agent Watchlist)
- Forgetting to reset temp state when adding new finder overrides -> memory of previous `with/without` leaks across queries.
- Introducing recursion by not updating `without()` when adding nested loads.
- Returning mixed array/object types when model `returnType` differs — always derive `$returnType` from actual model instance used.
- Mutating entity relations without clearing cached attribute -> ensure `unset($this->attributes[$tableName])` after pivot changes.

## 11. Minimal Usage Example
```php
class UserModel extends \CodeIgniter\Model {
  use \Daycry\Relations\Traits\ModelTrait; 
  protected $table = 'users';
  protected $primaryKey = 'id';
  protected $with = ['groups'];
}

class User extends \CodeIgniter\Entity { 
  use \Daycry\Relations\Traits\EntityTrait; 
  protected $table = 'users';
  protected $primaryKey = 'id';
}

$users = (new UserModel())->with(['groups','permissions'])->findAll();
$user = (new UserModel())->find(1);
foreach ($user->groups as $g) { /* ... */ }
```

## 12. When Unsure
- Inspect schema object (from `service('schemas')->get()`) to confirm relation pivots & types before modifying loader logic.
- Favor extending traits instead of duplicating logic; central behavior lives in `BaseTrait`.

---
Feedback: Indica si necesitas más detalle sobre el esquema (`daycry/schemas`), ejemplos de tests, o implementación de mutaciones `hasMany`.
