<?php

use NWC\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function client(): Client
    {
        $connection_uri = 'nostr+walletconnect://<pubkey>?relay=wss://relay.getalby.com/v1&secret=<secret>';
        return new Client($connection_uri);
    }
    public function testCanBeInitialized(): void
    {
        $this->assertInstanceOf(
            Client::class,
            $this->client(),
        );
    }

    public function testCanGetInfo(): void
    {
        $client = $this->client();
        $this->assertIsString($client->getInfo()['alias']);
    }

    public function testCanGetBalance(): void
    {
        $client = $this->client();
        $this->assertIsNumeric($client->getBalance()['balance']);
    }

    public function testCanAddInvoice(): void
    {
        $client = $this->client();
        $response = $client->addInvoice([
            'value' => 23,
            'memo' => 'test invoice'
        ]);
        $this->assertIsString($response['payment_request']);
        $this->assertIsString($response['r_hash']);
    }

    public function testCanGetInvoice(): void
    {
        $client = $this->client();
        $response = $client->addInvoice([
            'value' => 23,
            'memo' => 'test invoice'
        ]);
        $invoice = $client->getInvoice($response['r_hash']);

        $this->assertArrayHasKey('settled', $invoice);
    }
}
