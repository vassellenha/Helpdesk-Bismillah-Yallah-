<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the boundary between a layout's shipped message groups and the keys
 * its React islands actually ask for.
 *
 * partials/translations.blade.php ships 'common' plus whatever groups the
 * layout opts into. A component mounted in more than one layout — the top nav
 * is mounted in three — therefore cannot use a key from any single role's
 * group: on the other layouts that group is absent and t() falls back to
 * printing the raw key on screen.
 *
 * This has now happened twice: admin.common.close on non-admin screens, and
 * approver.nav.notifications on the Support and Support BPO screens. The rule
 * is cheap to check statically, so check it.
 */
final class LayoutTranslationGroupsTest extends TestCase
{
    private const LAYOUT_DIR = 'resources/views/layouts';
    private const REGISTRY = 'resources/js/components/registry.js';

    public function test_every_mounted_component_only_uses_groups_its_layout_ships(): void
    {
        $registry = $this->componentPaths();
        $violations = [];

        foreach (glob(base_path(self::LAYOUT_DIR).'/*.blade.php') as $layout) {
            $source = file_get_contents($layout);
            $allowed = $this->shippedGroups($source);

            foreach ($this->mountedComponents($source) as $component) {
                $path = $registry[$component] ?? null;
                $this->assertNotNull($path, "Komponen {$component} dipasang di ".basename($layout).' tapi tidak terdaftar di registry.js');

                foreach ($this->translationGroups($path) as $group => $key) {
                    if (! in_array($group, $allowed, true)) {
                        $violations[] = sprintf(
                            '%s memasang %s yang memakai %s, tetapi layout itu hanya mengirim grup [%s]',
                            basename($layout),
                            $component,
                            $key,
                            implode(', ', $allowed),
                        );
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Kunci terjemahan akan tampil mentah di layar:\n".implode("\n", $violations));
    }

    /** @return array<string, string> nama komponen => path berkas */
    private function componentPaths(): array
    {
        preg_match_all(
            "/import\s+(\w+)\s+from\s+'\.\/([^']+)'/",
            file_get_contents(base_path(self::REGISTRY)),
            $matches,
            PREG_SET_ORDER,
        );

        $paths = [];
        foreach ($matches as [, $name, $relative]) {
            $paths[$name] = base_path('resources/js/components/'.$relative.'.jsx');
        }

        return $paths;
    }

    /** 'common' selalu ikut; sisanya sesuai opt-in layout. */
    private function shippedGroups(string $layout): array
    {
        preg_match("/'groups'\s*=>\s*\[([^\]]*)\]/", $layout, $m);
        preg_match_all("/'([a-z-]+)'/", $m[1] ?? '', $groups);

        return array_merge(['common'], $groups[1]);
    }

    /** @return list<string> */
    private function mountedComponents(string $layout): array
    {
        preg_match_all('/data-react="(\w+)"/', $layout, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Hanya kunci literal. Kunci yang dibangun dari template string tidak bisa
     * dibaca statis, dan sengaja dilewati daripada ditebak.
     *
     * @return array<string, string> grup => contoh kunci utuh
     */
    private function translationGroups(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        preg_match_all("/\b(?:t|trans)\(\s*'([a-z][a-zA-Z0-9_]*)\.([a-zA-Z0-9_.]+)'/", file_get_contents($path), $m, PREG_SET_ORDER);

        $groups = [];
        foreach ($m as [, $group, $rest]) {
            $groups[$group] = $group.'.'.$rest;
        }

        return $groups;
    }
}
