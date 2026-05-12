<?php

test('root redirects to dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
