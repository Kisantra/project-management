<?php

namespace Tests\Unit\Access;

use App\Support\Access\PathPermission;
use PHPUnit\Framework\TestCase;

class PathPermissionTest extends TestCase
{
    public function test_exact_pattern_matches_only_that_path(): void
    {
        $this->assertTrue(PathPermission::matches('clients', 'clients'));
        $this->assertTrue(PathPermission::matches('/clients/', 'clients'));
        $this->assertFalse(PathPermission::matches('clients', 'clients/12'));
    }

    public function test_trailing_star_matches_everything_below(): void
    {
        $this->assertTrue(PathPermission::matches('clients*', 'clients'));
        $this->assertTrue(PathPermission::matches('clients*', 'clients/12/edit'));
        $this->assertTrue(PathPermission::matches('clients*', 'clients-archive'));
        $this->assertFalse(PathPermission::matches('clients*', 'projects'));
    }

    public function test_record_pattern_covers_list_and_detail_only(): void
    {
        // Same as the old access plugin; roles rely on this exact behaviour.
        $this->assertTrue(PathPermission::matches('clients/*', 'clients'));
        $this->assertTrue(PathPermission::matches('clients/*', 'clients/12'));
        $this->assertFalse(PathPermission::matches('clients/*', 'clients/12/edit'));
        $this->assertFalse(PathPermission::matches('clients/*', 'clients/create'));
        $this->assertTrue(PathPermission::matches('clients/*/edit', 'clients/12/edit'));
        $this->assertTrue(PathPermission::matches('clients/create', 'clients/create'));
    }

    public function test_patterns_split_lines_and_commas(): void
    {
        $this->assertSame(['clients*', 'pics'], PathPermission::patterns("/clients*/\r\n pics ,"));
        $this->assertSame([], PathPermission::patterns(null));
    }

    public function test_public_paths(): void
    {
        foreach (['', 'login', 'logout', 'error/403', 'assets/app.css'] as $path) {
            $this->assertTrue(PathPermission::isPublic($path), $path);
        }

        $this->assertFalse(PathPermission::isPublic('clients'));
        $this->assertFalse(PathPermission::isPublic('peran-izin'));
    }
}
