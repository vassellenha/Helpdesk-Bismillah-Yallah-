<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris pengaturan EVA (key-value). Penafsiran nilainya milik
 * AnswerSourceSettings, bukan tersebar di pemanggil.
 */
class KbSetting extends Model
{
    protected $table = 'kb_settings';

    protected $fillable = ['key', 'value'];
}
