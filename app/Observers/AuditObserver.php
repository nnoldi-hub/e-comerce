<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Jurnal de audit generic, configurat din config/audit.php. Înregistrează
 * cine (utilizatorul autentificat), ce înregistrare și ce câmpuri s-au schimbat
 * (vechi → nou). Scrierile în masă prin query builder nu declanșează modele,
 * deci nu apar aici (de exemplu agregatele produsului).
 */
class AuditObserver
{
    private const VERBS = [
        'created' => 'Creare',
        'updated' => 'Modificare',
        'deleted' => 'Ștergere',
        'restored' => 'Restaurare',
    ];

    public function created(Model $model): void
    {
        $fields = $this->fields($model);

        $this->record($model, 'created', Arr::only($model->getAttributes(), $fields), []);
    }

    public function updated(Model $model): void
    {
        $changes = Arr::only($model->getChanges(), $this->fields($model));

        if ($changes === []) {
            return;
        }

        $this->record($model, 'updated', $changes, Arr::only($model->getRawOriginal(), array_keys($changes)));
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', [], Arr::only($model->getRawOriginal(), $this->fields($model)));
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', [], []);
    }

    /** @return list<string> */
    private function fields(Model $model): array
    {
        return array_values((array) config('audit.models.'.$model::class.'.fields', []));
    }

    /**
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     */
    private function record(Model $model, string $event, array $new, array $old): void
    {
        $config = (array) config('audit.models.'.$model::class, []);

        if ($config === [] || ! in_array($event, (array) ($config['events'] ?? array_keys(self::VERBS)), true)) {
            return;
        }

        $titleColumn = (string) ($config['title'] ?? 'id');
        $label = (string) ($config['label'] ?? class_basename($model));

        activity((string) ($config['log'] ?? 'default'))
            ->performedOn($model)
            ->event($event)
            ->withProperties([
                'title' => (string) ($model->getAttribute($titleColumn) ?? $model->getKey()),
                'attributes' => $new,
                'old' => $old,
            ])
            ->log(self::VERBS[$event].' '.$label);
    }
}
