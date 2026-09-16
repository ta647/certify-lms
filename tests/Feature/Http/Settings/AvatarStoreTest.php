<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_upload_avatar(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $response = $this->actingAs($student)->post(route('settings.avatar.store'), ['avatar' => $file]);

        $response->assertRedirect(route('settings.profile.edit'));
        $student->refresh();
        $this->assertNotNull($student->avatar_url);
        $path = ltrim(str_replace('/storage/', '', $student->avatar_url), '/');
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_avatar_deletes_old_file(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('first.png', 200, 200),
        ]);
        $oldPath = ltrim(str_replace('/storage/', '', $student->fresh()->avatar_url), '/');

        $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('second.png', 200, 200),
        ]);
        $newPath = ltrim(str_replace('/storage/', '', $student->fresh()->avatar_url), '/');

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();

        $file = UploadedFile::fake()->create('big.png', 3000, 'image/png');

        $this->actingAs($student)->post(route('settings.avatar.store'), ['avatar' => $file])
            ->assertSessionHasErrors('avatar');
    }

    public function test_rejects_invalid_mime(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();

        $file = UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml');

        $this->actingAs($student)->post(route('settings.avatar.store'), ['avatar' => $file])
            ->assertSessionHasErrors('avatar');
    }
}
