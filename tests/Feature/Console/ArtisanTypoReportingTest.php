<?php

namespace Tests\Feature\Console;

use Closure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Throwable;

/**
 * Artisan typed wrong at the terminal (Watchpost error #21, `php artisan migrate --status`):
 * the person typing sees the message, nothing is reported. Wrong calls from code still are.
 */
class ArtisanTypoReportingTest extends TestCase
{
    /** @var array<int, Throwable> */
    private array $reported = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['logging.default' => 'null']);

        // Only exceptions that pass shouldReport() reach reportable callbacks.
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) {
            $this->reported[] = $e;
        });
    }

    public static function terminalTypos(): array
    {
        return [
            'option that does not exist (Watchpost #21)' => [['migrate', '--status'], 'The "--status" option does not exist.'],
            'short option that does not exist' => [['migrate:status', '-Z'], 'The "-Z" option does not exist.'],
            'argument a command does not take' => [['migrate:status', 'extra'], 'No arguments expected for "migrate:status" command, got "extra".'],
            'missing argument' => [['make:model'], 'Not enough arguments (missing: "name").'],
            'command that does not exist' => [['deploy:sekarang'], 'There are no commands defined in the "deploy" namespace.'],
        ];
    }

    #[DataProvider('terminalTypos')]
    public function test_typo_at_the_terminal_is_shown_but_not_reported(array $arguments, string $message): void
    {
        [$status, $output] = $this->artisanFromTerminal(...$arguments);

        $this->assertSame(1, $status);
        $this->assertStringContainsString($message, $output);
        $this->assertSame([], $this->reported);
    }

    public function test_wrong_artisan_call_from_code_is_still_reported(): void
    {
        $this->registerCommand('uji:artisan-call', fn () => Artisan::call('migrate --status'));

        [$status] = $this->artisanFromTerminal('uji:artisan-call');

        $this->assertSame(1, $status);
        $this->assertReportedOnce('The "--status" option does not exist.');
    }

    public function test_wrong_call_to_another_command_is_still_reported(): void
    {
        // ClosureCommand binds the closure to the command, so $this->call() is Command::call().
        $this->registerCommand('uji:sub-call', function () {
            return $this->call('migrate', ['--status' => true]);
        });

        [$status] = $this->artisanFromTerminal('uji:sub-call');

        $this->assertSame(1, $status);
        $this->assertReportedOnce('The "--status" option does not exist.');
    }

    public function test_console_exception_thrown_by_command_code_is_still_reported(): void
    {
        $this->registerCommand('uji:gagal', fn () => throw new ConsoleRuntimeException('Gagal di dalam perintah.'));

        [$status] = $this->artisanFromTerminal('uji:gagal');

        $this->assertSame(1, $status);
        $this->assertReportedOnce('Gagal di dalam perintah.');
    }

    /**
     * Runs artisan the way `php artisan ...` on the server does, without prompts.
     *
     * @return array{0: int, 1: string}
     */
    private function artisanFromTerminal(string ...$arguments): array
    {
        $input = new ArgvInput(['artisan', ...$arguments]);
        $input->setInteractive(false);
        $output = new BufferedOutput;

        $status = $this->app->make(Kernel::class)->handle($input, $output);

        return [$status, $output->fetch()];
    }

    private function registerCommand(string $signature, Closure $callback): void
    {
        $this->app->make(Kernel::class)->registerCommand(new ClosureCommand($signature, $callback));
    }

    private function assertReportedOnce(string $message): void
    {
        $this->assertCount(1, $this->reported);
        $this->assertSame($message, $this->reported[0]->getMessage());
    }
}
