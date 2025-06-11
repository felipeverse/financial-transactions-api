<?php

namespace App\Jobs;

use Psr\Log\LogLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AsyncLoggerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $channel;

    protected const VALID_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    public function __construct(
        string $channel = null,
        protected string $level = LogLevel::INFO,
        protected string $message = '',
        protected array $context = []
    ) {
        $this->channel = $channel ?? config('logging.default');
        $this->queue = 'logs';
    }

    public function handle(): void
    {
        if (!in_array(strtolower($this->level), self::VALID_LEVELS, true)) {
            $this->level = LogLevel::INFO;
        }

        Log::channel($this->channel)->{$this->level}(
            $this->message,
            $this->context
        );
    }
}
