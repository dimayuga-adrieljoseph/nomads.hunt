<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeAnnouncement(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'Test Announcement',
            'label' => 'NEXT FIELD',
            'description' => 'Event details',
            'status' => Announcement::STATUS_PUBLISHED,
            'priority' => 0,
        ], $overrides));
    }

    public function test_public_endpoint_returns_only_the_deterministic_active_item(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));

        $this->makeAnnouncement(['title' => 'Lower priority', 'priority' => 1]);
        $this->makeAnnouncement(['title' => 'Homepage pick', 'priority' => 5]);
        $this->makeAnnouncement([
            'title' => 'Future item',
            'priority' => 10,
            'starts_at' => Carbon::now()->addHour(),
        ]);
        $this->makeAnnouncement([
            'title' => 'Draft item',
            'priority' => 20,
            'status' => Announcement::STATUS_DRAFT,
        ]);
        $this->makeAnnouncement([
            'title' => 'Expired item',
            'priority' => 20,
            'ends_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->getJson('/api/announcements/active')
            ->assertOk()
            ->assertJsonPath('data.title', 'Homepage pick')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.priority');

        $this->assertSame('NEXT FIELD', $response->json('data.label'));
    }

    public function test_null_schedule_is_immediately_active_and_expiry_is_automatic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));

        $active = $this->makeAnnouncement([
            'starts_at' => null,
            'ends_at' => null,
        ]);
        $expired = $this->makeAnnouncement([
            'title' => 'Old item',
            'ends_at' => Carbon::now()->subSecond(),
        ]);

        $this->assertTrue($active->isEligible());
        $this->assertFalse($expired->isEligible());
        $this->getJson('/api/announcements/active')
            ->assertOk()
            ->assertJsonPath('data.id', $active->id);
    }

    public function test_admin_crud_and_status_actions_are_protected(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();

        $this->getJson('/api/admin/announcements')->assertUnauthorized();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/announcements')
            ->assertForbidden();

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/announcements', [
                'title' => 'Admin created',
                'status' => Announcement::STATUS_DRAFT,
                'priority' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Admin created');

        $id = $created->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/announcements/{$id}", [
                'title' => 'Admin updated',
                'status' => Announcement::STATUS_DRAFT,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Admin updated');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/announcements/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_PUBLISHED);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/announcements/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', Announcement::STATUS_ARCHIVED);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/announcements/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('announcements', ['id' => $id]);
    }

    public function test_image_upload_replacement_and_removal_use_the_public_disk(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $image = UploadedFile::fake()->image('event.jpg', 800, 400);

        $created = $this->actingAs($admin, 'sanctum')
            ->post('/api/admin/announcements', [
                'title' => 'With image',
                'image' => $image,
            ])
            ->assertCreated();

        $id = $created->json('data.id');
        $firstImage = $created->json('data.image_url');
        $oldImage = Announcement::findOrFail($id)->image;
        $this->assertNotNull($firstImage);
        Storage::disk('public')->assertExists('announcements/'.$oldImage);

        $replacement = UploadedFile::fake()->image('replacement.png', 800, 400);
        $this->actingAs($admin, 'sanctum')
            ->put("/api/admin/announcements/{$id}", [
                'title' => 'With image',
                'image' => $replacement,
            ])
            ->assertOk();
        Storage::disk('public')->assertMissing('announcements/'.$oldImage);

        $this->actingAs($admin, 'sanctum')
            ->put("/api/admin/announcements/{$id}", [
                'title' => 'Without image',
                'remove_image' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.image_url', null);

        $this->assertNull(Announcement::findOrFail($id)->image);
    }

    public function test_invalid_image_type_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->create('event.pdf', 10, 'application/pdf');

        $this->actingAs($admin, 'sanctum')
            ->post('/api/admin/announcements', [
                'title' => 'Invalid image',
                'image' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_schedule_must_end_after_it_starts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/announcements', [
                'title' => 'Invalid schedule',
                'starts_at' => '2026-10-10 12:00',
                'ends_at' => '2026-10-10 11:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }
}
