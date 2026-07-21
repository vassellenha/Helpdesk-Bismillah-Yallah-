<?php

namespace App\Support;

/**
 * Builds human-readable Audit Trail descriptions from field-level diffs,
 * matching the "X mengubah {field} {noun} 'target' dari A menjadi B." style
 * the product spec calls for, instead of dumping raw before/after JSON.
 */
class AuditDescriber
{
    /**
     * @param array<string,string> $labels attribute => display label
     * @param array<string,callable> $formatters attribute => fn(mixed $value): string
     * @return array<string,array{old:string,new:string}> only attributes that actually changed
     */
    public static function diff(array $before, array $after, array $labels, array $formatters = []): array
    {
        $changes = [];

        foreach ($labels as $attribute => $label) {
            $oldRaw = $before[$attribute] ?? null;
            $newRaw = $after[$attribute] ?? null;

            if ($oldRaw === $newRaw) {
                continue;
            }

            $formatter = $formatters[$attribute] ?? fn ($v) => (string) $v;

            $changes[$label] = [
                'old' => $formatter($oldRaw),
                'new' => $formatter($newRaw),
            ];
        }

        return $changes;
    }

    /**
     * @param array<string,array{old:string,new:string}> $changes
     */
    public static function describe(string $actorName, string $noun, string $target, array $changes): string
    {
        if (count($changes) === 1) {
            $label = array_key_first($changes);
            $c = $changes[$label];

            return "{$actorName} mengubah {$label} {$noun} \"{$target}\" dari {$c['old']} menjadi {$c['new']}.";
        }

        $parts = [];
        foreach ($changes as $label => $c) {
            $parts[] = "{$label} {$c['old']} → {$c['new']}";
        }

        return "{$actorName} memperbarui {$noun} \"{$target}\": ".implode('; ', $parts).'.';
    }

    /**
     * Reshapes a diff() result into flat old/new maps keyed by the same
     * human labels, formatted values only — what the Audit Trail detail
     * modal renders, instead of raw model attribute snapshots.
     *
     * @param array<string,array{old:string,new:string}> $changes
     * @return array{old_value:array<string,string>,new_value:array<string,string>}
     */
    public static function presentDiff(array $changes): array
    {
        $old = [];
        $new = [];

        foreach ($changes as $label => $c) {
            $old[$label] = $c['old'];
            $new[$label] = $c['new'];
        }

        return ['old_value' => $old, 'new_value' => $new];
    }

    public static function minutesLabel(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ($minutes / 1440).' Hari';
        }
        if ($minutes % 60 === 0) {
            return ($minutes / 60).' Jam';
        }

        return $minutes.' Menit';
    }
}
