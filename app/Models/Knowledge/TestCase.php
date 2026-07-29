<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Contoh pertanyaan uji milik sebuah Article, Faq, atau ServiceCatalogSubject.
 * Uji lolos bila pencarian mengembalikan sumber yang sama persis dengan
 * pemiliknya.
 */
class TestCase extends Model
{
    protected $table = 'kb_test_cases';

    protected $fillable = ['testable_type', 'testable_id', 'question'];

    public function testable(): MorphTo
    {
        return $this->morphTo();
    }
}
