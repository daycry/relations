<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Relations.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Daycry\Relations\Traits;

use Daycry\Relations\Config\Relations as ConfigRelations;
use Daycry\Relations\Exceptions\RelationsException;
use Daycry\Schemas\Structures\Relation;
use Daycry\Schemas\Structures\Schema;
use Daycry\Schemas\Structures\Table;
use RuntimeException;

trait BaseTrait
{
    /**
     * Uses loaded schema if exists
     *
     * @var string
     */
    protected ?Schema $schema = null;

    /**
     * Internal cache for resolved Relation objects keyed by target table name.
     * Optimizes repeated calls to _getRelationship/_getRelations within a single request.
     *
     * @var array<string, Relation>
     */
    protected array $_relationCache = [];

    /**
     * Static metrics across requests (per PHP process) for instrumentation/testing.
     * @var array{calls:int, tables:array<string,int>}
     */
    protected static array $relationMetrics = [
        'calls'  => 0,
        'tables' => [],
    ];

    /**
     * Optional per-process result cache (cleared manually or at end of request).
     * @var array<string,array>
     */
    protected static array $relationResultCache = [];

    /**
     * Clear the static relation result cache.
     */
    public static function clearRelationResultCache(): void
    {
        self::$relationResultCache = [];
    }

    /**
     * Return list of relation table names defined between this model/entity table and others.
     * (Keys from schema tables->{$this->table}->relations)
     *
     * @return string[]
     */
    public function listRelations(): array
    {
        $this->_verifyRelatable();
        $schema = $this->_schema();
        if (! isset($schema->tables->{$this->table})) {
            return [];
        }
        return array_keys(get_object_vars($schema->tables->{$this->table}->relations));
    }

    /**
     * Return associative array of relation metadata for one or many relations.
     * If $names omitted returns details for all relations.
     * Shape: [relationName => ['type'=>, 'singleton'=>bool, 'pivots'=>array, 'model'=>string|null]]
     *
     * @param string|string[]|null $names
     * @return array<string,array<string,mixed>>
     */
    public function relationDetails(string|array|null $names = null): array
    {
        $this->_verifyRelatable();
        $schema = $this->_schema();
        if (! isset($schema->tables->{$this->table})) {
            return [];
        }
        $all = $schema->tables->{$this->table}->relations;
        if ($names === null) {
            $targets = array_keys(get_object_vars($all));
        } else {
            $targets = is_array($names) ? $names : [$names];
        }
        $out = [];
        foreach ($targets as $t) {
            if (! isset($all->{$t})) {
                continue;
            }
            $rel = $all->{$t};
            $out[$t] = [
                'type'      => $rel->type,
                'singleton' => (bool) $rel->singleton,
                'pivots'    => $rel->pivots,
                'model'     => $schema->tables->{$t}->model ?? null,
            ];
        }
        return $out;
    }

    /**
     * Load the schema manually
     */
    public function setSchema(Schema $schema)
    {
        $this->schema = $schema;
        // Reset cached relations when schema is manually replaced
        $this->_relationCache = [];

        return $this;
    }

    /**
     * Uses the schema to determine this class's relationship to a table
     *
     * @param string $tableName Name of the target table
     */
    public function _getRelationship($tableName): Relation
    {
        $this->_verifyRelatable();

        // Serve from cache when available
        if (isset($this->_relationCache[$tableName])) {
            return $this->_relationCache[$tableName];
        }

        // Get the schema
        $schema = $this->_schema();

        // Make sure the schema knows the target table
        if (! isset($schema->tables->{$tableName})) {
            throw RelationsException::forUnknownTable($tableName);
        }

        // Fetch the target table
        $table = $schema->tables->{$tableName};

        // Make sure the tables are actually related
        if (! isset($schema->tables->{$this->table}->relations->{$table->name})) {
            throw RelationsException::forUnknownRelation($this->table, $table->name);
        }

        // Get the relation from schema
        $relation = $schema->tables->{$this->table}->relations->{$table->name};

        // Apply a type override if defined on the model (runtime modification only)
        if (property_exists($this, 'relationTypeOverrides') && isset($this->relationTypeOverrides[$tableName])) {
            $newType = $this->relationTypeOverrides[$tableName];
            if (! in_array($newType, ['hasOne', 'hasMany', 'belongsTo', 'manyToMany', 'manyThrough'], true)) {
                throw new RuntimeException("Invalid relation type override '{$newType}' for '{$tableName}'.");
            }
            // Clone to avoid mutating schema's canonical relation object
            $clone = new Relation();
            $clone->type      = $newType;
            $clone->pivots    = $relation->pivots; // shallow copy fine (array of arrays)
            $clone->singleton = in_array($newType, ['hasOne', 'belongsTo'], true);
            // Preserve existing singleton if original was singleton and override keeps equivalent semantics
            if (! $clone->singleton && $relation->singleton) {
                $clone->singleton = $relation->singleton;
            }
            // Cache and return clone
            return $this->_relationCache[$tableName] = $clone;
        }

        // Verify that pivots are defined
        if (empty($relation->pivots)) {
            throw RelationsException::forMissingPivots($this->table, $tableName);
        }

        // Store in cache and return
        return $this->_relationCache[$tableName] = $relation;
    }

    /**
     * Uses the schema to load related items
     *
     * @param string     $tableName Name of the table for related items
     * @param array|null $ids       Filter for this class's primary keys
     *
     * @return array [$id => [$relatedItems]], or [$id => $relatedItem] for singletons
     */
    public function _getRelations($tableName, $ids = null, ?Relation $prefetched = null): array
    {
        $this->_verifyRelatable();

        // Metrics bookkeeping (conditional) - cache flag
        static $collectMetrics;
        if ($collectMetrics === null) {
            $collectMetrics = (config('Relations')->collectMetrics ?? false);
        }
        if ($collectMetrics) {
            self::$relationMetrics['calls']++;
            self::$relationMetrics['tables'][$tableName] = (self::$relationMetrics['tables'][$tableName] ?? 0) + 1;
        }

        // Build cache key if enabled
        $configCache = config('Relations')->cacheRelationResults ?? false;
        $cacheKey = null;
        if ($configCache && is_array($ids)) {
            $sorted = $ids;
            sort($sorted);
            $cacheKey = $this->table . '>' . $tableName . ':' . md5(json_encode($sorted));
            if (isset(self::$relationResultCache[$cacheKey])) {
                return self::$relationResultCache[$cacheKey];
            }
        }

        // Fetch the target table
        /** @var Table $table */
        $table = $this->_schema()->tables->{$tableName};

        // Get the relationship (use prefetched if provided)
        $relation = $prefetched ?? $this->_getRelationship($tableName);

        // Get the config
        /** @var ConfigRelations $config */
        $config = config('Relations');

        // Check for a known model for the target table
        if (isset($table->model)) {
            // Grab an instance of the model to use (model acts as builder)
            $class   = $table->model;
            $model   = new $class();
            $builder = $model; // model has query builder methods
            $returnType = $model->returnType;
            unset($class);

            // If this was called from a model then check for another Relations model (to prevent nesting loops)
            if (method_exists($builder, '_getRelations')) {
                // Don't reindex (we'll do our own below)
                $builder->reindex(false);

                // If nesting is allowed we need to disable the target table
                if ($config->allowNesting) {
                    // Add the target table to the "without" list
                    $without   = $this->tmpWithout ?? [];
                    $without[] = $table->name;
                    $builder->without($without);
                }
                // Otherwise turn off relation loading on returned relations
                else {
                    $builder->with(false);
                }
            }
        }

        // No model - use a generic builder
        else {
            $db       = isset($this->db) ? $this->db : db_connect();
            $builder  = $db->table($table->name);
            $returnType = $config->defaultReturnType;
        }

        // Define the returns
        $builder->select("{$table->name}.*");

        // Handle each relationship type differently
        switch ($relation->type) {
            case 'hasMany':
            case 'hasOne': // explicit alias; singleton flag decides collapse
                $pivot       = reset($relation->pivots);
                $originating = "{$pivot[2]}.{$pivot[3]}";
                break;
            case 'belongsTo':
                $originating = "{$this->table}.{$this->primaryKey}";
                $pivot       = reset($relation->pivots);
                $builder->join($pivot[0], "{$pivot[0]}.{$pivot[1]} = {$pivot[2]}.{$pivot[3]}");
                break;
            case 'manyThrough':
                // Treat pivots as ordered hops: each pivot = [leftTable,leftKey,rightTable,rightKey]
                $firstPivot  = reset($relation->pivots);
                $originating = "{$firstPivot[0]}.{$firstPivot[1]}"; // PK of origin table (should match $this->table PK)
                $current     = $firstPivot;
                while ($next = next($relation->pivots)) {
                    // Join rightTable of current pivot to leftTable of next pivot if matches, else direct join chain
                    $builder->join($current[2], "{$current[2]}.{$current[3]} = {$current[0]}.{$current[1]}");
                    $current = $next;
                }
                // Join last hop target table
                if ($current !== $firstPivot) {
                    $builder->join($current[2], "{$current[2]}.{$current[3]} = {$current[0]}.{$current[1]}");
                }
                break;
            case 'manyToMany':
            default:
                $pivot       = reset($relation->pivots);
                $originating = "{$pivot[2]}.{$pivot[3]}";
                while ($pivot = next($relation->pivots)) {
                    $builder->join($pivot[0], "{$pivot[0]}.{$pivot[1]} = {$pivot[2]}.{$pivot[3]}");
                }
        }

        $builder->select("{$originating} AS originating_id");

        // Entities always filter by themselves
        if (! empty($this->attributes[$this->primaryKey])) {
            $builder->where("{$originating}", $this->attributes[$this->primaryKey]);
        }
        // Check for an explicit filter request
        elseif (! empty($ids)) {
            $builder->whereIn("{$originating}", $ids);
        }

        // If the model is available then use it to get the result
        // (Bonus: triggers model's afterFind)
        if (isset($table->model)) {
            // Check if this table should include soft deletes
            if (isset($this->withDeletedRelations) && in_array($table->name, $this->withDeletedRelations, true)) {
                $model->withDeleted();
            }
            $results = $model->find();
        } else {
            $results = $builder->get()->getResult();
        }

        // Clean up
        unset($table, $builder);

        // Reindex the results by the originating ID (this table's primary key)
        $return = [];

        foreach ($results as $row) {
            if ($returnType === 'array') {
                $originatingId = $row['originating_id'];
                unset($row['originating_id']);
            } else {
                $originatingId = $row->originating_id;
                unset($row->originating_id);
            }

            // Singleton return one row set, others append to an array
            if ($relation->singleton) {
                $return[$originatingId] = $row;
            } else {
                $return[$originatingId][] = $row;
            }
        }

        if ($cacheKey) {
            self::$relationResultCache[$cacheKey] = $return;
        }

        return $return;
    }

    /**
     * Return and optionally reset relation metrics.
     */
    public static function getRelationMetrics(bool $reset = false): array
    {
        if ($reset) {
            self::$relationMetrics = ['calls' => 0, 'tables' => []];
            return self::$relationMetrics;
        }
        return self::$relationMetrics;
    }

    /**
     * Preps the Schemas service with a current schema to share across this library and returns it.
     */
    protected function _schema(): Schema
    {
        $this->_verifyRelatable();

        // Load the Schemas service
        $schemas = service('schemas');

        if (empty($schemas)) {
            throw new RuntimeException(lang('Relations.noSchemas'));
        }

        // Check if schema is loaded manually
        if ($this->schema) {
            return $this->schema;
        }

        // Attempt to get current schema; if missing try draft (in-memory) as fallback
        $schema = $schemas->get();
        if ($schema === null) {
            try {
                $schemas->draft();
                $schema = $schemas->get();
            } catch (\Throwable $e) {
                // Ignore; will throw below
            }
            if ($schema === null) {
                throw new RuntimeException(lang('Relations.noSchemas'));
            }
        }

        // Memoize for future calls in this instance
        return $this->schema = $schema;
    }

    /**
     * Ensure this class has everything it needs to use Relations
     */
    protected function _verifyRelatable()
    {
        if (empty($this->table)) {
            throw RelationsException::forMissingProperty(self::class, 'table');
        }

        if (empty($this->primaryKey)) {
            throw RelationsException::forMissingProperty(self::class, 'primaryKey');
        }

        // Make sure we have the inflector helper
        if (! function_exists('plural')) {
            helper('inflector');
        }

        // Ensure implementing class declares RelatableInterface
        if (! is_a($this, \Daycry\Relations\Contracts\RelatableInterface::class)) {
            throw RelationsException::forNotRelatable(static::class);
        }
    }
}
