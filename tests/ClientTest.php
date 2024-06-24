<?php

use NWC\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function client(): Client
    {
        $connection_uri = 'nostr+walletconnect://69effe7b49a6dd5cf525bd0905917a5005ffe480b58eeb8e861418cf3ae760d9?relay=wss://relay.getalby.com/v1&secret=01824b648f94760ab8b0b57b6f2b7b1a962969de612f4b986898c8d88eeddaf9&lud16=adithyavardhan@getalby.com';
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
