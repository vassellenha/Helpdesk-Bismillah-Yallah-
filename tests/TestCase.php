<?php

namespace Tests;

use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // PriorityRegistry menyimpan hasilnya di properti statis — benar untuk
        // satu request HTTP, tapi properti statis bertahan melintasi tes dalam
        // satu proses PHPUnit. Tanpa dibuang di sini, tes yang membuat SLA
        // Policy akan mewarisi daftar prioritas milik tes sebelumnya, dan
        // urutan tes menentukan hasilnya.
        PriorityRegistry::flush();
    }
}
