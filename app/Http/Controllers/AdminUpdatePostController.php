<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\UpdatePost;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUpdatePostController extends Controller
{
    public function storeBlog(Request $request): RedirectResponse
    {
        $post = new UpdatePost();
        $this->persist($post, $request, UpdatePost::TYPE_BLOG);

        AuditLog::record($request, 'admin.blog.create', [
            'target_type' => 'update_post',
            'target_id' => $post->id,
            'metadata' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'is_published' => (bool) $post->is_published,
            ],
        ]);

        return $this->redirectToAdmin('Update post saved.');
    }

    public function updateBlog(Request $request, UpdatePost $post): RedirectResponse
    {
        if ($post->type !== UpdatePost::TYPE_BLOG) {
            abort(404);
        }

        $this->persist($post, $request, UpdatePost::TYPE_BLOG);

        AuditLog::record($request, 'admin.blog.update', [
            'target_type' => 'update_post',
            'target_id' => $post->id,
            'metadata' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'is_published' => (bool) $post->is_published,
            ],
        ]);

        return $this->redirectToAdmin('Update post updated.');
    }

    public function destroyBlog(Request $request, UpdatePost $post): RedirectResponse
    {
        if ($post->type !== UpdatePost::TYPE_BLOG) {
            abort(404);
        }

        $this->deleteImage($post);

        $postId = $post->id;
        $title = $post->title;
        $slug = $post->slug;
        $post->delete();

        AuditLog::record($request, 'admin.blog.delete', [
            'target_type' => 'update_post',
            'target_id' => $postId,
            'metadata' => [
                'title' => $title,
                'slug' => $slug,
            ],
        ]);

        return $this->redirectToAdmin('Update post removed.');
    }

    public function storeRelease(Request $request): RedirectResponse
    {
        $post = new UpdatePost();
        $this->persist($post, $request, UpdatePost::TYPE_WHATS_NEW);

        if ($request->boolean('set_as_version')) {
            Setting::setValue('app_version', $post->version);
        }

        AuditLog::record($request, 'admin.release.create', [
            'target_type' => 'update_post',
            'target_id' => $post->id,
            'metadata' => [
                'title' => $post->title,
                'version' => $post->version,
                'is_published' => (bool) $post->is_published,
            ],
        ]);

        return $this->redirectToAdmin('Release saved.');
    }

    public function updateRelease(Request $request, UpdatePost $post): RedirectResponse
    {
        if ($post->type !== UpdatePost::TYPE_WHATS_NEW) {
            abort(404);
        }

        $this->persist($post, $request, UpdatePost::TYPE_WHATS_NEW);

        if ($request->boolean('set_as_version')) {
            Setting::setValue('app_version', $post->version);
        }

        AuditLog::record($request, 'admin.release.update', [
            'target_type' => 'update_post',
            'target_id' => $post->id,
            'metadata' => [
                'title' => $post->title,
                'version' => $post->version,
                'is_published' => (bool) $post->is_published,
            ],
        ]);

        return $this->redirectToAdmin('Release updated.');
    }

    public function destroyRelease(Request $request, UpdatePost $post): RedirectResponse
    {
        if ($post->type !== UpdatePost::TYPE_WHATS_NEW) {
            abort(404);
        }

        $this->deleteImage($post);

        $postId = $post->id;
        $title = $post->title;
        $version = $post->version;
        $post->delete();

        AuditLog::record($request, 'admin.release.delete', [
            'target_type' => 'update_post',
            'target_id' => $postId,
            'metadata' => [
                'title' => $title,
                'version' => $version,
            ],
        ]);

        return $this->redirectToAdmin('Release removed.');
    }

    public function updateVersion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version' => [
                'required',
                'string',
                'max:' . config('ghostroom.limits.update_post.version_max', 50),
            ],
        ]);

        $previous = Setting::getValue('app_version', config('app.version', '1.0.0'));
        $setting = Setting::setValue('app_version', $data['version']);

        AuditLog::record($request, 'admin.version.update', [
            'target_type' => 'setting',
            'target_id' => $setting->id,
            'metadata' => [
                'from' => $previous,
                'to' => $data['version'],
            ],
        ]);

        return $this->redirectToAdmin('Project version updated to '.$data['version'].'.');
    }

    protected function persist(UpdatePost $post, Request $request, string $type): UpdatePost
    {
        $rules = [
            'title' => [
                'required',
                'string',
                'max:' . config('ghostroom.limits.update_post.title_max', 255),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:' . config('ghostroom.limits.update_post.slug_max', 255),
                Rule::unique('update_posts', 'slug')->ignore($post->id),
            ],
            'excerpt' => [
                'nullable',
                'string',
                'max:' . config('ghostroom.limits.update_post.excerpt_max', 500),
            ],
            'body' => ['required', 'string'],
            'image' => [
                'nullable',
                'image',
                'max:' . config('ghostroom.limits.update_post.image_max_kb', 4096),
            ],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'remove_image' => ['nullable', 'boolean'],
        ];

        if ($type === UpdatePost::TYPE_WHATS_NEW) {
            $rules['version'] = [
                'required',
                'string',
                'max:' . config('ghostroom.limits.update_post.version_max', 50),
            ];
            $rules['set_as_version'] = ['nullable', 'boolean'];
        } else {
            $rules['version'] = [
                'nullable',
                'string',
                'max:' . config('ghostroom.limits.update_post.version_max', 50),
            ];
        }

        $data = $request->validate($rules);
        $slugInput = $data['slug'] ?? null;
        $slug = $slugInput ?: $post->slug ?: Str::slug($data['title']);

        $post->fill([
            'type' => $type,
            'title' => $data['title'],
            'slug' => $slug ?: Str::random(8),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'version' => $type === UpdatePost::TYPE_WHATS_NEW ? $data['version'] : null,
        ]);

        if (!$post->excerpt && $post->body) {
            $post->excerpt = Str::limit(strip_tags($post->body), 220);
        }

        $isPublished = $request->boolean('is_published');
        $publishedAtInput = $data['published_at'] ?? null;
        $post->is_published = $isPublished;
        $post->published_at = $isPublished
            ? ($publishedAtInput ? Carbon::parse($publishedAtInput) : ($post->published_at ?? Carbon::now()))
            : null;

        if ($request->boolean('remove_image')) {
            $this->deleteImage($post);
            $post->image_path = null;
        }

        if ($request->hasFile('image')) {
            $existingImage = $post->image_path
                ? ltrim(str_replace('public/', '', $post->image_path), '/')
                : null;

            if ($existingImage) {
                Storage::disk('public')->delete($existingImage);
            }

            $post->image_path = $request->file('image')->store('updates', 'public');
        }

        $post->save();

        return $post;
    }

    protected function redirectToAdmin(string $message): RedirectResponse
    {
        $target = route('admin.index').'#updates';

        return redirect()
            ->to($target)
            ->with('status', $message);
    }

    protected function deleteImage(UpdatePost $post): void
    {
        if (! $post->image_path) {
            return;
        }

        $path = ltrim(str_replace('public/', '', $post->image_path), '/');
        Storage::disk('public')->delete($path);
    }
}
