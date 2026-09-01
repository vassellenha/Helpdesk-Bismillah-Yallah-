<?php

namespace App\Http\Controllers;

/**
 * Team Lead desk Support IT — pemilik layar /team-lead/*.
 *
 * Kunci role-nya tetap 'team-lead', bukan 'team-lead-it', meskipun nama
 * role-nya di basis data kini "Team Lead IT". Kunci itu sudah terlanjur
 * menjadi prefix URL yang dipakai orang, nilai kolom ticket_notifications.role
 * pada baris-baris lama, dan kosakata tes. Menggantinya hanya demi kerapian
 * nama akan memutus ketiganya sekaligus, dan yang paling tidak berbunyi adalah
 * notifikasi lama: ia tidak error, ia cuma berhenti muncul.
 */
class TeamLeadItController extends TeamLeadController
{
    protected function deskType(): string
    {
        return 'it';
    }

    protected function roleKey(): string
    {
        return 'team-lead';
    }
}
