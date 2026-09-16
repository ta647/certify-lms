<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_delete_avatar(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
        ]);
        $path = ltrim(str_replace('/storage/', '', $student->fresh()->avatar_url), '/');

        $response = $this->actingAs($student)->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertNull($student->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($path);
    }
}
