<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Docker inyecta MAIL_MAILER=resend; las pruebas nunca deben usar la API real.
        config(['mail.default' => 'array']);
    }
}
