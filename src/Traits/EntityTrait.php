<?php

/**
 * This file is part of Daycry Relations.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Relations\Traits;

use ArgumentCountError;
use BadMethodCallException;
use RuntimeException;

trait EntityTrait
{
    use BaseTrait;

    /**
     * Check for known relations when the framework entity fails to match a requested property
     *
     * @param string $key The name of the requested property, i.e. table to check for relations
     *
     * @return mixed|null Return is determined by relation type, see relations()
     */
    public function __get(string $key)
    {
        // First check the framework's version
        $result = parent::__get($key);

        if ($result !== null || array_key_exists($key, $this->attributes)) {
            return $result;
        }

        // Get the schema
        $schema = $this->_schema();

        // Convert the key to table format
        $tableName = plural(strtolower($key));

        // Check for a matching table
        if (isset($schema->tables->{$tableName})) {
            return $this->relations($tableName);
        }

        return null;
    }

    /**
     * Complimentary property checker to __get()
     *
     * @param string $key The name of the requested property, i.e. table to check for relations
     *
     * @return bool Whether the entity property or table relation exists
     */
    public function __isset(string $key): bool
    {
        // First check the framework's version
        if (parent::__isset($key)) {
            return true;
        }

        // Get the schema
        $schema = $this->_schema();

        // Convert the key to table format
        $tableName = plural(strtolower($key));

        // Check for a matching table
        return isset($schema->tables->{$tableName});
    }

    /**
     * Intercept undefined methods and check them against known relations
     *
     * @param string $name      The name of the missing method
     * @param array  $arguments Any arguments passed. Expects only one: an array of keys or null
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $this->_verifyRelatable();

        $verbs = ['has', 'set', 'add', 'remove'];

        // Parse the name to check for supported relation verbs
        $verb = false;

        foreach ($verbs as $test) {
            if (str_starts_with($name, $test)) {
                $verb = $test;
                break;
            }
        }

        // If no verb matched then this is an unrelated call
        if (empty($verb)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
        }

        // Trim the verb and check what is left
        $target = substr($name, strlen($verb));

        // Check for an uppercase character next
        // https://stackoverflow.com/questions/2814880/how-to-check-if-letter-is-upper-or-lower-in-php
        if (! preg_match('~^\p{Lu}~u', $target)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
        }

        // Validate argument count
        if (count($arguments) > 1) {
            throw new ArgumentCountError(sprintf('Too many arguments to function %s::%s, %s passed and at most 1 expected.', static::class, $name, count($arguments)));
        }

        // Format target as a valid table reference
        $target = strtolower(preg_replace('/(?<!^)[A-Z]+/', '_$0', plural($target)));

        // Flatten the arguments to just the keys
        $keys = reset($arguments);
        if (empty($keys)) {
            $keys = null;
        }

        // Wrap singletons in an array
        elseif (! is_array($keys)) {
            $keys = [$keys];
        }

        // Make sure there are no duplicates
        else {
            $keys = array_unique($keys);
        }

        // Pass to the appropriate function
        $method = '_' . $verb;

        return $this->{$method}($target, $keys);
    }

    /**
     * Get related item(s) from a known related table
     * Note that __get() has already checked $this->attributes for $tableName
     *
     * @param string $tableName The name of the table to check for relations
     * @param bool   $keysOnly  Whether to return the entire rows or just primary keys
     *
     * @return mixed Function return is determined by the relation type and keysOnly:
     *               array of items or keys (hasMany, manyToMany)
     *               single item or key (belongsTo, hasOne)
     *               null if no matches
     */
    public function relations(string $tableName, $keysOnly = false)
    {
        // If entity primary key is not set then finish
        if (! isset($this->attributes[$this->primaryKey])) {
            return null;
        }

        // Use BaseTrait to get related items
        $items = $this->_getRelations($tableName);

        // Collapse to just this entity's relations
        $items = reset($items);

        // Intercept empty results and force them to null
        if (empty($items)) {
            return null;
        }

        // Check the relationship to intercept singletons
        if ($this->_getRelationship($tableName)->singleton) {
            // Collapse to the row itself
            $name = singular($tableName);

            // Save it for future use
            $this->attributes[$name] = $items;

            if ($keysOnly) {
                return is_array($items) ? $items[$this->primaryKey] : $items->{$this->primaryKey};
            }

            return $items;
        }

        // Save them for future use
        $this->attributes[$tableName] = $items;
        $result = $keysOnly ? array_column($items, $this->primaryKey) : $items;

        // Hook: allow entity-specific post-processing of loaded relation
        if (method_exists($this, 'afterEntityRelations')) {
            $this->afterEntityRelations($tableName, $result, $keysOnly);
        }

        return $result;
    }

    /**
     * Force load one or many relations (lazy eager hybrid) and return $this for chaining.
     * Accepts string or array of table names (in any singular/plural/camel case, will normalize).
     */
    public function load(string|array $relations): static
    {
        $list = is_array($relations) ? $relations : [$relations];
        foreach ($list as $name) {
            // Normalize similar to __get: convert to table format (plural, lower, snake)
            $table = plural(strtolower(preg_replace('/(?<!^)[A-Z]+/', '_$0', $name)));
            // Skip if already loaded
            if (array_key_exists($table, $this->attributes) || array_key_exists(singular($table), $this->attributes)) {
                continue;
            }
            $this->relations($table);
        }
        return $this;
    }

    /**
     * Check if this entity has specific item(s) in a table
     * Only valid for non-singletons (i.e. hasMany, manyToMany)
     *
     * @param string     $tableName The name of the target table
     * @param array|null $keys      Primary keys to match, or null for "any"
     *
     * @return mixed
     */
    protected function _has(string $tableName, ?array $keys = null)
    {
        // Get related items
        $items = $this->attributes[$tableName] ?? $this->relations($tableName);

        // If relation resolution returned null treat as empty set
        if ($items === null) {
            return false;
        }

        // If not items matched then always fail
        if (empty($items)) {
            return false;
        }

        // If no keys were requested then we already have at least one entity
        if (empty($keys)) {
            return true;
        }

        // Otherwise count how many of the requested keys are matched
        $matched = 0;

    foreach ($this->attributes[$tableName] as $entity) {
            $key = is_array($entity) ? $entity[$this->primaryKey] : $entity->{$this->primaryKey};

            if (in_array($key, $keys, false)) {
                $matched++;

                if ($matched >= count($keys)) {
                    return true;
                }
            }
        }

        // Didn't match all of the passed keys
        return false;
    }

    /**
     * Update this entity's relations in the database
     * Only valid for non-singletons (i.e. hasMany, manyToMany)
     *
     * @param string     $tableName The name of the target table
     * @param array|null $keys      Primary keys to set (empty clears)
     *
     * @return bool Success or failure
     */
    protected function _set(string $tableName, ?array $keys = null): bool
    {
        // Determine the type of relationship
        $relation = $this->_getRelationship($tableName);

        switch ($relation->type) {
            // WIP - need to decide about adding and detaching
            case 'hasMany':
                $pivot       = $relation->pivots[0]; // [parentTable, parentPK, childTable, childFK]
                $childTable  = $pivot[2];
                $childFk     = $pivot[3];
                $parentPkVal = $this->attributes[$this->primaryKey];
                $db          = db_connect();
                $db->transStart();
                $childBuilder = $db->table($childTable);
                // Fetch current child ids
                $currentRows = $childBuilder->select('id')->where($childFk, $parentPkVal)->get()->getResultArray();
                $currentIds  = array_column($currentRows, 'id');
                $newIds      = $keys ?? [];
                // Compute diffs
                $toDetach = array_diff($currentIds, $newIds);
                $toAttach = array_diff($newIds, $currentIds);
                if (! empty($toDetach)) {
                    $db->table($childTable)->whereIn('id', $toDetach)->set($childFk, null)->update();
                }
                if (! empty($toAttach)) {
                    $db->table($childTable)->whereIn('id', $toAttach)->set($childFk, $parentPkVal)->update();
                }
                unset($this->attributes[$tableName]);
                $db->transComplete();
                return true;

                // Delete entries from the pivot table
            case 'manyToMany':
                // Get the pivot table info
                $pivotTable = $relation->pivots[0][2];
                $pivotId    = $relation->pivots[0][3];

                $db      = db_connect();
                $builder = $db->table($pivotTable);
                $db->transStart();

                // Clear existing relations
                $builder->where($pivotId, $this->attributes[$this->primaryKey])->delete();

                // Remove from the entity so if they are requested they will reload
                unset($this->attributes[$tableName]);

                // If no keys were supplied then finish
                if (empty($keys)) {
                    $db->transComplete();
                    return true;
                }

                // Add back any specified keys (will manage its own insert logic but within same transaction)
                $result = $this->_add($tableName, $keys);
                $db->transComplete();
                return $result;

            default:
                throw new RuntimeException(lang('Relations.invalidOperation', ['setRelations', $relation->type]));
        }

    }

    /**
     * Add to this entity's relations in the database
     * Only valid for non-singletons (i.e. hasMany, manyToMany)
     *
     * @param string $tableName The name of the target table
     * @param array  $keys      Primary keys to add
     *
     * @return bool Success or failure
     */
    protected function _add(string $tableName, array $keys): bool
    {
        // If no keys were supplied then finish
        if (empty($keys)) {
            return false;
        }

        // Determine the type of relationship
        $relation = $this->_getRelationship($tableName);

        switch ($relation->type) {
            // WIP - need to decide about attaching versus adding
            case 'hasMany':
                $pivot       = $relation->pivots[0];
                $childTable  = $pivot[2];
                $childFk     = $pivot[3];
                $parentPkVal = $this->attributes[$this->primaryKey];
                $db          = db_connect();
                $db->transStart();
                $db->table($childTable)->whereIn('id', $keys)->where($childFk, null)->set($childFk, $parentPkVal)->update();
                unset($this->attributes[$tableName]);
                $db->transComplete();
                return true;

                // Add entries to the pivot table
            case 'manyToMany':
                // Get the pivot table info
                $pivotTable = $relation->pivots[0][2];
                $pivotId    = $relation->pivots[0][3];
                $targetId   = $relation->pivots[1][1];
                $db      = db_connect();
                $builder = $db->table($pivotTable);
                $db->transStart();

                // Remove from the entity so if they are requested they must reload
                unset($this->attributes[$tableName]);
                // Fetch existing target ids to avoid duplicate inserts (unique constraints or wasted work)
                $existing = $builder
                    ->select($targetId)
                    ->where($pivotId, $this->attributes[$this->primaryKey])
                    ->get()
                    ->getResultArray();
                $existingIds = array_column($existing, $targetId);

                $insertKeys = array_diff($keys, $existingIds);

                if (empty($insertKeys)) {
                    return true; // nothing new to insert
                }

                $rows = [];
                foreach ($insertKeys as $key) {
                    $rows[] = [
                        $pivotId  => $this->attributes[$this->primaryKey],
                        $targetId => $key,
                    ];
                }

                if (! empty($rows)) {
                    $builder->insertBatch($rows);
                }
                $db->transComplete();
                return true;

            default:
                throw new RuntimeException(lang('Relations.invalidOperation', ['setRelations', $relation->type]));
        }

    }

    /**
     * Remove some of this entity's relations from the database
     * Only valid for non-singletons (i.e. hasMany, manyToMany)
     *
     * @param string $tableName The name of the target table
     * @param array  $keys      Primary keys to remove
     *
     * @return bool Success or failure
     */
    protected function _remove(string $tableName, array $keys): bool
    {
        // If no keys were supplied then quit
        if (empty($keys)) {
            return false;
        }

        // Determine the type of relationship
        $relation = $this->_getRelationship($tableName);

        switch ($relation->type) {
            // WIP - need to decide about detaching versus deleting
            case 'hasMany':
                $pivot       = $relation->pivots[0];
                $childTable  = $pivot[2];
                $childFk     = $pivot[3];
                $parentPkVal = $this->attributes[$this->primaryKey];
                $db          = db_connect();
                $db->transStart();
                $db->table($childTable)->where($childFk, $parentPkVal)->whereIn('id', $keys)->set($childFk, null)->update();
                unset($this->attributes[$tableName]);
                $db->transComplete();
                return true;

                // Delete entries from the pivot table
            case 'manyToMany':
                // Get the pivot table info
                $pivotTable = $relation->pivots[0][2];
                $pivotId    = $relation->pivots[0][3];
                $targetId   = $relation->pivots[1][1];
                $db      = db_connect();
                $builder = $db->table($pivotTable);
                $db->transStart();
                $builder->where($pivotId, $this->attributes[$this->primaryKey])
                    ->whereIn($targetId, $keys)
                    ->delete();

                // Remove from the entity so if requested they will reload
                unset($this->attributes[$tableName]);
                $db->transComplete();
                return true;

            default:
                throw new RuntimeException(lang('Relations.invalidOperation', ['setRelations', $relation->type]));
        }

    }
}
