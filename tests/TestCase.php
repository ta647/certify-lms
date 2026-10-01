<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Http ファサード経由の外部通信は、Http::fake() していないリクエストが発生した時点で
        // 例外を投げて即座にテストを失敗させる(外部API — GeminiClient等 — への実通信を防止する)。
        Http::preventStrayRequests();
    }
}
