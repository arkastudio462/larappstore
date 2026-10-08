<?php

namespace Tests\Feature\Admin;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_report_a_post(): void
    {
        $post = Post::factory()->create(['body' => 'Konten bermasalah']);
        $reporter = User::factory()->create();

        $this->actingAs($reporter)
            ->postJson('/api/reports', [
                'reportable_type' => 'post',
                'reportable_id' => $post->id,
                'reason' => 'Spam',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Report::STATUS_OPEN)
            ->assertJsonPath('data.reportable_type', 'Post')
            ->assertJsonPath('data.subject.label', 'Konten bermasalah');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'Spam',
            'status' => Report::STATUS_OPEN,
        ]);
    }

    public function test_reporting_requires_authentication(): void
    {
        $post = Post::factory()->create();

        $this->postJson('/api/reports', [
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reason' => 'Spam',
        ])->assertUnauthorized();
    }

    public function test_report_validates_its_input(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/reports', ['reportable_type' => 'video', 'reportable_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reportable_type', 'reason']);
    }

    public function test_report_returns_404_when_the_content_is_missing(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/reports', [
                'reportable_type' => 'comment',
                'reportable_id' => 9999,
                'reason' => 'Spam',
            ])
            ->assertNotFound();
    }

    public function test_admin_lists_and_filters_reports(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Komentar',
        ]);

        $this->report($post);
        $open = $this->report($comment);
        $this->report($post)->forceFill(['status' => Report::STATUS_RESOLVED])->save();

        $this->actingAs($this->admin())
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/reports?status=open')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertNotNull($open);
    }

    public function test_admin_resolves_a_report(): void
    {
        $admin = $this->admin();
        $report = $this->report(Post::factory()->create());

        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$report->id}/resolve", ['status' => Report::STATUS_DISMISSED])
            ->assertOk()
            ->assertJsonPath('data.status', Report::STATUS_DISMISSED);

        $this->assertSame($admin->id, $report->refresh()->resolved_by);
    }

    public function test_admin_report_routes_require_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/reports')
            ->assertForbidden();
    }

    private function report(Post|Comment $reportable): Report
    {
        return Report::query()->create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
            'reason' => 'Uji',
            'status' => Report::STATUS_OPEN,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
