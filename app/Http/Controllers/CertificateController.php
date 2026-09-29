<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証PDFのダウンロードを扱うController(3ロール共有: 本人 / 担当コーチ / 管理者)。
 */
class CertificateController extends Controller
{
    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        abort_unless(Storage::disk('private')->exists($certificate->pdf_path), 404);

        $certificate->loadMissing('certification');

        return Storage::disk('private')->download(
            $certificate->pdf_path,
            $certificate->certification->name.'_修了証.pdf',
        );
    }
}
