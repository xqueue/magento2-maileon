<?php

namespace Xqueue\Maileon\Cron;

use Exception;
use GuzzleHttp\Exception\GuzzleException;

class PluginStatusReporter
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Xqueue\Maileon\Model\Maileon\PluginStatusReporterService
     */
    protected $pluginStatusReporterService;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Xqueue\Maileon\Model\Maileon\PluginStatusReporterService $pluginStatusReporterService
    ) {
        $this->logger = $logger;
        $this->pluginStatusReporterService = $pluginStatusReporterService;
    }

    public function execute()
    {
        try {
            $this->pluginStatusReporterService->sendHeartbeatReport();
        } catch (Exception|GuzzleException $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
