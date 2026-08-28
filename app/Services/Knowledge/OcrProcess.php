<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Menjalankan satu binari OCR dan memulangkan keluarannya.
 *
 * Dipisah karena sekarang ada DUA pembaca yang memanggil binari luar — PDF
 * (poppler + Tesseract) dan gambar (Tesseract saja). Sifat yang wajib sama di
 * keduanya bukan sekadar gaya penulisan:
 *   - batas waktu, supaya satu berkas rusak tidak menggantung antrean selamanya
 *   - kegagalan dicatat, tidak ditelan diam-diam
 *   - null berarti GAGAL, bukan "isinya kosong"
 * Menyalin ketiganya ke dua tempat berarti cepat atau lambat salah satunya
 * kehilangan batas waktu, dan gejalanya cuma "antrean EVA berhenti".
 */
final class OcrProcess
{
    /** Panjang maksimal pesan galat yang ikut masuk log. */
    private const MAX_ERROR_CHARS = 500;

    public function __construct(private readonly float $timeout) {}

    /**
     * @param  array<int,string|null>  $command
     * @return string|null null bila binarinya gagal atau melewati batas waktu.
     */
    public function run(array $command): ?string
    {
        $process = new Process(array_map('strval', $command));
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            Log::warning('EVA: proses OCR melewati batas waktu.', ['command' => $command[0]]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::warning('EVA: proses OCR gagal.', [
                'command' => $command[0],
                'error' => mb_substr($process->getErrorOutput(), 0, self::MAX_ERROR_CHARS),
            ]);

            return null;
        }

        return $process->getOutput();
    }
}
