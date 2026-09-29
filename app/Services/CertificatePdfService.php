<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * 修了証PDFのレンダリングを担うService。
 *
 * ファイルへの書き込みは呼出側(IssueAction)の責務とし、本Serviceはバイナリを生成して返すだけにする
 * (テストで「PDF生成失敗時にCertificateごとロールバックされる」ことをモックで検証しやすくするため)。
 */
class CertificatePdfService
{
    public function generate(Certificate $certificate): string
    {
        $certificate->loadMissing(['user', 'certification']);

        $html = view('certificates.pdf', ['certificate' => $certificate])->render();

        $mpdf = new Mpdf;
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
