<?php

namespace Tests\Unit;

use App\Services\TwoFactorAuthenticationService;
use PHPUnit\Framework\TestCase;

class TwoFactorAuthenticationServiceTest extends TestCase
{
    public function test_totp_matches_the_rfc_6238_sha1_test_vector(): void
    {
        $service = new TwoFactorAuthenticationService;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $service->codeForCounter($secret, intdiv(59, 30)));
        $this->assertSame(intdiv(59, 30), $service->matchingCounter($secret, '287082', 59));
    }
}
