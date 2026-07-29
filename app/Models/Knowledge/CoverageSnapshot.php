<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat coverage untuk grafik tren saja. Angka hari ini selalu dihitung
 * ulang oleh CoverageCalculator, tidak dibaca dari tabel ini.
 */
class CoverageSnapshot extends Model
{
    protected $table = 'kb_coverage_snapshots';

    protected $fillable = ['captured_on', 'total_subjects', 'covered_subjects', 'coverage_percent'];

    protected $casts = [
        'captured_on' => 'date',
        'total_subjects' => 'integer',
        'covered_subjects' => 'integer',
        'coverage_percent' => 'integer',
    ];
}
