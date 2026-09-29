<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certificate;
use App\Services\CertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_valid_pdf_binary(): void
    {
        $certificate = Certificate::factory()->create();

        $content = app(CertificatePdfService::class)->generate($certificate);

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }
}
