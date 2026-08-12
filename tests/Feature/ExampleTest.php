<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Portal pemilih role kini berada di balik login — tamu diantar ke halaman
     * masuk, bukan disuguhi daftar role yang tak satu pun bisa dibuka.
     */
    public function test_tamu_diantar_ke_halaman_masuk(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
