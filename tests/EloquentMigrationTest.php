<?php

declare(strict_types=1);

namespace Tests;

use App\Application;
use App\Database\MigrationRunner;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

final class EloquentMigrationTest extends TestCase
{
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $app = Application::boot(dirname(__DIR__));
        $this->runner = new MigrationRunner(
            $app->db->getDatabaseManager(),
            $app->config['database']['migrations']['path'],
        );
    }

    public function testMigrateCreatesUsersTableAndRollbackDropsIt(): void
    {
        $this->assertSame(['2026_10_05_000000_create_users_table'], $this->runner->migrate());
        $this->assertTrue(Capsule::schema()->hasTable('users'));
        $this->assertSame([], $this->runner->migrate(), 'second run has nothing to do');

        $this->runner->rollback();
        $this->assertFalse(Capsule::schema()->hasTable('users'));
    }

    public function testEloquentModelWorksAfterMigration(): void
    {
        $this->runner->migrate();

        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $this->assertSame(1, User::count());
        $this->assertSame('Ada', User::find($user->id)?->name);
    }
}
