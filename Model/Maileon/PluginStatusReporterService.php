<?php

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\account\AccountService;
use Xqueue\Maileon\Helper\Config;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use stdClass;

class PluginStatusReporterService
{
    public function __construct(
        protected LoggerInterface $logger,
        protected Config $config
    ) {}

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function sendActivatedReport(): bool
    {
        return $this->sendReport('activated');
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function sendDeactivatedReport(): bool
    {
        return $this->sendReport('deactivated');
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function sendHeartbeatReport(): bool
    {
        return $this->sendReport('heartbeat');
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    protected function sendReport(string $type): bool
    {
        $accountParameters = $this->getAccountParameters();

        if (empty($accountParameters)) {
            return false;
        }

        $parameters = [
            'pluginID' => Config::XSIC_ID,
            'checkSum' => Config::XSIC_CHECKSUM,
            'accountID' => $accountParameters['accountID'],
            'clientHash' => $accountParameters['clientHash'],
            'event' => $type,
        ];

        $client = new Client();

        $uri = Config::XSIC_URL . '?' . http_build_query($parameters);

        $response = $client->get($uri);

        if ($response->getStatusCode() === 200) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    protected function getAccountParameters(): array
    {
        $accountInfo = $this->getAccountInfo();

        return [
            'accountID' => $accountInfo->id,
            'clientHash' => $this->createClientHash($accountInfo->name),
        ];
    }

    /**
     * @throws Exception
     */
    protected function getAccountInfo(): stdClass
    {
        $maileonConfig = [
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $this->getMaileonApiKey(),
            'TIMEOUT' => 30,
        ];

        $accountService = new AccountService($maileonConfig);
        $response = $accountService->getAccountInfo();

        if (! $response->isSuccess()) {
            throw new Exception('Account info not found! (API key is missing or invalid)');
        }

        return $response->getResult();
    }

    /**
     * Get Maileon Api key from plugin config
     * Default Api key
     *
     * @return string
     * @throws Exception
     */
    protected function getMaileonApiKey(): string
    {
        $apiKey = $this->config->getApiKey();

        if ($apiKey === '') {
            throw new Exception('Maileon API key not found!');
        }

        return $apiKey;
    }

    protected function createClientHash(string $accountName): string
    {
        if (empty($accountName)) {
            return '';
        }

        $firstChar = substr($accountName, 0, 1);
        $lastChar = substr($accountName, -1, 1);

        return $firstChar . $lastChar;
    }
}
