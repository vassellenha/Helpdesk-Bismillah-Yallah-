<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\CoverageCalculator;
use Illuminate\View\View;

/**
 * Apps & Systems.
 *
 * Layar ini BACA SAJA. Layanan dan sub category adalah isi Service Catalog
 * milik role Admin (aturan #5) — EVA tidak boleh menambah, mengubah, atau
 * menonaktifkan satu pun barisnya.
 *
 * Mockup menyediakan form tambah/sunting layanan di sini. Itu sengaja tidak
 * ditiru: layanan yang bisa dibuat dari dua tempat adalah persis pola "satu
 * konsep, dua sumber data" yang membuat katalog dan KB perlahan berbeda isi.
 */
class AppsController extends Controller
{
    public function __construct(private readonly CoverageCalculator $coverage) {}

    public function index(): View
    {
        $services = $this->coverage->byService();
        $summary = $this->coverage->summary();

        return view('eva.apps', [
            'services' => $services,
            'stats' => [
                'services' => count($services),
                'subjects' => $summary['total_subjects'],
                'covered' => $summary['covered_subjects'],
                'untouched' => count(array_filter($services, fn (array $s) => $s['covered'] === 0)),
            ],
            'catalogUrl' => route('admin.service-catalog'),
        ]);
    }
}
