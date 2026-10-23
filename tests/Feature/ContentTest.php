<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostMedia;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => 'content_manager', 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Study planning guide', 'slug' => 'study-planning-guide',
            'category_id' => PostCategory::factory()->create()->id,
            'body' => 'Prepare your documents and discuss your plans with our team.',
            'author_name' => 'Edu World', 'is_published' => false, 'is_featured' => false,
        ], $overrides);
    }

    public function test_drafts_and_future_posts_are_hidden_from_public_pages(): void
    {
        $live = Post::factory()->published()->create(['title' => 'Visible guidance']);
        $draft = Post::factory()->create(['title' => 'Private draft headline']);
        $scheduled = Post::factory()->published()->create(['title' => 'Future news headline', 'published_at' => now()->addDay()]);
        $this->get(route('news'))->assertOk()->assertSee($live->title)->assertDontSee($draft->title)->assertDontSee($scheduled->title);
        $this->get(route('news.show', $draft->slug))->assertNotFound();
        $this->get(route('news.show', $scheduled->slug))->assertNotFound();
        $this->get(route('news.show', $live->slug))->assertOk()->assertSee('BreadcrumbList')->assertSee('Article');
    }

    public function test_an_editor_can_publish_and_unpublish_a_post_with_audit_history(): void
    {
        $this->actingAs($this->editor());
        $payload = $this->payload(['is_published' => true, 'published_at' => now()->subMinute()->toDateTimeString(), 'tags' => 'Admissions, Study Abroad']);
        $this->post(route('admin.posts.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $post = Post::firstOrFail();
        $this->assertTrue($post->isPublic());
        $this->assertCount(2, $post->tags);
        $this->assertDatabaseHas('activity_logs', ['action' => 'post.created', 'subject_id' => $post->id]);
        $payload['is_published'] = false;
        $this->put(route('admin.posts.update', $post), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('news.show', $post->slug))->assertNotFound();
        $this->assertDatabaseHas('activity_logs', ['action' => 'post.unpublished', 'subject_id' => $post->id]);
        $payload['is_published'] = true;
        $this->put(route('admin.posts.update', $post), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'post.published', 'subject_id' => $post->id]);
    }

    public function test_publication_requires_a_date_and_unique_slug(): void
    {
        $post = Post::factory()->create(['slug' => 'already-used']);
        $this->actingAs($this->editor());
        $this->post(route('admin.posts.store'), $this->payload(['is_published' => true, 'slug' => $post->slug]))
            ->assertSessionHasErrors(['published_at', 'slug']);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_utc_schedule_becomes_public_at_the_exact_time_with_gallery_media(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(21, 29, 59));
        $payload = $this->payload(['is_published' => true, 'published_at' => '2026-10-06T21:30', 'excerpt' => 'Scheduled UTC news.']);
        $this->actingAs($this->editor())->post(route('admin.posts.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect()
            ->assertSessionHas('success', 'Scheduled for 06 Oct 2026, 21:30 UTC. It will appear publicly at that time.');
        $post = Post::firstOrFail();
        $this->assertSame('Scheduled', $post->publication_status);
        $this->assertSame('2026-10-06 21:30:00', $post->published_at->toDateTimeString());
        $media = PostMedia::factory()->create(['post_id' => $post->id]);
        Storage::disk('local')->put($media->path, 'image');

        $this->get(route('admin.posts.edit', $post))->assertOk()->assertSee('2026-10-06T21:30')->assertSee('Time zone: UTC');
        $this->get(route('admin.posts.index'))->assertOk()->assertSee('06 Oct 2026 21:30');
        $this->put(route('admin.posts.update', $post), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-06 21:30:00', $post->fresh()->published_at->toDateTimeString());

        $this->app['auth']->forgetGuards();
        $this->assertGuest();
        $this->get(route('news'))->assertOk()->assertDontSee($post->title);
        $this->get(route('news.show', $post->slug))->assertNotFound();
        $this->get(route('news.media', $media))->assertNotFound();
        $this->assertFalse($post->isPublic());

        $this->travel(1)->seconds();
        $this->assertTrue($post->isPublic());
        $this->assertSame('Published', $post->publication_status);
        $this->get(route('news'))->assertOk()->assertSee($post->title);
        $this->get('/')->assertOk()->assertSee($post->title);
        $this->get(route('news.show', $post->slug))->assertOk()->assertSee($post->title);
        $this->get(route('news.media', $media))->assertOk();
        $this->travelBack();
    }

    public function test_a_date_without_publication_enabled_stays_a_draft(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(21, 29, 59));
        $payload = $this->payload(['published_at' => '2026-10-06T21:30', 'excerpt' => 'Publication requires an explicit choice.']);
        unset($payload['is_published']);
        $this->actingAs($this->editor())->post(route('admin.posts.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Saved as a draft. Select "Publish this post" and set a publication date to make it public.');
        $post = Post::firstOrFail();
        $this->travel(2)->seconds();
        $this->assertSame('Draft', $post->publication_status);
        $this->get(route('news.show', $post->slug))->assertNotFound();
        $payload['is_published'] = true;
        $this->put(route('admin.posts.update', $post), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Published. This post is now visible on the public website.');
        $this->app['auth']->forgetGuards();
        $this->get(route('news.show', $post->slug))->assertOk();
        $this->travelBack();
    }

    public function test_cms_access_requires_an_active_content_role(): void
    {
        $this->get(route('admin.posts.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get(route('admin.posts.index'))->assertForbidden();
        $this->post(route('admin.posts.store'), $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'content_manager', 'is_active' => false]));
        $this->get(route('admin.posts.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_draft_images_and_video_are_not_accessible_without_editor_permission(): void
    {
        Storage::fake('local');
        $post = Post::factory()->create(['featured_image' => 'cms/images/private.jpg']);
        Storage::disk('local')->put($post->featured_image, 'image');
        $media = PostMedia::factory()->create(['post_id' => $post->id, 'type' => 'video', 'path' => 'cms/videos/private.mp4', 'mime_type' => 'video/mp4']);
        Storage::disk('local')->put($media->path, 'video');
        $this->get(route('news.image', $post))->assertNotFound();
        $this->get(route('news.media', $media))->assertNotFound();
        $this->actingAs($this->editor());
        $this->get(route('news.image', $post))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('news.media', $media))->assertOk()->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_images_are_validated_reencoded_and_kept_in_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->editor());
        $this->post(route('admin.posts.store'), $this->payload([
            'featured_upload' => UploadedFile::fake()->image('photo.png', 2500, 1200),
            'gallery' => [UploadedFile::fake()->image('group.jpg', 600, 400)],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $post = Post::firstOrFail();
        $this->assertStringEndsWith('.jpg', $post->featured_image);
        $this->assertStringNotContainsString('photo', $post->featured_image);
        Storage::disk('local')->assertExists($post->featured_image);
        Storage::disk('public')->assertMissing($post->featured_image);
        $dimensions = getimagesize(Storage::disk('local')->path($post->featured_image));
        $this->assertSame(1800, $dimensions[0]);
        $this->assertSame('image/jpeg', $dimensions['mime']);
        $this->assertCount(1, $post->media);
        $this->post(route('admin.posts.store'), $this->payload(['slug' => 'bad-upload', 'featured_upload' => UploadedFile::fake()->create('script.php', 1, 'text/x-php')]))->assertSessionHasErrors('featured_upload');
    }

    public function test_script_links_and_untrusted_embeds_are_rejected_and_html_is_escaped(): void
    {
        $this->actingAs($this->editor());
        $this->post(route('admin.posts.store'), $this->payload(['youtube_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ', 'cta_url' => 'javascript:alert(1)', 'cta_label' => 'Apply']))->assertSessionHasErrors(['youtube_url', 'cta_url']);
        $this->post(route('admin.posts.store'), $this->payload(['youtube_url' => 'https://youtu.be/dQw4w9WgXcQ']))->assertSessionHasNoErrors();
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', Post::firstOrFail()->youtube_embed_url);
        $post = Post::factory()->published()->create(['title' => '<script>alert("metadata")</script>', 'seo_title' => '<script>alert("metadata")</script>', 'body' => '<script>alert("unsafe")</script>']);
        $this->get(route('news.show', $post->slug))->assertOk()->assertDontSee('<script>alert(', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_category_filtering_and_admin_content_views_work(): void
    {
        $included = Post::factory()->published()->create(['title' => 'Selected category article']);
        $excluded = Post::factory()->published()->create(['title' => 'Another category article']);
        $this->get(route('news', ['category' => $included->category->slug]))->assertOk()->assertSee($included->title)->assertDontSee($excluded->title);
        $this->actingAs($this->editor());
        foreach (['admin.posts.index', 'admin.posts.create', 'admin.categories.index', 'admin.pages.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('admin.posts.edit', $included))->assertOk();
    }

    public function test_legal_pages_are_editable_and_content_seeding_preserves_edits(): void
    {
        $this->seed(ContentSeeder::class);
        foreach (['privacy-policy', 'terms', 'payment-terms', 'refund-policy', 'disclaimer'] as $slug) {
            $this->get(route($slug))->assertOk();
        }
        $page = Page::where('slug', 'privacy-policy')->firstOrFail();
        $this->actingAs($this->editor());
        $this->get(route('admin.pages.edit', $page))->assertOk();
        $this->put(route('admin.pages.update', $page), ['title' => $page->title, 'body' => 'Business-reviewed privacy wording.', 'review_required' => false])->assertSessionHasNoErrors();
        $this->seed(ContentSeeder::class);
        $this->assertSame('Business-reviewed privacy wording.', $page->fresh()->body);
        $this->assertFalse($page->fresh()->review_required);
        $this->assertDatabaseCount('posts', 3);
    }

    public function test_deleting_a_post_removes_its_private_media(): void
    {
        Storage::fake('local');
        $post = Post::factory()->create(['featured_image' => 'cms/images/cover.jpg']);
        $media = PostMedia::factory()->create(['post_id' => $post->id]);
        Storage::disk('local')->put($post->featured_image, 'image');
        Storage::disk('local')->put($media->path, 'image');
        $this->actingAs($this->editor())->delete(route('admin.posts.destroy', $post))->assertRedirect(route('admin.posts.index'));
        $this->assertModelMissing($post);
        $this->assertModelMissing($media);
        Storage::disk('local')->assertMissing($post->featured_image);
        Storage::disk('local')->assertMissing($media->path);
    }
}
