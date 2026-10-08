<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditLedger;
use App\Models\DeveloperProfile;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_exposes_role_helpers(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->role = User::ROLE_DEVELOPER;
        $this->assertTrue($user->isDeveloper());

        $user->role = User::ROLE_ADMIN;
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isDeveloper());
    }

    public function test_developer_profile_defaults_to_zero_credits(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $user->uploadCredits());
        $this->assertFalse($user->hasUnlimitedUploads());
    }

    public function test_consume_upload_credit_fails_when_balance_is_zero(): void
    {
        $user = User::factory()->create();
        DeveloperProfile::query()->create([
            'user_id' => $user->id,
            'studio_name' => 'Studio Uji',
            'slug' => 'studio-uji',
            'upload_credits' => 0,
        ]);

        $this->assertFalse($user->fresh()->consumeUploadCredit());
    }

    public function test_consume_upload_credit_decrements_balance_once(): void
    {
        $user = User::factory()->create();
        DeveloperProfile::query()->create([
            'user_id' => $user->id,
            'studio_name' => 'Studio Uji',
            'slug' => 'studio-uji',
            'upload_credits' => 1,
        ]);

        $this->assertTrue($user->fresh()->consumeUploadCredit());
        $this->assertSame(0, $user->fresh()->uploadCredits());
        $this->assertFalse($user->fresh()->consumeUploadCredit());
    }

    public function test_unlimited_uploads_never_consumes_credits(): void
    {
        $user = User::factory()->create();
        DeveloperProfile::query()->create([
            'user_id' => $user->id,
            'studio_name' => 'Studio Uji',
            'slug' => 'studio-uji',
            'upload_credits' => 0,
            'unlimited_uploads' => true,
        ]);

        $this->assertTrue($user->fresh()->consumeUploadCredit());
        $this->assertTrue($user->fresh()->consumeUploadCredit());
        $this->assertSame(0, $user->fresh()->uploadCredits());
    }

    public function test_product_belongs_to_developer_and_category(): void
    {
        $product = Product::factory()->create();

        $this->assertInstanceOf(User::class, $product->developer);
        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertSame($product->developer_id, $product->developer->id);
    }

    public function test_category_children_are_ordered_by_position(): void
    {
        $parent = Category::factory()->create();
        $second = Category::factory()->create(['parent_id' => $parent->id, 'position' => 2]);
        $first = Category::factory()->create(['parent_id' => $parent->id, 'position' => 1]);

        $this->assertSame(
            [$first->id, $second->id],
            $parent->children->pluck('id')->all()
        );
    }

    public function test_product_versions_are_ordered_newest_first(): void
    {
        $product = Product::factory()->create();
        ProductVersion::query()->create([
            'product_id' => $product->id,
            'version' => '1.0.0',
            'file_path' => 'products/1/1.0.0.apk',
        ]);
        ProductVersion::query()->create([
            'product_id' => $product->id,
            'version' => '2.0.0',
            'file_path' => 'products/1/2.0.0.apk',
        ]);

        $this->assertSame(
            ['2.0.0', '1.0.0'],
            $product->versions->pluck('version')->all()
        );
    }

    public function test_published_scope_excludes_drafts(): void
    {
        Product::factory()->create(['title' => 'Terbit']);
        Product::factory()->draft()->create(['title' => 'Draf']);

        $this->assertSame(
            ['Terbit'],
            Product::query()->published()->pluck('title')->all()
        );
    }

    public function test_free_and_paid_products_are_distinguishable(): void
    {
        $free = Product::factory()->create();
        $paid = Product::factory()->paid(25000)->create();

        $this->assertTrue($free->isFree());
        $this->assertFalse($paid->isFree());
        $this->assertSame(25000, $paid->price);
    }

    public function test_post_counts_comments_at_top_level_only(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $parent = $post->allComments()->create([
            'user_id' => $user->id,
            'body' => 'Komentar induk',
        ]);
        $post->allComments()->create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'body' => 'Balasan',
        ]);

        $this->assertCount(1, $post->comments);
        $this->assertCount(2, $post->allComments);
    }

    public function test_credit_ledger_records_balance_history(): void
    {
        $user = User::factory()->create();

        CreditLedger::query()->create([
            'user_id' => $user->id,
            'delta' => 1,
            'type' => CreditLedger::TYPE_SIGNUP_BONUS,
            'balance_after' => 1,
            'description' => 'Bonus pendaftaran developer',
        ]);
        CreditLedger::query()->create([
            'user_id' => $user->id,
            'delta' => -1,
            'type' => CreditLedger::TYPE_USAGE,
            'balance_after' => 0,
            'description' => 'Unggah aplikasi pertama',
        ]);

        $this->assertSame(
            [1, 0],
            $user->creditLedger->pluck('balance_after')->all()
        );
    }
}
