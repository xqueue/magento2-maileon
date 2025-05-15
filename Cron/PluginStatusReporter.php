<?php

namespace Xqueue\Maileon\Cron;

use Throwable;
use Xqueue\Maileon\Model\Maileon\PluginStatusReporterService;
use Xqueue\Maileon\Logger\Logger;

class PluginStatusReporter
{
    public function __construct(
        private Logger $logger,
        private PluginStatusReporterService $pluginStatusReporterService
    ) {}

    public function execute(): void
    {
        try {
            $this->pluginStatusReporterService->sendHeartbeatReport();
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
