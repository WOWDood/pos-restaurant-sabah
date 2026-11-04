<?php

it('does not allow public staff registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'New user', 'email' => 'new@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();
    $this->get('/login')->assertOk()->assertDontSee('Sign up');
});
