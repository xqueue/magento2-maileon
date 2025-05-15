<?php

namespace Xqueue\Maileon\Model\Maileon;

use Exception;
use Xqueue\Maileon\Helper\Config;
use de\xqueue\maileon\api\client\transactions\DataType;
use de\xqueue\maileon\api\client\transactions\TransactionsService;
use de\xqueue\maileon\api\client\transactions\TransactionType;
use de\xqueue\maileon\api\client\transactions\AttributeType;
use de\xqueue\maileon\api\client\transactions\Transaction;
use de\xqueue\maileon\api\client\transactions\ContactReference;

class TransactionService
{
    private TransactionsService $transactionsService;

    public function __construct(string $apiKey)
    {
        $maileonConfig = [
            'BASE_URI' => 'https://api.maileon.com/1.0',
            'API_KEY' => $apiKey,
            'TIMEOUT' => 20
        ];

        $this->transactionsService = new TransactionsService($maileonConfig);
    }

    /**
     * @throws Exception
     */
    public function sendTransaction(
        string $email,
        string $transactionName,
        array $content
    ): bool {
        if (! $this->existsTransactionType($transactionName)) {
            $this->setTransactionType($transactionName);
        }

        $transaction = new Transaction();
        $transaction->contact = new ContactReference();
        $transaction->contact->email = $email;
        $transaction->typeName = $transactionName;
        $transaction->content = $content;

        $result = $this->transactionsService->createTransactions([$transaction]);

        return $result->isSuccess();
    }

    public function existsTransactionType(string $transactionName): bool
    {
        $existsTransactionType = $this->transactionsService->getTransactionTypeByName($transactionName);

        if ($existsTransactionType->getStatusCode() === 404) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function setTransactionType(string $transactionName): bool
    {
        $transactionType = new TransactionType();
        $transactionType->name = $transactionName;
        $transactionType->attributes = $this->buildTransactionTypeFields($transactionName);

        $result = $this->transactionsService->createTransactionType($transactionType);

        return $result->isSuccess();

    }

    /**
     * @throws Exception
     */
    public function buildTransactionTypeFields(string $transactionName): array
    {
        $transactionTypes = Config::TRANSACTION_TYPES;
        $transactionAttributes = [];

        if (! array_key_exists($transactionName, $transactionTypes)) {
            throw new Exception('Invalid transaction type: ' . $transactionName);
        }

        $attributes = $transactionTypes[$transactionName]['fields'];

        if (array_key_exists('extra_fields', $transactionTypes[$transactionName])) {
            $attributes = array_merge($attributes, $transactionTypes[$transactionName]['extra_fields']);
        }

        if ($transactionTypes[$transactionName]['addGeneric']) {
            $attributes = $this->addGenericFields($attributes);
        }

        foreach ($attributes as $name => $type) {
            $transactionAttributes[] = new AttributeType(null, $name, DataType::getDataType($type), false);
        }

        return $transactionAttributes;
    }

    protected function addGenericFields(array $attributes): array
    {
        return array_merge($attributes, Config::GENERIC_FIELDS);
    }
}
