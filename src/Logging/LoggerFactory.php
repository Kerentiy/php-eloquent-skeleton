<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;

/**
 * Builds Monolog loggers. All channels share one UidProcessor, so every record
 * written while handling a single HTTP request carries the same "uid"
 * (it is also returned to the client as the X-Request-Id header).
 */
final class LoggerFactory
{
    private const LINE_FORMAT = "[%datetime%] %channel%.%level_name% [%extra.uid%]: %message% %context% %extra%\n";

    private readonly UidProcessor $uid;

    /** @param array<string, mixed> $config config/logging.php */
    public function __construct(private readonly array $config)
    {
        $this->uid = new UidProcessor(16);
    }

    public function requestId(): string
    {
        return $this->uid->getUid();
    }

    /** Creates a daily-rotated logger writing to {path}/{channel}.log */
    public function make(string $channel): Logger
    {
        $handler = new RotatingFileHandler(
            filename: sprintf('%s/%s.log', $this->config['path'], $channel),
            maxFiles: (int) $this->config['max_files'],
            level: Level::fromName($this->config['level']),
            filePermission: 0664,
        );
        $handler->setFormatter(new LineFormatter(
            format: self::LINE_FORMAT,
            dateFormat: 'Y-m-d H:i:s.v',
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true,
        ));

        return new Logger(
            name: $channel,
            handlers: [$handler],
            processors: [new PsrLogMessageProcessor(removeUsedContextFields: true), $this->uid],
        );
    }
}
