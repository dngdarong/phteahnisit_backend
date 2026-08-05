<?php

test('the general api rate limit returns 429 once exceeded', function () {
    config(['phteahnisit.api.max_attempts' => 2, 'phteahnisit.api.decay_minutes' => 1]);

    $this->getJson('/api/rooms')->assertOk();
    $this->getJson('/api/rooms')->assertOk();
    $response = $this->getJson('/api/rooms');

    $response->assertStatus(429);
    $response->assertJson(['message' => 'Too many requests. Please try again later.']);
});

test('the general api rate limit does not block a request under the threshold', function () {
    config(['phteahnisit.api.max_attempts' => 60, 'phteahnisit.api.decay_minutes' => 1]);

    $this->getJson('/api/rooms')->assertOk();
});
