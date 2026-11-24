<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Exception types that are too common/expected to warrant a webhook ping.
     */
    protected array $skipWebhookFor = [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ];

    /**
     * Redirect unauthenticated users to the correct Filament panel login.
     * Laravel's default looks for a 'login' route which doesn't exist here.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return redirect()->guest(route('filament.admin.auth.login'));
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->sendErrorWebhook($e);
        });
    }

    /**
     * Salah ketik artisan di terminal tidak dilaporkan ke log, Watchpost, maupun Discord.
     */
    protected function shouldntReport(Throwable $e)
    {
        return $this->isArtisanTypo($e) || parent::shouldntReport($e);
    }

    /**
     * Perintah artisan yang salah ketik, mis. `php artisan migrate --status` (Watchpost #21).
     * Symfony menolak opsi, argumen, atau nama perintahnya sebelum kode aplikasi jalan, dan
     * pesannya sudah tampil di terminal orang yang mengetik. Cirinya: di antara
     * `Kernel::handle()` dan titik lempar hanya ada mesin konsol. `Artisan::call()` atau
     * `$this->call()` dari kode dengan opsi keliru tetap dilaporkan karena itu bug.
     */
    private function isArtisanTypo(Throwable $e): bool
    {
        if (! $e instanceof ConsoleException) {
            return false;
        }

        foreach ($e->getTrace() as $frame) {
            $class = $frame['class'] ?? '';

            if ($class === ConsoleKernel::class && $frame['function'] === 'handle') {
                return true;
            }

            $isConsoleMachinery = str_starts_with($class, 'Symfony\\Component\\Console\\')
                || ($class === Command::class && $frame['function'] === 'run');

            if (! $isConsoleMachinery) {
                return false;
            }
        }

        return false;
    }

    /** Kirim notifikasi contoh ke Discord untuk menguji format & pengiriman (lewati guard production). */
    public function sendTestWebhook(Throwable $e): bool
    {
        return $this->sendErrorWebhook($e, true);
    }

    private function sendErrorWebhook(Throwable $e, bool $force = false): bool
    {
        // Hanya kirim notifikasi error di production — local/dev tidak perlu ping Discord.
        // $force = true dipakai command uji (discord:test-error) untuk melewati guard ini.
        if (! $force && ! app()->isProduction()) {
            return false;
        }

        $webhookUrl = config('app.discord_webhook_url');

        if (empty($webhookUrl)) {
            return false;
        }

        foreach ($this->skipWebhookFor as $skipped) {
            if ($e instanceof $skipped) {
                return false;
            }
        }

        try {
            // Deduplicate — skip if same error was sent recently (mode uji selalu kirim).
            $cacheKey = 'discord_error:' . md5(get_class($e) . $e->getMessage() . $e->getFile() . $e->getLine());
            $cooldownMinutes = (int) env('DISCORD_ERROR_COOLDOWN_MINUTES', 15);

            if (! $force && Cache::has($cacheKey)) {
                return false;
            }

            if (! $force) {
                Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
            }

            $request  = request();
            $appName  = config('app.name');
            $env      = config('app.env');
            $context  = $this->resolveRequestContext($request);

            // Build mention string from comma-separated IDs
            $mentionIds = array_filter(explode(',', config('app.discord_mention_ids', '')));
            $mentions   = implode(' ', array_map(fn($id) => '<@' . trim($id) . '>', $mentionIds));

            // Pesan dipangkas agar muat di batas embed Discord.
            $message = $e->getMessage();
            if (mb_strlen($message) > 1500) {
                $message = mb_substr($message, 0, 1500) . '…';
            }

            $fields = [
                [
                    'name'   => '📍 Halaman',
                    'value'  => $context['page'] ?: 'N/A',
                    'inline' => false,
                ],
            ];

            // Konteks "sedang apa": komponen + aksi Livewire, atau nama route biasa.
            if ($context['action']) {
                $fields[] = [
                    'name'   => '🧩 Komponen / Aksi',
                    'value'  => '`' . $context['action'] . '`',
                    'inline' => false,
                ];
            } elseif ($context['route']) {
                $fields[] = [
                    'name'   => '🧭 Route',
                    'value'  => '`' . $context['route'] . '`',
                    'inline' => false,
                ];
            }

            $fields[] = [
                'name'   => '📦 Exception',
                'value'  => '`' . get_class($e) . '`',
                'inline' => false,
            ];
            $fields[] = [
                'name'   => '📄 File',
                'value'  => '`' . str_replace(base_path() . '/', '', $e->getFile()) . ':' . $e->getLine() . '`',
                'inline' => false,
            ];
            $fields[] = [
                'name'   => '👤 User',
                'value'  => $context['user'],
                'inline' => true,
            ];
            $fields[] = [
                'name'   => '📡 Method',
                'value'  => $request?->method() ?? 'N/A',
                'inline' => true,
            ];
            $fields[] = [
                'name'   => '🌍 Environment',
                'value'  => $env,
                'inline' => true,
            ];

            $response = Http::timeout(5)->post($webhookUrl, [
                'content' => $mentions ?: null,
                'embeds' => [
                    [
                        'title'       => '🚨 Error — ' . class_basename($e),
                        'color'       => 0xE74C3C, // red
                        'description' => '```' . $message . '```',
                        'fields'      => $fields,
                        'footer'      => ['text' => $appName],
                        'timestamp'   => now()->toIso8601String(),
                    ],
                ],
            ]);

            return $response->successful();
        } catch (Throwable) {
            // Silently fail — never let the webhook call break the app
            Log::warning('Discord error webhook delivery failed for: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Ringkas konteks request agar notifikasi mudah dipahami:
     * - page   : halaman sebenarnya (referer untuk request Livewire, bukan /livewire/update)
     * - action : komponen + method Livewire yang sedang dijalankan
     * - route  : nama route (untuk request non-Livewire)
     * - user   : siapa yang mengalami error
     *
     * @return array{page: ?string, action: ?string, route: ?string, user: string}
     */
    private function resolveRequestContext($request): array
    {
        if (! $request) {
            return ['page' => null, 'action' => null, 'route' => null, 'user' => 'N/A'];
        }

        $path       = (string) $request->path();
        $isLivewire = str_contains($path, 'livewire/');

        // Halaman: untuk Livewire pakai referer (halaman asli), selain itu URL saat ini.
        $page = $isLivewire
            ? ($request->headers->get('referer') ?: $request->fullUrl())
            : $request->fullUrl();

        // Komponen + aksi Livewire dari payload update.
        $action = null;
        if ($isLivewire) {
            $snapshot  = $request->input('components.0.snapshot');
            $component = is_string($snapshot)
                ? data_get(json_decode($snapshot, true), 'memo.name')
                : null;
            $method = data_get($request->input('components.0.calls.0'), 'method');

            if ($component || $method) {
                $action = ($component ?: 'unknown') . ($method ? " → {$method}()" : '');
            }
        }

        $route = (! $isLivewire) ? $request->route()?->getName() : null;

        $user = auth()->user();
        $userLabel = $user
            ? (($user->name ?? $user->email ?? 'User') . ' (#' . $user->getKey() . ')')
            : 'Guest';

        return [
            'page'   => $page,
            'action' => $action,
            'route'  => $route,
            'user'   => $userLabel,
        ];
    }
}
