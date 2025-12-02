<?php

use NWC\Client;
use PHPUnit\Framework\TestCase;
use Dotenv\Dotenv;

final class ClientTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }

    private function client(): Client
    {
        $connection_uri = $_ENV['NWC_CONNECTION_URI'];
        if (!$connection_uri) {
            $this->markTestSkipped('NWC_CONNECTION_URI environment variable not set');
        }
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
    public function testInvalidConnectionStringScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NWC connection URI: must start with nostr+walletconnect://');
        new Client('nostr%2Bwalletconnect%3A%2F%2Fb3cc782194c0cc2376310a67f950c04f113152e34ec6cddb455caedb45d2b2dd%3Frelay%3Dwss%3A%2F%2Frelay.getalby.com%26secret%3D2f14c2cfb7e55c60de2dcbd325c8bae8b06cb19901d0e2b45bb64d8a936c5035');
    }

    public function testInvalidPubkey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid pubkey');
        new Client('nostr+walletconnect://invalidpubkey?relay=wss://relay.getalby.com&secret=2f14c2cfb7e55c60de2dcbd325c8bae8b06cb19901d0e2b45bb64d8a936c5035');
    }

    public function testInvalidSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid secret');
        new Client('nostr+walletconnect://b3cc782194c0cc2376310a67f950c04f113152e34ec6cddb455caedb45d2b2dd?relay=wss://relay.getalby.com&secret=invalidsecret');
    }

    public function testInvalidRelayUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid relay URL');
        new Client('nostr+walletconnect://b3cc782194c0cc2376310a67f950c04f113152e34ec6cddb455caedb45d2b2dd?relay=invalid-relay-url&secret=2f14c2cfb7e55c60de2dcbd325c8bae8b06cb19901d0e2b45bb64d8a936c5035');
    }
}
