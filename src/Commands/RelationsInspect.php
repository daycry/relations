<?php

declare(strict_types=1);

namespace Daycry\Relations\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Daycry\Relations\Contracts\RelatableInterface;
use Daycry\Relations\Contracts\RelationIntrospector;

/**
 * spark relations:inspect model=UserModel [--details] [--json] [--only=groups,permissions]
 */
class RelationsInspect extends BaseCommand
{
    protected $group       = 'Relations';
    protected $name        = 'relations:inspect';
    protected $description = 'Inspecta y muestra las relaciones detectadas para un modelo que use ModelTrait.';
    protected $usage       = 'relations:inspect model=UserModel [--details] [--json] [--only=relation1,relation2]';

    public function run(array $params)
    {
        $modelClass = CLI::getOption('model') ?? $params['model'] ?? null;
        if (! $modelClass) {
            CLI::error('Debe proporcionar model=NombreDelModelo');
            return;
        }
        if (! class_exists($modelClass)) {
            CLI::error("Clase de modelo no encontrada: {$modelClass}");
            return;
        }
    /** @var RelationIntrospector|RelatableInterface|object $model */
    $model = new $modelClass();
        if (! $model instanceof RelatableInterface) {
            CLI::error('El modelo no implementa RelatableInterface');
            return;
        }
        if (! method_exists($model, 'listRelations')) {
            CLI::error('El trait de relaciones requerido no está presente.');
            return;
        }

        $only = CLI::getOption('only');
        $names = $model->listRelations();
        if ($only) {
            $filters = array_filter(array_map('trim', explode(',', (string) $only)));
            $names = array_values(array_intersect($names, $filters));
        }

        $detailsFlag = CLI::getOption('details') !== null;
        $jsonFlag    = CLI::getOption('json') !== null;

        $output = [];
        foreach ($names as $rel) {
            if ($detailsFlag) {
                /** @var array $allMeta */ // @phpstan-ignore-line
                // Safe: ModelTrait (BaseTrait) añade relationDetails
                $allMeta = $model->relationDetails($rel);
                $meta    = $allMeta[$rel] ?? [];
                $output[$rel] = $meta;
            } else {
                $output[] = $rel;
            }
        }

        if ($jsonFlag) {
            CLI::write(json_encode($output, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            return;
        }

        if ($detailsFlag) {
            CLI::write('Relaciones (detalle):');
            foreach ($output as $name => $meta) {
                CLI::write(" - {$name}: type={$meta['type']} singleton=" . ($meta['singleton'] ? 'yes':'no') . ' model=' . ($meta['model'] ?? '-'));
                if (! empty($meta['pivots'])) {
                    foreach ($meta['pivots'] as $p) {
                        CLI::write('    pivot: ' . implode(' | ', $p));
                    }
                }
            }
        } else {
            CLI::write('Relaciones: ' . implode(', ', $output));
        }
    }
}
