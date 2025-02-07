<?php

namespace Xqueue\Maileon\Model\Maileon;

use de\xqueue\maileon\api\client\account\AccountService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

class PluginStatusReporterService
{
    const XSIC_ID = '10017';

    const XSIC_CHECKSUM = 'L9_Z6734NbgB_xk7D23hRJZs';

    const XSIC_URL = 'https://integrations.maileon.com/xsic/tx.php';
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

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
            'pluginID' => self::XSIC_ID,
            'checkSum' => self::XSIC_CHECKSUM,
            'accountID' => $accountParameters['accountID'],
            'clientHash' => $accountParameters['clientHash'],
            'event' => $type,
        ];

        $client = new Client();

        $uri = self::XSIC_URL . '?' . http_build_query($parameters);

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
        $apiKey = (string) $this->scopeConfig->getValue(
            'syncplugin/general/api_key'
        );

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
