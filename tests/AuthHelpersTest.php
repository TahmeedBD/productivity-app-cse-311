<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/helpers.php';

use PHPUnit\Framework\TestCase;

final class AuthHelpersTest extends TestCase
{
    public function testGenerateUuidReturnsUuidV4String(): void
    {
        $uuid = generate_uuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
    }

    public function testGenerateUuidReturnsDifferentValuesOnConsecutiveCalls(): void
    {
        $firstUuid = generate_uuid();
        $secondUuid = generate_uuid();

        $this->assertNotSame($firstUuid, $secondUuid);
    }
}
