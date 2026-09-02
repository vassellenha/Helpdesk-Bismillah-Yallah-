<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditTrail;

/**
 * Pencatatan Audit Trail untuk pengelolaan Knowledge (konsol EVA).
 *
 * Artikel, dokumen, FAQ, dan sinonim adalah bahan yang dipakai EVA menjawab
 * karyawan. Mengubah atau menghapusnya mengubah jawaban yang diterima orang
 * lain — tapi sampai sekarang tak satu pun tindakan itu meninggalkan jejak.
 *
 * Pelakunya selalu diambil sebagai pemegang role EVA, bukan Administrator:
 * seluruh rute konsol ini dijaga `role:eva`, jadi itulah kursi yang pasti
 * dimiliki. Sebagian kode lama memakai CurrentActor::admin() untuk mengisi
 * penulis — itu kebetulan bekerja karena admin-nya kebetulan juga EVA, dan
 * bukan sesuatu yang layak diandalkan pencatatan audit.
 */
final class KnowledgeAudit
{
    /**
     * @param  array<string,mixed>|null  $baru
     */
    public static function record(
        string $action,
        string $targetType,
        ?int $targetId,
        string $targetName,
        string $description,
        ?array $baru = null,
    ): void {
        $actor = CurrentActor::knowledgeAdmin();

        AuditTrail::record($actor, [
            'module' => 'knowledge',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'new_value' => $baru,
            'description' => $actor->name.' '.$description,
        ]);
    }
}
