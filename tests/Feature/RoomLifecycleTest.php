<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('join form renders', function () {
    $this->get(route('rooms.join'))
        ->assertOk()
        ->assertSeeText('Enter a room code to jump in');
});

test('join submit validates missing code', function () {
    $this->post(route('rooms.join.submit'), ['code' => ' '])
        ->assertSessionHasErrors('code');
});

test('join submit accepts full url and redirects', function () {
    $room = Room::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Join room',
        'slug' => Str::random(8),
    ]);

    $input = 'https://example.test/r/' . $room->slug . '?ref=1';

    $this->post(route('rooms.join.submit'), ['code' => $input])
        ->assertRedirect(route('rooms.public', $room->slug));
});

test('room exists endpoint reports status', function () {
    $room = Room::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Exists room',
        'slug' => Str::random(8),
    ]);

    $this->getJson(route('rooms.exists', $room->slug))
        ->assertOk()
        ->assertJson(['exists' => true]);

    $this->getJson(route('rooms.exists', 'missing-' . Str::random(6)))
        ->assertOk()
        ->assertJson(['exists' => false]);
});

test('owner can create update and delete a room', function () {
    $owner = User::factory()->create();

    $create = $this->actingAs($owner)->post(route('rooms.store'), [
        'title' => 'My room',
        'description' => 'Demo',
        'is_public_read' => 1,
    ]);

    $room = Room::where('title', 'My room')->first();
    expect($room)->not->toBeNull();
    $create->assertRedirect(route('rooms.public', $room->slug));

    $this->actingAs($owner)
        ->patch(route('rooms.update', $room), ['status' => 'finished'])
        ->assertSessionHasNoErrors();

    $room->refresh();
    expect($room->status)->toBe('finished');
    expect($room->finished_at)->not->toBeNull();

    $this->actingAs($owner)
        ->patch(route('rooms.update', $room), ['status' => 'active'])
        ->assertSessionHasNoErrors();

    $room->refresh();
    expect($room->status)->toBe('active');
    expect($room->finished_at)->toBeNull();

    $this->actingAs($owner)
        ->patch(route('rooms.update', $room), ['card_color' => 'teal'])
        ->assertSessionHasNoErrors();

    $room->refresh();
    expect($room->card_color)->toBe('teal');

    $this->actingAs($owner)
        ->patch(route('rooms.update', $room), ['card_color' => 'default'])
        ->assertSessionHasNoErrors();

    $room->refresh();
    expect($room->card_color)->toBeNull();

    $this->actingAs($owner)
        ->delete(route('rooms.destroy', $room), ['confirm_title' => $room->title])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
});

test('non owners cannot update or delete rooms', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Locked',
        'slug' => Str::random(8),
    ]);

    $this->actingAs($other)
        ->patch(route('rooms.update', $room), ['title' => 'Nope'])
        ->assertStatus(403);

    $this->actingAs($other)
        ->delete(route('rooms.destroy', $room), ['confirm_title' => $room->title])
        ->assertStatus(403);
});

test('owner can reorder rooms and persist sort order', function () {
    $owner = User::factory()->create();

    $first = Room::create([
        'user_id' => $owner->id,
        'title' => 'First',
        'slug' => Str::random(8),
        'sort_order' => 1,
    ]);
    $second = Room::create([
        'user_id' => $owner->id,
        'title' => 'Second',
        'slug' => Str::random(8),
        'sort_order' => 2,
    ]);
    $third = Room::create([
        'user_id' => $owner->id,
        'title' => 'Third',
        'slug' => Str::random(8),
        'sort_order' => 3,
    ]);

    $this->actingAs($owner)
        ->patchJson(route('rooms.reorder'), ['room_order' => [$third->id, $first->id, $second->id]])
        ->assertOk()
        ->assertJson(['message' => 'Room order saved.']);

    expect($third->fresh()->sort_order)->toBe(1);
    expect($first->fresh()->sort_order)->toBe(2);
    expect($second->fresh()->sort_order)->toBe(3);
});

test('room deletion requires confirm title', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Confirm me',
        'slug' => Str::random(8),
    ]);

    $this->actingAs($owner)
        ->delete(route('rooms.destroy', $room), ['confirm_title' => 'Wrong'])
        ->assertSessionHasErrors('confirm_title');

    $this->assertDatabaseHas('rooms', ['id' => $room->id]);
});

test('room card color validates against allowed palette', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Color check',
        'slug' => Str::random(8),
    ]);

    $this->actingAs($owner)
        ->patch(route('rooms.update', $room), ['card_color' => 'not-a-color'])
        ->assertSessionHasErrors('card_color');
});
