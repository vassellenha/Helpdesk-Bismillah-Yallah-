<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Penolakan karena AKUNNYA nonaktif — bukan karena role-nya tidak cocok.
 *
 * Dibedakan sebagai jenis tersendiri, bukan ditandai lewat isi pesannya, karena
 * halaman 403 memperlakukan keduanya berbeda: penolakan role menawarkan "Pilih
 * Role" sebagai jalan keluar, sedangkan pada akun nonaktif tawaran itu justru
 * menyesatkan — role mana pun yang dipilih akan ditolak gerbang yang sama.
 *
 * Memeriksa isi teks pesan akan bekerja hari ini dan diam-diam berhenti bekerja
 * begitu kalimatnya diperbaiki seseorang.
 */
final class AccountInactive extends AuthorizationException {}
