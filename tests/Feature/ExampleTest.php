<?php

declare(strict_types=1);

test('the api health endpoint returns 200 with status ok', function () {
    $response = $this->get('/api/health');

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok']);
});
