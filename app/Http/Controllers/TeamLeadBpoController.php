<?php

namespace App\Http\Controllers;

/**
 * Team Lead desk Support BPO — pemilik layar /team-lead-bpo/*.
 *
 * Seluruh perilakunya diwarisi dari TeamLeadController; yang berbeda hanya
 * desk yang diawasi. Lihat induknya untuk alasan mengapa perbedaan itu
 * diturunkan dari satu nilai, bukan disalin.
 */
class TeamLeadBpoController extends TeamLeadController
{
    protected function deskType(): string
    {
        return 'bpo';
    }

    protected function roleKey(): string
    {
        return 'team-lead-bpo';
    }
}
